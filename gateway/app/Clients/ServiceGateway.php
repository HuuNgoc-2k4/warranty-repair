<?php

namespace App\Clients;

use App\Exceptions\ServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ServiceGateway
{
    /**
     * Phải lớn hơn thời gian Repair tối đa mất khi xử lý chưa rõ kết quả (vài lần timeout 3 giây).
     */
    protected const TIMEOUT_SECONDS = 30;

    protected const CONNECT_TIMEOUT_SECONDS = 3;

    /**
     * @return array
     */
    public function devices(): array
    {
        return $this->call('warranty', 'GET', '/devices')['data'];
    }

    /**
     * @return array
     */
    public function parts(): array
    {
        return $this->call('inventory', 'GET', '/parts')['data'];
    }

    /**
     * @param string|null $status
     * @return array
     */
    public function tickets(?string $status): array
    {
        return $this->call('repair', 'GET', '/tickets', array_filter(['status' => $status]))['data'];
    }

    /**
     * @param int $id
     * @return array
     */
    public function ticket(int $id): array
    {
        return $this->call('repair', 'GET', "/tickets/{$id}")['data'];
    }

    /**
     * @param array $data
     * @param string $key Idempotency-Key
     * @param string|null $simulate
     * @return array{status: int, message: string|null, data: array}
     */
    public function createTicket(array $data, string $key, ?string $simulate): array
    {
        return $this->call('repair', 'POST', '/tickets', $data, ['Idempotency-Key' => $key] + $this->simulateHeader($simulate));
    }

    /**
     * @param int $id
     * @param string $status
     * @param string|null $simulate
     * @return array
     */
    public function changeStatus(int $id, string $status, ?string $simulate): array
    {
        return $this->call('repair', 'POST', "/tickets/{$id}/status", ['status' => $status], $this->simulateHeader($simulate))['data'];
    }

    /**
     * @param int $id
     * @param string|null $simulate
     * @return array
     */
    public function cancel(int $id, ?string $simulate): array
    {
        return $this->call('repair', 'POST', "/tickets/{$id}/cancel", [], $this->simulateHeader($simulate))['data'];
    }

    /**
     * Đối soát ngay các phiếu PENDING (older_than=0) thay vì đợi quá hạn.
     *
     * @return array
     */
    public function reconcile(): array
    {
        return $this->call('repair', 'POST', '/tickets/reconcile', ['older_than' => 0])['data'];
    }

    /**
     * @param string|null $simulate
     * @return array<string, string>
     */
    protected function simulateHeader(?string $simulate): array
    {
        return $simulate ? ['X-Simulate' => $simulate] : [];
    }

    /**
     * Gọi một dịch vụ kèm X-Service-Key.
     *
     * @param string $service warranty | repair | inventory
     * @param string $method
     * @param string $path
     * @param array $data
     * @param array $headers
     * @return array{status: int, message: string|null, data: array}
     * @throws ServiceException
     */
    protected function call(string $service, string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $response = Http::baseUrl((string) config("services.{$service}_url"))
                ->withHeaders(['X-Service-Key' => (string) config('services.service_key')] + $headers)
                ->acceptJson()
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException $e) {
            Log::warning("{$service} {$method} {$path} không kết nối được: {$e->getMessage()}");
            throw new ServiceException("Không kết nối được dịch vụ {$service}.", ServiceException::UNAVAILABLE, 503);
        }

        if (!$response->successful()) {
            throw new ServiceException(
                (string) $response->json('message', "Dịch vụ {$service} gặp lỗi."),
                (string) $response->json('error_code', 'service_error'),
                $response->status()
            );
        }

        return [
            'status' => $response->status(),
            'message' => $response->json('message'),
            'data' => (array) $response->json('data', [])
        ];
    }
}
