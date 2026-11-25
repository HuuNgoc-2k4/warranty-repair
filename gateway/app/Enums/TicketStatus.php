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

    /**
     * Class màu của huy hiệu trạng thái.
     *
     * @return string
     */
    public function badge(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-100 text-amber-800',
            self::RECEIVED => 'bg-blue-100 text-blue-800',
            self::REPAIRING => 'bg-indigo-100 text-indigo-800',
            self::DONE => 'bg-emerald-100 text-emerald-800',
            self::RETURNED => 'bg-gray-100 text-gray-700',
            self::CANCELLED => 'bg-gray-200 text-gray-600',
            self::FAILED => 'bg-red-100 text-red-800'
        };
    }

    /**
     * Bước tiếp theo của vòng đời và nhãn nút bấm; null khi không còn bước nào.
     *
     * @return array{status: string, label: string}|null
     */
    public function next(): ?array
    {
        return match ($this) {
            self::RECEIVED => ['status' => self::REPAIRING->value, 'label' => 'Bắt đầu sửa'],
            self::REPAIRING => ['status' => self::DONE->value, 'label' => 'Đánh dấu sửa xong'],
            self::DONE => ['status' => self::RETURNED->value, 'label' => 'Trả máy cho khách'],
            default => null
        };
    }
}
