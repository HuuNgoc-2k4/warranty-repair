<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCreationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Thiếu service key thì bị từ chối.
     */
    public function test_requires_service_key(): void
    {
        $this->postJson('/tickets', $this->ticketPayload())->assertStatus(401);
        $this->getJson('/tickets')->assertStatus(401);
    }

    /**
     * Luồng thành công chạm cả 3 dịch vụ: bảo hành → lưu PENDING → giữ linh kiện → RECEIVED.
     */
    public function test_creates_ticket_and_reserves_part(): void
    {
        $inventory = $this->fakeServices();

        $response = $this->createTicket()->assertStatus(201);

        $response->assertJsonPath('data.status', 'RECEIVED')->assertJsonPath('data.in_warranty', true);
        $id = $response->json('data.id');
        $this->assertSame('RESERVED', $inventory->reservations["T{$id}"]);
        $this->assertDatabaseHas('ticket_events', ['ticket_id' => $id, 'from_status' => 'PENDING', 'to_status' => 'RECEIVED']);
    }

    /**
     * Hết bảo hành vẫn nhận phiếu, đánh dấu in_warranty=false.
     */
    public function test_out_of_warranty_ticket_is_still_accepted(): void
    {
        $this->fakeServices();

        $this->createTicket('k', ['serial' => 'SN-OLD'])
            ->assertStatus(201)
            ->assertJsonPath('data.in_warranty', false)
            ->assertJsonPath('data.status', 'RECEIVED');
    }

    /**
     * Serial chưa đăng ký bị từ chối, không tạo phiếu, không đụng kho.
     */
    public function test_unknown_serial_is_rejected_without_side_effects(): void
    {
        $inventory = $this->fakeServices();

        $this->createTicket('k', ['serial' => 'NOPE'])->assertStatus(422)->assertJsonPath('error_code', 'device_not_found');

        $this->assertDatabaseCount('tickets', 0);
        $this->assertSame([], $inventory->calls);
    }

    /**
     * Warranty không trả lời: 503, chưa lưu gì nên gửi lại bình thường.
     */
    public function test_warranty_down_creates_nothing(): void
    {
        $this->fakeServices();

        $this->createTicket('k', ['serial' => 'SN-DOWN'])->assertStatus(503)->assertJsonPath('error_code', 'warranty_unavailable');

        $this->assertDatabaseCount('tickets', 0);
    }

    /**
     * Dữ liệu sai và thiếu Idempotency-Key.
     */
    public function test_validation(): void
    {
        $this->fakeServices();

        $this->postJson('/tickets', $this->ticketPayload(), $this->serviceHeaders())
            ->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        $this->createTicket('k', ['phone' => 'abc'])->assertStatus(422);
        $this->createTicket('k', ['qty' => 0])->assertStatus(422);
        $this->createTicket('k', ['serial' => ''])->assertStatus(422);
        $this->assertDatabaseCount('tickets', 0);
    }

    /**
     * Gửi lại cùng Idempotency-Key trả phiếu cũ, không tạo phiếu hay giữ kho lần nữa.
     */
    public function test_same_idempotency_key_returns_same_ticket(): void
    {
        $inventory = $this->fakeServices();

        $first = $this->createTicket('same')->assertStatus(201)->json('data.id');
        $second = $this->createTicket('same')->assertStatus(200)->json('data.id');

        $this->assertSame($first, $second);
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame(1, $inventory->countCalls('POST /reservations'));
    }

    /**
     * Cùng key nhưng nội dung khác là lỗi của client.
     */
    public function test_same_key_with_different_payload_conflicts(): void
    {
        $this->fakeServices();

        $this->createTicket('same')->assertStatus(201);
        $this->createTicket('same', ['issue' => 'Lỗi khác'])->assertStatus(409)->assertJsonPath('error_code', 'idempotency_key_conflict');

        $this->assertDatabaseCount('tickets', 1);
    }

    /**
     * Hết hàng là từ chối rõ ràng: phiếu FAILED, trả 409.
     */
    public function test_out_of_stock_marks_ticket_failed(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['out_of_stock'];

        $this->createTicket()->assertStatus(409)->assertJsonPath('error_code', 'out_of_stock');

        $ticket = Ticket::query()->firstOrFail();
        $this->assertSame('FAILED', $ticket->status);
        $this->assertSame('out_of_stock', $ticket->failure_reason);
        $this->assertSame([], $inventory->reservations);
    }

    /**
     * Gửi lại key của phiếu FAILED trả phiếu cũ, không chạy lại luồng.
     */
    public function test_replaying_failed_ticket_does_not_rerun(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['out_of_stock'];
        $this->createTicket('k')->assertStatus(409);

        $this->createTicket('k')->assertStatus(200)->assertJsonPath('data.status', 'FAILED');

        $this->assertSame(1, $inventory->countCalls('POST /reservations'));
    }

    /**
     * Giữ kho đã ghi xong nhưng phản hồi bị timeout: tra lượt giữ thấy có → RECEIVED, không giữ lần hai.
     */
    public function test_reserve_timeout_after_commit_is_resolved_by_lookup(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['commit_timeout'];

        $this->createTicket()->assertStatus(201)->assertJsonPath('data.status', 'RECEIVED');

        $this->assertSame(1, $inventory->countCalls('POST /reservations'));
        $this->assertCount(1, $inventory->reservations);
    }

    /**
     * Timeout mà kho chưa ghi: tra không thấy → gửi lại cùng ref một lần → RECEIVED.
     */
    public function test_reserve_timeout_before_commit_is_retried_once(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['timeout'];

        $this->createTicket()->assertStatus(201)->assertJsonPath('data.status', 'RECEIVED');

        $this->assertSame(2, $inventory->countCalls('POST /reservations'));
        $this->assertCount(1, $inventory->reservations);
    }

    /**
     * Inventory lỗi liên tục nhưng tra cứu được và chắc chắn chưa giữ: FAILED, kho không đổi.
     */
    public function test_persistent_failure_marks_ticket_failed_and_stock_untouched(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['error503', 'error503'];

        $this->createTicket()->assertStatus(503)->assertJsonPath('error_code', 'inventory_unavailable');

        $this->assertSame('FAILED', Ticket::query()->firstOrFail()->status);
        $this->assertSame([], $inventory->reservations);
    }

    /**
     * Vừa lỗi vừa không tra được: chưa rõ kết quả, phiếu ở PENDING (202) chờ đối soát, không bù trừ bừa.
     */
    public function test_unknown_state_leaves_ticket_pending(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['commit_timeout'];
        $inventory->lookupDown = true;

        $this->createTicket()->assertStatus(202)->assertJsonPath('data.status', 'PENDING');

        $this->assertSame('PENDING', Ticket::query()->firstOrFail()->status);
    }

    /**
     * Đối soát: phiếu PENDING mà kho đã giữ → RECEIVED.
     */
    public function test_reconcile_confirms_reservation_that_committed(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['commit_timeout'];
        $inventory->lookupDown = true;
        $this->createTicket()->assertStatus(202);
        $inventory->lookupDown = false;

        $this->postJson('/tickets/reconcile', ['older_than' => 0], $this->serviceHeaders())
            ->assertOk()
            ->assertJsonPath('data.checked', 1)
            ->assertJsonPath('data.received', 1);

        $this->assertSame('RECEIVED', Ticket::query()->firstOrFail()->status);
        $this->assertCount(1, $inventory->reservations);
    }

    /**
     * Đối soát: kho chưa có thì giữ lại bằng cùng ref (không trùng).
     */
    public function test_reconcile_reserves_when_reservation_is_missing(): void
    {
        $inventory = $this->fakeServices();
        $inventory->queues['reserve'] = ['timeout'];
        $inventory->lookupDown = true;
        $this->createTicket()->assertStatus(202);
        $inventory->lookupDown = false;

        $this->postJson('/tickets/reconcile', ['older_than' => 0], $this->serviceHeaders())->assertJsonPath('data.received', 1);

        $this->assertCount(1, $inventory->reservations);
    }

    /**
     * Đối soát: kho hết hàng thì phiếu thất bại; Inventory vẫn chết thì để nguyên PENDING.
     */
    public function test_reconcile_failed_and_still_pending(): void
    {
        $inventory = $this->fakeServices();
        $inventory->lookupDown = true;
        $this->makeTicket('PENDING');
        $this->postJson('/tickets/reconcile', ['older_than' => 0], $this->serviceHeaders())->assertJsonPath('data.pending', 1);

        $inventory->lookupDown = false;
        $inventory->queues['reserve'] = ['out_of_stock'];
        $this->postJson('/tickets/reconcile', ['older_than' => 0], $this->serviceHeaders())->assertJsonPath('data.failed', 1);

        $this->assertSame('FAILED', Ticket::query()->firstOrFail()->status);
    }

    /**
     * Đối soát bỏ qua phiếu còn mới (đang xử lý) và phiếu không phải PENDING.
     */
    public function test_reconcile_skips_recent_and_non_pending(): void
    {
        $this->fakeServices();
        $this->makeTicket('PENDING');
        $this->makeTicket('RECEIVED');

        $this->postJson('/tickets/reconcile', [], $this->serviceHeaders())->assertJsonPath('data.checked', 0);
    }

    /**
     * Lệnh artisan tickets:reconcile dùng chung logic đối soát.
     */
    public function test_reconcile_command(): void
    {
        $this->fakeServices();
        $this->makeTicket('PENDING');
        $this->inventory->reservations['T1'] = 'RESERVED';

        $this->artisan('tickets:reconcile', ['--older-than' => 0])->assertSuccessful();

        $this->assertSame('RECEIVED', Ticket::query()->firstOrFail()->status);
    }

    /**
     * Danh sách và lọc theo trạng thái.
     */
    public function test_list_and_filter(): void
    {
        $this->fakeServices();
        $this->makeTicket('RECEIVED');
        $this->makeTicket('DONE');

        $this->getJson('/tickets', $this->serviceHeaders())->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/tickets?status=DONE', $this->serviceHeaders())->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/tickets?status=BAD', $this->serviceHeaders())->assertStatus(422);
    }

    /**
     * Chi tiết phiếu kèm lịch sử, 404 khi không có.
     */
    public function test_show_ticket_with_events(): void
    {
        $this->fakeServices();
        $id = $this->createTicket()->json('data.id');

        $this->getJson("/tickets/{$id}", $this->serviceHeaders())->assertOk()->assertJsonCount(2, 'data.events');
        $this->getJson('/tickets/999', $this->serviceHeaders())->assertStatus(404)->assertJsonPath('error_code', 'ticket_not_found');
    }
}
