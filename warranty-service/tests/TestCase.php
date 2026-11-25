<?php

namespace Tests;

use App\Models\Device;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return array<string, string>
     */
    protected function serviceHeaders(): array
    {
        return ['X-Service-Key' => 'test-key'];
    }

    /**
     * @param array $attributes
     * @return Device
     */
    protected function makeDevice(array $attributes = []): Device
    {
        return Device::query()->create($attributes + [
            'serial' => 'SN-TEST-001',
            'model' => 'Test device',
            'purchase_date' => now()->subMonths(1)->toDateString(),
            'warranty_months' => 12
        ]);
    }
}
