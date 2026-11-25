<?php

namespace App\Clients;

class InventoryClient extends ServiceClient
{
    /**
     * @return string
     */
    protected function baseUrl(): string
    {
        return (string) config('services.inventory_url');
    }

    /**
     * @return string
     */
    protected function serviceName(): string
    {
        return 'inventory';
    }

    /**
     * Giữ linh kiện; idempotent theo ref nên gửi lại an toàn.
     *
     * @param string $ref
     * @param int $partId
     * @param int $qty
     * @param string|null $simulate Giá trị X-Simulate chuyển tiếp để mô phỏng lỗi
     * @return array
     */
    public function reserve(string $ref, int $partId, int $qty, ?string $simulate = null): array
    {
        return $this->send('POST', '/reservations', ['ref' => $ref, 'part_id' => $partId, 'qty' => $qty], $this->simulateHeader($simulate));
    }

    /**
     * Tra lượt giữ; dùng khi reserve/release chưa rõ kết quả.
     *
     * @param string $ref
     * @return array
     */
    public function find(string $ref): array
    {
        return $this->send('GET', '/reservations/'.rawurlencode($ref));
    }

    /**
     * @param string $ref
     * @param string|null $simulate
     * @return array
     */
    public function release(string $ref, ?string $simulate = null): array
    {
        return $this->send('POST', '/reservations/'.rawurlencode($ref).'/release', [], $this->simulateHeader($simulate));
    }

    /**
     * @param string $ref
     * @param string|null $simulate
     * @return array
     */
    public function consume(string $ref, ?string $simulate = null): array
    {
        return $this->send('POST', '/reservations/'.rawurlencode($ref).'/consume', [], $this->simulateHeader($simulate));
    }

    /**
     * @param string|null $simulate
     * @return array<string, string>
     */
    protected function simulateHeader(?string $simulate): array
    {
        return $simulate ? ['X-Simulate' => $simulate] : [];
    }
}
