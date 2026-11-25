<?php

namespace App\Exceptions;

use Exception;

/**
 * Lỗi khi gọi dịch vụ phía sau gateway; message đã là tiếng Việt để hiển thị cho nhân viên.
 */
class ServiceException extends Exception
{
    public const UNAVAILABLE = 'service_unavailable';

    /**
     * @param string $message
     * @param string $errorCode Mã lỗi do dịch vụ trả về, hoặc service_unavailable khi không kết nối được
     * @param int $status
     */
    public function __construct(string $message, protected string $errorCode = 'service_error', protected int $status = 500)
    {
        parent::__construct($message);
    }

    /**
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
