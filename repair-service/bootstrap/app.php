<?php

use App\Exceptions\BusinessException;
use App\Exceptions\RemoteUnavailableException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'service.key' => \App\Http\Middleware\ServiceKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => true);
        $exceptions->render(fn (BusinessException $e) => response()->json([
            'message' => $e->getMessage(),
            'error_code' => $e->getErrorCode(),
        ], $e->getStatus()));
        $exceptions->render(fn (RemoteUnavailableException $e) => response()->json([
            'message' => $e->getMessage(),
            'error_code' => $e->getErrorCode(),
        ], 503));
    })->create();
