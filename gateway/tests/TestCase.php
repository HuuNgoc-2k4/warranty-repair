<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param string $role ADMIN | STAFF | TECHNICIAN
     * @return User
     */
    protected function makeUser(string $role = 'STAFF'): User
    {
        return User::query()->create([
            'name' => 'Người dùng '.$role,
            'email' => strtolower($role).'@gmail.com',
            'password' => 'password',
            'role' => $role
        ]);
    }

    /**
     * @param array $overrides
     * @return array
     */
    protected function ticketData(array $overrides = []): array
    {
        return $overrides + [
            'id' => 7,
            'serial' => 'SN-OK',
            'customer' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'issue' => 'Màn hình sọc',
            'in_warranty' => true,
            'status' => 'RECEIVED',
            'part_id' => 1,
            'qty' => 1,
            'failure_reason' => null,
            'events' => [['to_status' => 'RECEIVED', 'note' => 'Đã giữ linh kiện', 'created_at' => '2026-11-26T01:00:00Z']]
        ];
    }

    /**
     * @param array $overrides
     * @return array
     */
    protected function formData(array $overrides = []): array
    {
        return $overrides + [
            'idempotency_key' => 'key-123',
            'serial' => 'SN-OK',
            'customer' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'issue' => 'Màn hình sọc',
            'part_id' => 1,
            'qty' => 1
        ];
    }
}
