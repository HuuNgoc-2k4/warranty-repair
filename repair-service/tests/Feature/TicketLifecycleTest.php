<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param int $id
     * @param string $status
     * @return \Illuminate\Testing\TestResponse
     */
    protected function setStatus(int $id, string $status)
    {
        return $this->postJson("/tickets/{$id}/status", ['status' => $status], $this->serviceHeaders());
    }

    /**
     * Đi hết vòng đời; sang REPAIRING thì Inventory đánh dấu đã dùng linh kiện.
     */
    public function test_full_lifecycle(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');

        $this->setStatus($ticket->id, 'REPAIRING')->assertOk()->assertJsonPath('data.status', 'REPAIRING');
        $this->assertSame('CONSUMED', $inventory->reservations["T{$ticket->id}"]);
        $this->setStatus($ticket->id, 'DONE')->assertOk();
        $this->setStatus($ticket->id, 'RETURNED')->assertOk()->assertJsonPath('data.status', 'RETURNED');

        $this->assertDatabaseCount('ticket_events', 3);
    }

    /**
     * Không nhảy cóc và không đi ngược.
     */
    public function test_invalid_transitions_are_rejected(): void
    {
        $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');

        $this->setStatus($ticket->id, 'DONE')->assertStatus(422)->assertJsonPath('error_code', 'invalid_transition');
        $this->setStatus($ticket->id, 'CANCELLED')->assertStatus(422);
        $this->setStatus($ticket->id, 'NOPE')->assertStatus(422)->assertJsonPath('error_code', 'validation_failed');
        $this->setStatus(999, 'REPAIRING')->assertStatus(404);
    }

    /**
     * Gửi lại cùng đích thì trả kết quả hiện tại, không ghi thêm lịch sử.
     */
    public function test_repeating_a_transition_is_idempotent(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');

        $this->setStatus($ticket->id, 'REPAIRING')->assertOk();
        $this->setStatus($ticket->id, 'REPAIRING')->assertOk();

        $this->assertSame(1, $inventory->countCalls("POST /reservations/T{$ticket->id}/consume"));
        $this->assertDatabaseCount('ticket_events', 1);
    }

    /**
     * Consume lỗi: phiếu giữ nguyên RECEIVED, gửi lại thành công.
     */
    public function test_consume_failure_keeps_status_and_can_be_retried(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');
        $inventory->queues['consume'] = ['timeout'];

        $this->setStatus($ticket->id, 'REPAIRING')->assertStatus(503)->assertJsonPath('error_code', 'inventory_unavailable');
        $this->assertSame('RECEIVED', $ticket->fresh()->status);

        $this->setStatus($ticket->id, 'REPAIRING')->assertOk();
        $this->assertSame('REPAIRING', $ticket->fresh()->status);
    }

    /**
     * Consume đã ghi nhưng phản hồi timeout: tra lượt giữ thấy CONSUMED → phiếu sang REPAIRING, không bị kẹt ở RECEIVED.
     */
    public function test_consume_timeout_after_commit_is_resolved_by_lookup(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');
        $inventory->queues['consume'] = ['commit_timeout'];

        $this->setStatus($ticket->id, 'REPAIRING')->assertOk()->assertJsonPath('data.status', 'REPAIRING');

        $this->assertSame(1, $inventory->countCalls("POST /reservations/T{$ticket->id}/consume"));
        $this->assertSame('CONSUMED', $inventory->reservations["T{$ticket->id}"]);
    }

    /**
     * Consume timeout và không tra được: không đoán, phiếu giữ RECEIVED; gửi lại sau khi kho hoạt động thì thành công.
     */
    public function test_consume_unknown_state_keeps_ticket_and_retry_succeeds(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');
        $inventory->queues['consume'] = ['commit_timeout'];
        $inventory->lookupDown = true;

        $this->setStatus($ticket->id, 'REPAIRING')->assertStatus(503);
        $this->assertSame('RECEIVED', $ticket->fresh()->status);

        $inventory->lookupDown = false;
        $this->setStatus($ticket->id, 'REPAIRING')->assertOk();
        $this->assertSame('REPAIRING', $ticket->fresh()->status);
    }

    /**
     * Hủy phiếu trả linh kiện và chuyển CANCELLED.
     */
    public function test_cancel_releases_part(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');

        $this->postJson("/tickets/{$ticket->id}/cancel", [], $this->serviceHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame('RELEASED', $inventory->reservations["T{$ticket->id}"]);
    }

    /**
     * Hủy hai lần chỉ trả kho một lần.
     */
    public function test_cancel_twice_releases_once(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');

        $this->postJson("/tickets/{$ticket->id}/cancel", [], $this->serviceHeaders())->assertOk();
        $this->postJson("/tickets/{$ticket->id}/cancel", [], $this->serviceHeaders())->assertOk()->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame(1, $inventory->countCalls("POST /reservations/T{$ticket->id}/release"));
    }

    /**
     * Phiếu đang sửa, đã xong hoặc chưa rõ kết quả thì không hủy được, kho không đổi.
     */
    public function test_cancel_not_allowed_states(): void
    {
        $inventory = $this->fakeServices();
        $repairing = $this->makeTicket('REPAIRING');
        $done = $this->makeTicket('DONE');
        $pending = $this->makeTicket('PENDING');

        $this->postJson("/tickets/{$repairing->id}/cancel", [], $this->serviceHeaders())
            ->assertStatus(409)->assertJsonPath('error_code', 'cancel_not_allowed');
        $this->postJson("/tickets/{$done->id}/cancel", [], $this->serviceHeaders())->assertStatus(409);
        $this->postJson("/tickets/{$pending->id}/cancel", [], $this->serviceHeaders())
            ->assertStatus(409)->assertJsonPath('error_code', 'ticket_pending');

        $this->assertSame(0, $inventory->countCalls("POST /reservations/T{$repairing->id}/release"));
        $this->postJson('/tickets/999/cancel', [], $this->serviceHeaders())->assertStatus(404);
    }

    /**
     * Release lỗi hẳn: phiếu giữ RECEIVED, hủy lại sau được.
     */
    public function test_cancel_failure_keeps_ticket_and_can_be_retried(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');
        $inventory->queues['release'] = ['timeout'];

        $this->postJson("/tickets/{$ticket->id}/cancel", [], $this->serviceHeaders())->assertStatus(503);
        $this->assertSame('RECEIVED', $ticket->fresh()->status);

        $this->postJson("/tickets/{$ticket->id}/cancel", [], $this->serviceHeaders())->assertOk();
        $this->assertSame('CANCELLED', $ticket->fresh()->status);
    }

    /**
     * Release đã ghi nhưng phản hồi timeout: tra lượt giữ thấy RELEASED → hủy thành công, không hủy lại kho.
     */
    public function test_cancel_timeout_after_commit_is_resolved_by_lookup(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');
        $inventory->queues['release'] = ['commit_timeout'];

        $this->postJson("/tickets/{$ticket->id}/cancel", [], $this->serviceHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame(1, $inventory->countCalls("POST /reservations/T{$ticket->id}/release"));
    }

    /**
     * Release timeout và không tra được: không đoán, giữ nguyên phiếu.
     */
    public function test_cancel_unknown_state_keeps_ticket(): void
    {
        $inventory = $this->fakeServices();
        $ticket = $this->makeTicket('RECEIVED');
        $inventory->queues['release'] = ['commit_timeout'];
        $inventory->lookupDown = true;

        $this->postJson("/tickets/{$ticket->id}/cancel", [], $this->serviceHeaders())->assertStatus(503);

        $this->assertSame('RECEIVED', $ticket->fresh()->status);
    }
}
