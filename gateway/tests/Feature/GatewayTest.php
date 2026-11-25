<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GatewayTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Chưa đăng nhập thì mọi trang đều chuyển về /login.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        foreach (['/tickets', '/tickets/create', '/tickets/1', '/inventory'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->post('/tickets', $this->formData())->assertRedirect('/login');
    }

    /**
     * Đăng nhập đúng vào danh sách phiếu; sai mật khẩu ở lại trang login.
     */
    public function test_login_and_logout(): void
    {
        $this->makeUser();

        $this->post('/login', ['email' => 'staff@gmail.com', 'password' => 'wrong'])->assertRedirect()->assertSessionHas('error');
        $this->assertGuest();

        $this->post('/login', ['email' => 'staff@gmail.com', 'password' => 'password'])->assertRedirect(route('tickets.index'));
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * Dữ liệu đăng nhập thiếu bị từ chối.
     */
    public function test_login_validation(): void
    {
        $this->post('/login', ['email' => 'bad'])->assertSessionHasErrors(['email', 'password']);
    }

    /**
     * Danh sách phiếu gọi Repair kèm service key và hiển thị dữ liệu.
     */
    public function test_ticket_list_calls_repair_with_service_key(): void
    {
        Http::fake(['repair.test/*' => Http::response(['data' => [$this->ticketData()]])]);

        $this->actingAs($this->makeUser())->get('/tickets?status=RECEIVED')
            ->assertOk()
            ->assertSee('Nguyễn Văn A')
            ->assertSee('Đã tiếp nhận');

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Service-Key', 'test-key') && str_contains($request->url(), 'status=RECEIVED'));
    }

    /**
     * Dịch vụ phía sau chết thì trang vẫn hiện, kèm thông báo lỗi.
     */
    public function test_ticket_list_survives_service_down(): void
    {
        Http::fake(fn () => throw new ConnectionException('down'));

        $this->actingAs($this->makeUser())->get('/tickets')->assertOk()->assertSee('Không kết nối được dịch vụ repair');
    }

    /**
     * Form tạo phiếu lấy thiết bị từ Warranty, linh kiện từ Inventory.
     */
    public function test_create_form_lists_devices_and_parts(): void
    {
        Http::fake([
            'warranty.test/*' => Http::response(['data' => [['serial' => 'SN-OK', 'model' => 'Laptop', 'valid' => true, 'expires_at' => '2027-01-01']]]),
            'inventory.test/*' => Http::response(['data' => [['id' => 1, 'name' => 'Màn hình', 'available' => 3]]])
        ]);

        $this->actingAs($this->makeUser())->get('/tickets/create')
            ->assertOk()
            ->assertSee('SN-OK - Laptop')
            ->assertSee('Màn hình (còn 3)')
            ->assertSee('name="idempotency_key"', false);
    }

    /**
     * Tạo phiếu: gửi Idempotency-Key và X-Simulate sang Repair rồi chuyển tới trang chi tiết.
     */
    public function test_store_ticket_forwards_idempotency_key_and_simulate(): void
    {
        Http::fake(['repair.test/*' => Http::response(['data' => $this->ticketData()], 201)]);

        $this->actingAs($this->makeUser())
            ->post('/tickets', $this->formData(['simulate' => 'slow']))
            ->assertRedirect(route('tickets.show', 7))
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://repair.test/tickets'
            && $request->hasHeader('Idempotency-Key', 'key-123')
            && $request->hasHeader('X-Simulate', 'slow')
            && $request['part_id'] === 1);
    }

    /**
     * Repair trả 202 (chưa rõ kết quả) thì hiện cảnh báo thay vì thành công.
     */
    public function test_store_ticket_pending_shows_warning(): void
    {
        Http::fake(['repair.test/*' => Http::response(['message' => 'Chưa xác định được kết quả.', 'data' => $this->ticketData(['status' => 'PENDING'])], 202)]);

        $this->actingAs($this->makeUser())->post('/tickets', $this->formData())
            ->assertRedirect(route('tickets.show', 7))
            ->assertSessionHas('warning');
    }

    /**
     * Lỗi nghiệp vụ (hết hàng) quay lại form, giữ dữ liệu nhập và đổi sang key mới.
     */
    public function test_store_ticket_business_error_drops_idempotency_key(): void
    {
        Http::fake(['repair.test/*' => Http::response(['message' => 'Không đủ linh kiện trong kho.', 'error_code' => 'out_of_stock'], 409)]);

        $this->actingAs($this->makeUser())->from('/tickets/create')->post('/tickets', $this->formData())
            ->assertRedirect('/tickets/create')
            ->assertSessionHas('error', 'Không đủ linh kiện trong kho.')
            ->assertSessionHasInput('customer', 'Nguyễn Văn A')
            ->assertSessionMissing('_old_input.idempotency_key');
    }

    /**
     * Mất kết nối tới Repair (chưa rõ đã nhận chưa) thì giữ nguyên key để gửi lại không tạo trùng.
     */
    public function test_store_ticket_connection_error_keeps_idempotency_key(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->actingAs($this->makeUser())->from('/tickets/create')->post('/tickets', $this->formData())
            ->assertSessionHasInput('idempotency_key', 'key-123')
            ->assertSessionHas('error');
    }

    /**
     * Dữ liệu form sai không gọi dịch vụ nào.
     */
    public function test_store_ticket_validation_makes_no_remote_call(): void
    {
        Http::fake();

        $this->actingAs($this->makeUser())->post('/tickets', $this->formData(['phone' => 'abc', 'simulate' => 'boom']))
            ->assertSessionHasErrors(['phone', 'simulate']);

        Http::assertNothingSent();
    }

    /**
     * Trang chi tiết hiện nút theo trạng thái.
     */
    public function test_show_ticket_actions_depend_on_status(): void
    {
        $status = 'RECEIVED';
        Http::fake(['repair.test/*' => function () use (&$status) {
            return Http::response(['data' => $this->ticketData(['status' => $status])]);
        }]);
        $this->actingAs($this->makeUser('ADMIN'));

        $this->get('/tickets/7')->assertOk()->assertSee('Bắt đầu sửa')->assertSee('Hủy phiếu');

        $status = 'REPAIRING';
        $this->get('/tickets/7')->assertOk()->assertSee('Đánh dấu sửa xong')->assertDontSee('Hủy phiếu');

        $status = 'CANCELLED';
        $this->get('/tickets/7')->assertOk()->assertDontSee('Thao tác');
    }

    /**
     * Phiếu không tồn tại quay về danh sách kèm lỗi.
     */
    public function test_show_missing_ticket_redirects_with_error(): void
    {
        Http::fake(['repair.test/*' => Http::response(['message' => 'Không tìm thấy phiếu.', 'error_code' => 'ticket_not_found'], 404)]);

        $this->actingAs($this->makeUser())->get('/tickets/99')->assertRedirect(route('tickets.index'))->assertSessionHas('error', 'Không tìm thấy phiếu.');
    }

    /**
     * Đổi trạng thái và hủy gọi đúng endpoint của Repair; lỗi hiện lại cho nhân viên.
     */
    public function test_change_status_and_cancel(): void
    {
        Http::fake(['repair.test/*' => Http::response(['data' => $this->ticketData()])]);

        $this->actingAs($this->makeUser('ADMIN'))->from('/tickets/7')->post('/tickets/7/status', ['status' => 'REPAIRING'])
            ->assertRedirect('/tickets/7')->assertSessionHas('success');
        $this->post('/tickets/7/cancel')->assertSessionHas('success');
        $this->post('/tickets/7/status', ['status' => 'NOPE'])->assertSessionHas('error');

        Http::assertSent(fn (Request $request) => $request->url() === 'http://repair.test/tickets/7/status' && $request['status'] === 'REPAIRING');
        Http::assertSent(fn (Request $request) => $request->url() === 'http://repair.test/tickets/7/cancel');
    }

    /**
     * Repair từ chối hủy thì lý do hiện lại cho nhân viên.
     */
    public function test_cancel_rejection_is_shown(): void
    {
        Http::fake(['repair.test/*' => Http::response(['message' => 'Phiếu đã vào quy trình sửa chữa.', 'error_code' => 'cancel_not_allowed'], 409)]);

        $this->actingAs($this->makeUser('ADMIN'))->post('/tickets/7/cancel')->assertSessionHas('error', 'Phiếu đã vào quy trình sửa chữa.');
    }

    /**
     * Đối soát gọi Repair với older_than=0 và hiện kết quả.
     */
    public function test_reconcile(): void
    {
        Http::fake(['repair.test/*' => Http::response(['data' => ['checked' => 2, 'received' => 1, 'failed' => 1, 'pending' => 0]])]);

        $this->actingAs($this->makeUser('ADMIN'))->post('/tickets/reconcile')
            ->assertSessionHas('success', 'Đã đối soát 2 phiếu: 1 tiếp nhận, 1 thất bại, 0 vẫn chờ.');

        Http::assertSent(fn (Request $request) => $request['older_than'] === 0);
    }

    /**
     * Trang kho.
     */
    public function test_inventory_page(): void
    {
        Http::fake(['inventory.test/*' => Http::response(['data' => [['id' => 4, 'name' => 'Camera', 'available' => 0]]])]);

        $this->actingAs($this->makeUser())->get('/inventory')->assertOk()->assertSee('Camera');
    }
}
