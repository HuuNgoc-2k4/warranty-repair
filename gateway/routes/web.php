<?php

use App\Enums\UserRole;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/{id}', [TicketController::class, 'show'])->whereNumber('id')->name('tickets.show');

    Route::middleware('can:'.UserRole::ABILITY_CREATE_TICKET)->group(function () {
        Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    });
    Route::post('/tickets/reconcile', [TicketController::class, 'reconcile'])
        ->middleware('can:'.UserRole::ABILITY_RECONCILE)->name('tickets.reconcile');
    Route::post('/tickets/{id}/status', [TicketController::class, 'changeStatus'])
        ->middleware('can:'.UserRole::ABILITY_CHANGE_STATUS)->whereNumber('id')->name('tickets.status');
    Route::post('/tickets/{id}/cancel', [TicketController::class, 'cancel'])
        ->middleware('can:'.UserRole::ABILITY_CANCEL_TICKET)->whereNumber('id')->name('tickets.cancel');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
});
