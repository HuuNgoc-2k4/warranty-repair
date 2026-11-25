<?php

use App\Http\Controllers\PartController;
use App\Http\Controllers\ReservationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['service.key', 'simulate'])->group(function () {
    Route::get('/parts', [PartController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/{ref}', [ReservationController::class, 'show']);
    Route::post('/reservations/{ref}/release', [ReservationController::class, 'release']);
    Route::post('/reservations/{ref}/consume', [ReservationController::class, 'consume']);
});
