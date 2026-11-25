<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServiceKey
{
    /**
     * Chỉ cho phép request nội bộ mang đúng X-Service-Key.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) config('services.service_key');
        if ($key === '' || !hash_equals($key, (string) $request->header('X-Service-Key'))) {
            return response()->json(['message' => 'Unauthorized service call.', 'error_code' => 'unauthorized'], 401);
        }

        return $next($request);
    }
}
