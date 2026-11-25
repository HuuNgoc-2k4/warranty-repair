<?php

namespace App\Clients;

use App\Exceptions\BusinessException;
use App\Exceptions\RemoteUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class ServiceClient
{
    protected const TIMEOUT_SECONDS = 3;

    protected const CONNECT_TIMEOUT_SECONDS = 2;

    /**
     * @return string
     */
    abstract protected function baseUrl(): string;

    /**
     * @return string
     */
    abstract protected function serviceName(): string;

    /**
     * Gọi dịch vụ khác. 4xx nghiệp vụ → BusinessException (kết quả rõ: từ chối);
     * timeout/mất kết nối/5xx/401 → RemoteUnavailableException (kết quả chưa rõ).
     *
     * @param string $method
     * @param string $path
     * @param array $data
     * @param array $headers
     * @return array
     * @throws BusinessException
     * @throws RemoteUnavailableException
     */
    protected function send(string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->withHeaders(['X-Service-Key' => (string) config('services.service_key')] + $headers)
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException $e) {
            Log::warning("{$this->serviceName()} {$method} {$path} không phản hồi: {$e->getMessage()}");
            throw new RemoteUnavailableException($this->serviceName());
        }

        if ($response->successful()) {
            return (array) $response->json('data', []);
        }

        $status = $response->status();
        $isClientError = $status >= 400 && $status < 500 && !in_array($status, [401, 403, 429], true);
        if (!$isClientError) {
            Log::warning("{$this->serviceName()} {$method} {$path} trả {$status}");
            throw new RemoteUnavailableException($this->serviceName());
        }

        throw new BusinessException(
            (string) $response->json('message', 'Dịch vụ từ chối yêu cầu.'),
            (string) $response->json('error_code', 'business_error'),
            $status
        );
    }
}
