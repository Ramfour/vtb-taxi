<?php

use App\Http\Controllers\BotAuthController;
use App\Http\Controllers\EmployeeRequestController;
use App\Http\Controllers\ManagerRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('resolve.actor')->group(function () {
    Route::prefix('employee')
        ->middleware('role:employee,manager,admin')
        ->group(function () {
            Route::get('/requests', [EmployeeRequestController::class, 'index']);
            Route::post('/requests', [EmployeeRequestController::class, 'store']);
            Route::patch('/requests/{tempRequest}/cancel', [EmployeeRequestController::class, 'cancel']);
        });

    Route::prefix('manager')
        ->middleware('role:manager,admin')
        ->group(function () {
            Route::get('/requests', [ManagerRequestController::class, 'index']);
            Route::patch('/requests/{tempRequest}/review', [ManagerRequestController::class, 'review']);
            Route::post('/requests/finalize', [ManagerRequestController::class, 'finalize']);
        });
});

Route::prefix('bot')->middleware('bot.auth')->group(function () {
    Route::post('/auth/link', [BotAuthController::class, 'linkTelegram']);

    Route::middleware('role:employee,manager,admin')->group(function () {
        Route::get('/me', [BotAuthController::class, 'me']);
        Route::post('/auth/unlink', [BotAuthController::class, 'unlinkTelegram']);

        Route::prefix('employee')->group(function () {
            Route::get('/requests', [EmployeeRequestController::class, 'index']);
            Route::post('/requests', [EmployeeRequestController::class, 'store']);
            Route::patch('/requests/{tempRequest}/cancel', [EmployeeRequestController::class, 'cancel']);
        });
    });

    Route::prefix('manager')->middleware('role:manager,admin')->group(function () {
        Route::get('/requests', [ManagerRequestController::class, 'index']);
        Route::patch('/requests/{tempRequest}/review', [ManagerRequestController::class, 'review']);
        Route::post('/requests/finalize', [ManagerRequestController::class, 'finalize']);
    });
});
