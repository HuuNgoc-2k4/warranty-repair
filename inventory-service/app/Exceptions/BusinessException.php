<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    /**
     * @param string $message Nội dung trả về cho client
     * @param string $errorCode Mã lỗi để client rẽ nhánh
     * @param int $status HTTP status
     */
    public function __construct(string $message, protected string $errorCode = 'business_error', protected int $status = 422)
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
