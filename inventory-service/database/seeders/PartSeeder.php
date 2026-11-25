<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    /**
     * Linh kiện mẫu; "Mainboard" chỉ còn 1 để thử tranh chấp linh kiện cuối.
     * Chỉ thêm linh kiện còn thiếu, không ghi đè tồn kho đang có (seed lại khi khởi động lại không làm lệch kho so với các lượt giữ).
     *
     * @return void
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Màn hình', 'available' => 10],
            ['name' => 'Pin', 'available' => 5],
            ['name' => 'Mainboard', 'available' => 1],
            ['name' => 'Camera', 'available' => 0],
        ] as $part) {
            Part::query()->firstOrCreate(['name' => $part['name']], ['available' => $part['available']]);
        }
    }
}
