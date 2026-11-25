<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Tài khoản nhân viên mẫu (mật khẩu: password).
     *
     * @return void
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Nhân viên 1', 'email' => 'staff1@wr.test'],
            ['name' => 'Nhân viên 2', 'email' => 'staff2@wr.test']
        ] as $staff) {
            User::query()->updateOrCreate(['email' => $staff['email']], ['name' => $staff['name'], 'password' => 'password']);
        }
    }
}
