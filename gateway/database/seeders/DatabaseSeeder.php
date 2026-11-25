<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Tài khoản mẫu cho từng vai trò (mật khẩu: password).
     *
     * @return void
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Quản trị viên', 'email' => 'admin@gmail.com', 'role' => UserRole::ADMIN],
            ['name' => 'Nhân viên tiếp nhận 1', 'email' => 'staff1@gmail.com', 'role' => UserRole::STAFF],
            ['name' => 'Nhân viên tiếp nhận 2', 'email' => 'staff2@gmail.com', 'role' => UserRole::STAFF],
            ['name' => 'Kỹ thuật viên', 'email' => 'technician1@gmail.com', 'role' => UserRole::TECHNICIAN]
        ] as $user) {
            User::query()->updateOrCreate(['email' => $user['email']], [
                'name' => $user['name'],
                'password' => 'password',
                'role' => $user['role']->value
            ]);
        }
    }
}
