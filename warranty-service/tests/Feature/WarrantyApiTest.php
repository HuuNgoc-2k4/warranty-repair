<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Thiếu hoặc sai service key thì bị từ chối.
     */
    public function test_requires_service_key(): void
    {
        $this->getJson('/warranty/SN-TEST-001')->assertStatus(401)->assertJsonPath('error_code', 'unauthorized');
        $this->getJson('/warranty/SN-TEST-001', ['X-Service-Key' => 'wrong'])->assertStatus(401);
    }

    /**
     * Thiết bị còn hạn.
     */
    public function test_device_within_warranty_is_valid(): void
    {
        $this->makeDevice();

        $this->getJson('/warranty/SN-TEST-001', $this->serviceHeaders())
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.serial', 'SN-TEST-001')
            ->assertJsonPath('data.expires_at', now()->subMonths(1)->addMonthsNoOverflow(12)->toDateString());
    }

    /**
     * Thiết bị hết hạn vẫn tra cứu được nhưng valid=false.
     */
    public function test_expired_device_is_not_valid(): void
    {
        $this->makeDevice(['purchase_date' => now()->subMonths(30)->toDateString(), 'warranty_months' => 12]);

        $this->getJson('/warranty/SN-TEST-001', $this->serviceHeaders())
            ->assertOk()
            ->assertJsonPath('data.valid', false);
    }

    /**
     * Đúng ngày hết hạn vẫn còn bảo hành.
     */
    public function test_device_is_valid_on_expiry_day(): void
    {
        $this->makeDevice(['purchase_date' => now()->subMonths(12)->toDateString(), 'warranty_months' => 12]);

        $this->getJson('/warranty/SN-TEST-001', $this->serviceHeaders())->assertJsonPath('data.valid', true);
    }

    /**
     * Serial lạ trả 404 device_not_found.
     */
    public function test_unknown_serial_returns_404(): void
    {
        $this->getJson('/warranty/NOPE', $this->serviceHeaders())
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'device_not_found');
    }

    /**
     * Danh sách thiết bị.
     */
    public function test_lists_devices(): void
    {
        $this->makeDevice();
        $this->makeDevice(['serial' => 'SN-TEST-002']);

        $this->getJson('/devices', $this->serviceHeaders())->assertOk()->assertJsonCount(2, 'data');
    }
}
