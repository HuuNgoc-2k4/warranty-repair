<?php

namespace Database\Seeders;

use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    /**
     * Thiết bị mẫu: ngày mua tính lùi từ hôm nay để dữ liệu còn đúng khi chạy lại sau này.
     *
     * @return void
     */
    public function run(): void
    {
        foreach ([
            ['serial' => 'SN-LAP-001', 'model' => 'Laptop Dell XPS 13', 'months_ago' => 6, 'warranty_months' => 24],
            ['serial' => 'SN-PHN-002', 'model' => 'iPhone 15', 'months_ago' => 10, 'warranty_months' => 12],
            ['serial' => 'SN-TAB-003', 'model' => 'iPad Air', 'months_ago' => 30, 'warranty_months' => 12],
            ['serial' => 'SN-LAP-004', 'model' => 'MacBook Air M2', 'months_ago' => 14, 'warranty_months' => 12],
            ['serial' => 'SN-TV-005', 'model' => 'Samsung QLED 55"', 'months_ago' => 1, 'warranty_months' => 36]
        ] as $device) {
            Device::query()->updateOrCreate(['serial' => $device['serial']], [
                'model' => $device['model'],
                'purchase_date' => now()->subMonths($device['months_ago'])->toDateString(),
                'warranty_months' => $device['warranty_months']
            ]);
        }
    }
}
