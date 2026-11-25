<?php

use App\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Route;

Route::middleware('service.key')->group(function () {
    Route::get('/devices', [WarrantyController::class, 'index']);
    Route::get('/warranty/{serial}', [WarrantyController::class, 'show']);
});
