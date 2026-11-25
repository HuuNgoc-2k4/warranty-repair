<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case RESERVED = 'RESERVED';
    case RELEASED = 'RELEASED';
    case CONSUMED = 'CONSUMED';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::RESERVED => 'Đã giữ',
            self::RELEASED => 'Đã trả lại kho',
            self::CONSUMED => 'Đã sử dụng',
        };
    }
}
