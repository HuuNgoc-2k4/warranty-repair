<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case STAFF = 'STAFF';
    case TECHNICIAN = 'TECHNICIAN';

    public const ABILITY_CREATE_TICKET = 'tickets.create';

    public const ABILITY_CANCEL_TICKET = 'tickets.cancel';

    public const ABILITY_CHANGE_STATUS = 'tickets.status';

    public const ABILITY_RECONCILE = 'tickets.reconcile';

    /**
     * Xem phiếu và kho chỉ cần đăng nhập nên không nằm trong danh sách quyền này.
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [
            self::ABILITY_CREATE_TICKET,
            self::ABILITY_CANCEL_TICKET,
            self::ABILITY_CHANGE_STATUS,
            self::ABILITY_RECONCILE
        ];
    }

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Quản trị',
            self::STAFF => 'Tiếp nhận',
            self::TECHNICIAN => 'Kỹ thuật'
        };
    }

    /**
     * @param string $ability
     * @return bool
     */
    public function allows(string $ability): bool
    {
        return match ($this) {
            self::ADMIN => true,
            self::STAFF => in_array($ability, [self::ABILITY_CREATE_TICKET, self::ABILITY_CANCEL_TICKET], true),
            self::TECHNICIAN => $ability === self::ABILITY_CHANGE_STATUS
        };
    }
}
