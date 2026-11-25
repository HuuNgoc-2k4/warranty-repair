<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dịch vụ khác không trả lời được (timeout, mất kết nối, 5xx): KẾT QUẢ CHƯA RÕ, không đồng nghĩa với thất bại.
 */
class RemoteUnavailableException extends RuntimeException
{
    /**
     * @param string $service Tên dịch vụ (warranty | inventory)
     */
    public function __construct(protected string $service)
    {
        parent::__construct("Dịch vụ {$service} không phản hồi, chưa rõ kết quả.");
    }

    /**
     * @return string
     */
    public function getService(): string
    {
        return $this->service;
    }

    /**
     * @return string
     */
    public function getErrorCode(): string
    {
        return "{$this->service}_unavailable";
    }
}
