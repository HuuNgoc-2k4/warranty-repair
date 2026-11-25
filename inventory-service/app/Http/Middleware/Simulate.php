<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Simulate
{
    /**
     * Mô phỏng lỗi theo header X-Simulate (fail | slow | commit-then-timeout), chỉ khi APP_SIMULATE=true.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('services.simulate')) {
            return $next($request);
        }

        $mode = $request->header('X-Simulate');
        if ($mode === 'fail') {
            return response()->json(['message' => 'Simulated failure.', 'error_code' => 'simulated_failure'], 503);
        }
        if ($mode === 'slow') {
            sleep(5);
        }

        $response = $next($request);

        // Đã ghi DB xong mới chậm: client timeout nhưng dữ liệu vẫn được lưu
        if ($mode === 'commit-then-timeout') {
            sleep(10);
        }

        return $response;
    }
}
