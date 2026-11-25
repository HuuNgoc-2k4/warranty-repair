<?php

namespace Tests;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeInventory;

abstract class TestCase extends BaseTestCase
{
    protected FakeInventory $inventory;

    /**
     * @return array<string, string>
     */
    protected function serviceHeaders(array $extra = []): array
    {
        return ['X-Service-Key' => 'test-key'] + $extra;
    }

    /**
     * Giả Warranty (SN-OK còn hạn, SN-OLD hết hạn, SN-DOWN không trả lời, còn lại 404) và Inventory giả.
     *
     * @return FakeInventory
     */
    protected function fakeServices(): FakeInventory
    {
        $this->inventory = new FakeInventory();
        Http::fake([
            'warranty.test/*' => function (Request $request) {
                $serial = basename(parse_url($request->url(), PHP_URL_PATH));
                if ($serial === 'SN-DOWN') {
                    throw new ConnectionException('timeout');
                }
                if (!in_array($serial, ['SN-OK', 'SN-OLD'], true)) {
                    return Http::response(['message' => 'Không tìm thấy thiết bị theo serial.', 'error_code' => 'device_not_found'], 404);
                }

                return Http::response(['data' => ['serial' => $serial, 'valid' => $serial === 'SN-OK']]);
            },
            'inventory.test/*' => fn (Request $request) => ($this->inventory)($request)
        ]);

        return $this->inventory;
    }

    /**
     * @param array $overrides
     * @return array
     */
    protected function ticketPayload(array $overrides = []): array
    {
        return $overrides + [
            'serial' => 'SN-OK',
            'customer' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'issue' => 'Màn hình bị sọc',
            'part_id' => 1,
            'qty' => 1
        ];
    }

    /**
     * Tạo phiếu qua API với Idempotency-Key cho trước.
     *
     * @param string $key
     * @param array $overrides
     * @param array $headers
     * @return \Illuminate\Testing\TestResponse
     */
    protected function createTicket(string $key = 'key-1', array $overrides = [], array $headers = [])
    {
        return $this->postJson('/tickets', $this->ticketPayload($overrides), $this->serviceHeaders(['Idempotency-Key' => $key] + $headers));
    }

    /**
     * Dựng phiếu thẳng vào DB (không qua API) ở trạng thái mong muốn, kèm lượt giữ tương ứng ở Inventory giả.
     *
     * @param string $status
     * @return Ticket
     */
    protected function makeTicket(string $status = 'RECEIVED'): Ticket
    {
        $ticket = Ticket::query()->create($this->ticketPayload() + [
            'in_warranty' => true,
            'status' => $status,
            'idempotency_key' => 'seed-'.uniqid(),
            'request_hash' => 'x'
        ]);
        if ($status !== 'PENDING' && isset($this->inventory)) {
            $this->inventory->reservations['T'.$ticket->id] = 'RESERVED';
        }

        return $ticket;
    }
}
