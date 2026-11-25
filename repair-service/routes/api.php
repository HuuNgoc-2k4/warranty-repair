<?php

use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('service.key')->group(function () {
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::post('/tickets/reconcile', [TicketController::class, 'reconcile']);
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id');
    Route::post('/tickets/{id}/status', [TicketController::class, 'changeStatus'])->whereNumber('id');
    Route::post('/tickets/{id}/cancel', [TicketController::class, 'cancel'])->whereNumber('id');
});
