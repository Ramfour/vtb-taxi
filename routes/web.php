<?php

use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManagerPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('resolve.actor')->group(function () {
    Route::prefix('employee')
        ->middleware('role:employee,manager,admin')
        ->group(function () {
            Route::get('/requests', [EmployeePortalController::class, 'index'])->name('employee.requests.index');
            Route::post('/requests', [EmployeePortalController::class, 'store'])->name('employee.requests.store');
            Route::patch('/requests/{tempRequest}/cancel', [EmployeePortalController::class, 'cancel'])->name('employee.requests.cancel');
        });

    Route::prefix('manager')
        ->middleware('role:manager,admin')
        ->group(function () {
            Route::get('/requests', [ManagerPortalController::class, 'index'])->name('manager.requests.index');
            Route::patch('/requests/{tempRequest}/review', [ManagerPortalController::class, 'review'])->name('manager.requests.review');
            Route::post('/requests/finalize', [ManagerPortalController::class, 'finalize'])->name('manager.requests.finalize');
        });
});
