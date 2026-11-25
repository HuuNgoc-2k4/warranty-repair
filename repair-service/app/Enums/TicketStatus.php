<?php

namespace App\Enums;

enum TicketStatus: string
{
    case PENDING = 'PENDING';
    case RECEIVED = 'RECEIVED';
    case REPAIRING = 'REPAIRING';
    case DONE = 'DONE';
    case RETURNED = 'RETURNED';
    case CANCELLED = 'CANCELLED';
    case FAILED = 'FAILED';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Đang xử lý',
            self::RECEIVED => 'Đã tiếp nhận',
            self::REPAIRING => 'Đang sửa',
            self::DONE => 'Sửa xong',
            self::RETURNED => 'Đã trả khách',
            self::CANCELLED => 'Đã hủy',
            self::FAILED => 'Thất bại'
        };
    }
}
