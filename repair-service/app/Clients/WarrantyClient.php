<?php

namespace App\Clients;

class WarrantyClient extends ServiceClient
{
    /**
     * @return string
     */
    protected function baseUrl(): string
    {
        return (string) config('services.warranty_url');
    }

    /**
     * @return string
     */
    protected function serviceName(): string
    {
        return 'warranty';
    }

    /**
     * Tra cứu bảo hành theo serial (chỉ đọc nên có thể gọi lại an toàn).
     *
     * @param string $serial
     * @return array
     */
    public function check(string $serial): array
    {
        return $this->send('GET', '/warranty/'.rawurlencode($serial));
    }
}
