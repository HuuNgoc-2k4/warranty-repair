<?php

namespace Database\Seeders;

use App\Models\Part;
use Illuminate\Database\Seeder;

class PartSeeder extends Seeder
{
    /**
     * Linh kiện mẫu; "Mainboard" chỉ còn 1 để thử tranh chấp linh kiện cuối.
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
            Part::query()->updateOrCreate(['name' => $part['name']], ['available' => $part['available']]);
        }
    }
}
