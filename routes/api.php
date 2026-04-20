<?php

use App\Http\Controllers\Api\V1\CheckController;
use App\Http\Controllers\Api\V1\MonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'throttle:api'])
    ->group(function () {
        Route::apiResource('monitors', MonitorController::class);
        Route::get('monitors/{monitor}/checks', [CheckController::class, 'index'])
            ->name('monitors.checks');
    });
