<?php

namespace Tests\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Inventory giả trong bộ nhớ. Hành vi mỗi lần gọi lấy từ hàng đợi theo loại lệnh:
 * ok | out_of_stock | error503 | timeout (mất kết nối, chưa ghi) | commit_timeout (ghi xong rồi mới mất kết nối).
 */
class FakeInventory
{
    /** @var array<string, string> ref => RESERVED|RELEASED|CONSUMED */
    public array $reservations = [];

    /** @var array<int, string> Mọi request đã nhận, dạng "METHOD /path" */
    public array $calls = [];

    /** @var array<string, array<int, string>> Hàng đợi hành vi theo lệnh: reserve|release|consume */
    public array $queues = ['reserve' => [], 'release' => [], 'consume' => []];

    public bool $lookupDown = false;

    /**
     * @param Request $request
     * @return mixed
     * @throws ConnectionException
     */
    public function __invoke(Request $request): mixed
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        $this->calls[] = $request->method().' '.$path;

        if ($request->method() === 'GET') {
            return $this->lookup(basename($path));
        }
        if ($path === '/reservations') {
            return $this->reserve($request->data());
        }

        [, , $ref, $action] = explode('/', $path);

        return $this->change($action, $ref);
    }

    /**
     * @param string $command
     * @return int
     */
    public function countCalls(string $command): int
    {
        return count(array_filter($this->calls, fn (string $call) => $call === $command));
    }

    /**
     * @param array $data
     * @return mixed
     * @throws ConnectionException
     */
    protected function reserve(array $data): mixed
    {
        $behavior = array_shift($this->queues['reserve']) ?? 'ok';
        if ($behavior === 'out_of_stock') {
            return Http::response(['message' => 'Không đủ linh kiện trong kho.', 'error_code' => 'out_of_stock'], 409);
        }
        if ($behavior === 'error503') {
            return Http::response(['message' => 'Simulated failure.', 'error_code' => 'simulated_failure'], 503);
        }
        if ($behavior === 'timeout') {
            throw new ConnectionException('timeout');
        }

        $this->reservations[$data['ref']] ??= 'RESERVED';
        if ($behavior === 'commit_timeout') {
            throw new ConnectionException('timeout');
        }

        return Http::response(['data' => ['ref' => $data['ref'], 'status' => $this->reservations[$data['ref']]]], 201);
    }

    /**
     * @param string $ref
     * @return mixed
     * @throws ConnectionException
     */
    protected function lookup(string $ref): mixed
    {
        if ($this->lookupDown) {
            throw new ConnectionException('timeout');
        }
        if (!isset($this->reservations[$ref])) {
            return Http::response(['message' => 'Không tìm thấy lượt giữ linh kiện.', 'error_code' => 'reservation_not_found'], 404);
        }

        return Http::response(['data' => ['ref' => $ref, 'status' => $this->reservations[$ref]]]);
    }

    /**
     * @param string $action release|consume
     * @param string $ref
     * @return mixed
     * @throws ConnectionException
     */
    protected function change(string $action, string $ref): mixed
    {
        $behavior = array_shift($this->queues[$action]) ?? 'ok';
        if ($behavior === 'timeout') {
            throw new ConnectionException('timeout');
        }

        $target = $action === 'release' ? 'RELEASED' : 'CONSUMED';
        $this->reservations[$ref] = $target;
        if ($behavior === 'commit_timeout') {
            throw new ConnectionException('timeout');
        }

        return Http::response(['data' => ['ref' => $ref, 'status' => $target]]);
    }
}
