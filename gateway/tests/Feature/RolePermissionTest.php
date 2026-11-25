<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Giả cả ba dịch vụ; Repair trả cùng một phiếu cho mọi lệnh ghi và kết quả đối soát cho /tickets/reconcile.
     *
     * @return void
     */
    protected function fakeAllServices(): void
    {
        Http::fake([
            'warranty.test/*' => Http::response(['data' => []]),
            'inventory.test/*' => Http::response(['data' => []]),
            'repair.test/tickets/reconcile' => Http::response(['data' => ['checked' => 0, 'received' => 0, 'failed' => 0, 'pending' => 0]]),
            'repair.test/*' => Http::response(['data' => $this->ticketData()], 201)
        ]);
    }

    /**
     * Ai đăng nhập cũng xem được danh sách phiếu và kho.
     */
    public function test_every_role_can_view_tickets_and_inventory(): void
    {
        Http::fake([
            'repair.test/tickets/7' => Http::response(['data' => $this->ticketData()]),
            'repair.test/tickets' => Http::response(['data' => [$this->ticketData()]]),
            'inventory.test/*' => Http::response(['data' => []])
        ]);

        foreach (UserRole::cases() as $role) {
            $this->actingAs($this->makeUser($role->value));
            $this->get('/tickets')->assertOk();
            $this->get('/tickets/7')->assertOk();
            $this->get('/inventory')->assertOk();
            auth()->logout();
            User::query()->delete();
        }
    }

    /**
     * Nhân viên tiếp nhận tạo và hủy được phiếu nhưng không đổi trạng thái sửa chữa, không đối soát.
     */
    public function test_staff_permissions(): void
    {
        $this->fakeAllServices();
        $this->actingAs($this->makeUser('STAFF'));

        $this->get('/tickets/create')->assertOk();
        $this->post('/tickets', $this->formData())->assertRedirect(route('tickets.show', 7));
        $this->post('/tickets/7/cancel')->assertSessionHas('success');
        $this->post('/tickets/7/status', ['status' => 'REPAIRING'])->assertForbidden();
        $this->post('/tickets/reconcile')->assertForbidden();
    }

    /**
     * Kỹ thuật viên đổi trạng thái được nhưng không tạo, không hủy, không đối soát.
     */
    public function test_technician_permissions(): void
    {
        Http::fake(['*' => Http::response(['data' => $this->ticketData()])]);
        $this->actingAs($this->makeUser('TECHNICIAN'));

        $this->post('/tickets/7/status', ['status' => 'REPAIRING'])->assertSessionHas('success');
        $this->get('/tickets/create')->assertForbidden();
        $this->post('/tickets', $this->formData())->assertForbidden();
        $this->post('/tickets/7/cancel')->assertForbidden();
        $this->post('/tickets/reconcile')->assertForbidden();
    }

    /**
     * Quản trị viên làm được mọi thao tác.
     */
    public function test_admin_can_do_everything(): void
    {
        $this->fakeAllServices();
        $this->actingAs($this->makeUser('ADMIN'));

        $this->get('/tickets/create')->assertOk();
        $this->post('/tickets/7/status', ['status' => 'REPAIRING'])->assertSessionHas('success');
        $this->post('/tickets/7/cancel')->assertSessionHas('success');
        $this->post('/tickets/reconcile')->assertSessionHas('success');
    }

    /**
     * Bị chặn bằng 403 thì không có request nào gọi sang dịch vụ phía sau.
     */
    public function test_forbidden_action_makes_no_remote_call(): void
    {
        Http::fake();
        $this->actingAs($this->makeUser('TECHNICIAN'))->post('/tickets/7/cancel')->assertForbidden();

        Http::assertNothingSent();
    }

    /**
     * Nút chỉ hiện khi vai trò có quyền.
     */
    public function test_buttons_follow_role(): void
    {
        Http::fake(['repair.test/*' => Http::response(['data' => [$this->ticketData()]])]);

        $this->actingAs($this->makeUser('STAFF'))->get('/tickets')->assertSee('Tạo phiếu')->assertDontSee('Đối soát phiếu chờ');
        auth()->logout();
        User::query()->delete();

        $this->actingAs($this->makeUser('TECHNICIAN'))->get('/tickets')->assertDontSee('Tạo phiếu')->assertDontSee('Đối soát phiếu chờ');
        auth()->logout();
        User::query()->delete();

        $this->actingAs($this->makeUser('ADMIN'))->get('/tickets')->assertSee('Tạo phiếu')->assertSee('Đối soát phiếu chờ');
    }

    /**
     * Trang chi tiết: kỹ thuật viên thấy nút sửa nhưng không thấy nút hủy; nhân viên tiếp nhận ngược lại.
     */
    public function test_ticket_detail_actions_follow_role(): void
    {
        Http::fake(['repair.test/*' => Http::response(['data' => $this->ticketData()])]);

        $this->actingAs($this->makeUser('TECHNICIAN'))->get('/tickets/7')->assertSee('Bắt đầu sửa')->assertDontSee('Hủy phiếu');
        auth()->logout();
        User::query()->delete();

        $this->actingAs($this->makeUser('STAFF'))->get('/tickets/7')->assertDontSee('Bắt đầu sửa')->assertSee('Hủy phiếu');
    }

    /**
     * Vai trò lạ trong DB không có quyền nào.
     */
    public function test_unknown_role_has_no_abilities(): void
    {
        Http::fake();
        $this->actingAs($this->makeUser('HACKER'))->get('/tickets/create')->assertForbidden();
    }
}
