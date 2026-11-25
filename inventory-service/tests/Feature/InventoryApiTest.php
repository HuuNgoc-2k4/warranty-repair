<?php

namespace Tests\Feature;

use App\Models\Part;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Thiếu service key thì bị từ chối.
     */
    public function test_requires_service_key(): void
    {
        $this->getJson('/parts')->assertStatus(401);
        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => 1, 'qty' => 1])->assertStatus(401);
    }

    /**
     * Giữ linh kiện trừ kho và trả 201.
     */
    public function test_reserve_decrements_stock(): void
    {
        $part = $this->makePart(5);

        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 2], $this->serviceHeaders())
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'RESERVED');

        $this->assertSame(3, $part->fresh()->available);
    }

    /**
     * Gửi lại cùng ref không trừ kho lần nữa.
     */
    public function test_reserve_is_idempotent_by_ref(): void
    {
        $part = $this->makePart(5);
        $body = ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 2];

        $this->postJson('/reservations', $body, $this->serviceHeaders())->assertStatus(201);
        $this->postJson('/reservations', $body, $this->serviceHeaders())->assertStatus(200)->assertJsonPath('data.ref', 'T1');

        $this->assertSame(3, $part->fresh()->available);
        $this->assertDatabaseCount('reservations', 1);
    }

    /**
     * Không đủ hàng trả 409 và không đổi kho, không tạo lượt giữ.
     */
    public function test_reserve_out_of_stock(): void
    {
        $part = $this->makePart(1);

        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 2], $this->serviceHeaders())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'out_of_stock');

        $this->assertSame(1, $part->fresh()->available);
        $this->assertDatabaseCount('reservations', 0);
    }

    /**
     * Linh kiện cuối cùng chỉ giữ được một lần; ref khác bị từ chối.
     */
    public function test_last_part_goes_to_first_reservation_only(): void
    {
        $part = $this->makePart(1);

        $this->postJson('/reservations', ['ref' => 'A', 'part_id' => $part->id, 'qty' => 1], $this->serviceHeaders())->assertStatus(201);
        $this->postJson('/reservations', ['ref' => 'B', 'part_id' => $part->id, 'qty' => 1], $this->serviceHeaders())->assertStatus(409);

        $this->assertSame(0, $part->fresh()->available);
    }

    /**
     * Linh kiện không tồn tại và dữ liệu sai.
     */
    public function test_reserve_validation_and_missing_part(): void
    {
        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => 999, 'qty' => 1], $this->serviceHeaders())
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'part_not_found');
        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => 1, 'qty' => 0], $this->serviceHeaders())
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed');
    }

    /**
     * Trả linh kiện cộng lại kho; trả lần hai không cộng thêm.
     */
    public function test_release_restores_stock_once(): void
    {
        $part = $this->makePart(5);
        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 2], $this->serviceHeaders());

        $this->postJson('/reservations/T1/release', [], $this->serviceHeaders())->assertOk()->assertJsonPath('data.status', 'RELEASED');
        $this->postJson('/reservations/T1/release', [], $this->serviceHeaders())->assertOk()->assertJsonPath('data.status', 'RELEASED');

        $this->assertSame(5, $part->fresh()->available);
    }

    /**
     * Release lượt không tồn tại trả 404.
     */
    public function test_release_unknown_ref(): void
    {
        $this->postJson('/reservations/NOPE/release', [], $this->serviceHeaders())
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'reservation_not_found');
    }

    /**
     * Đã dùng thì không trả lại được; dùng lần hai trả kết quả cũ.
     */
    public function test_consume_then_release_is_rejected(): void
    {
        $part = $this->makePart(5);
        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 1], $this->serviceHeaders());

        $this->postJson('/reservations/T1/consume', [], $this->serviceHeaders())->assertOk()->assertJsonPath('data.status', 'CONSUMED');
        $this->postJson('/reservations/T1/consume', [], $this->serviceHeaders())->assertOk()->assertJsonPath('data.status', 'CONSUMED');
        $this->postJson('/reservations/T1/release', [], $this->serviceHeaders())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'already_consumed');

        $this->assertSame(4, $part->fresh()->available);
    }

    /**
     * Đã trả thì không dùng được; ref lạ trả 404.
     */
    public function test_consume_released_or_unknown(): void
    {
        $part = $this->makePart(5);
        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 1], $this->serviceHeaders());
        $this->postJson('/reservations/T1/release', [], $this->serviceHeaders());

        $this->postJson('/reservations/T1/consume', [], $this->serviceHeaders())
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'already_released');
        $this->postJson('/reservations/NOPE/consume', [], $this->serviceHeaders())->assertStatus(404);
    }

    /**
     * Tra cứu lượt giữ (dùng khi reserve bị timeout).
     */
    public function test_lookup_reservation(): void
    {
        $part = $this->makePart(5);
        $this->postJson('/reservations', ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 1], $this->serviceHeaders());

        $this->getJson('/reservations/T1', $this->serviceHeaders())->assertOk()->assertJsonPath('data.qty', 1);
        $this->getJson('/reservations/NOPE', $this->serviceHeaders())->assertStatus(404);
    }

    /**
     * X-Simulate: fail trả 503 và không đụng kho.
     */
    public function test_simulate_fail_does_not_touch_stock(): void
    {
        $part = $this->makePart(5);

        $this->postJson(
            '/reservations',
            ['ref' => 'T1', 'part_id' => $part->id, 'qty' => 1],
            $this->serviceHeaders() + ['X-Simulate' => 'fail']
        )->assertStatus(503)->assertJsonPath('error_code', 'simulated_failure');

        $this->assertSame(5, $part->fresh()->available);
        $this->assertDatabaseCount('reservations', 0);
    }

    /**
     * Danh sách linh kiện.
     */
    public function test_lists_parts(): void
    {
        $this->makePart(3);
        $this->makePart(0);

        $this->getJson('/parts', $this->serviceHeaders())->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame(2, Part::query()->count());
    }
}
