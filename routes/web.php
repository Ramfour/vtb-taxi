<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationRegistrationController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\EmployeeAddressController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ManagerInvitationController;
use App\Http\Controllers\ManagerPortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/invite/{token}', [InvitationRegistrationController::class, 'show'])->name('invitation.accept.show');
    Route::post('/invite/{token}', [InvitationRegistrationController::class, 'store'])->name('invitation.accept.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::prefix('employee')
        ->middleware('role:employee,manager,admin')
        ->group(function () {
            Route::get('/requests', [EmployeePortalController::class, 'index'])->name('employee.requests.index');
            Route::post('/requests', [EmployeePortalController::class, 'store'])->name('employee.requests.store');
            Route::patch('/requests/{tempRequest}/cancel', [EmployeePortalController::class, 'cancel'])->name('employee.requests.cancel');
            Route::get('/addresses', [EmployeeAddressController::class, 'index'])->name('employee.addresses.index');
            Route::post('/addresses', [EmployeeAddressController::class, 'store'])->name('employee.addresses.store');
            Route::delete('/addresses/{address}', [EmployeeAddressController::class, 'destroy'])->name('employee.addresses.destroy');
        });

    Route::prefix('manager')
        ->middleware('role:manager,admin')
        ->group(function () {
            Route::get('/requests', [ManagerPortalController::class, 'index'])->name('manager.requests.index');
            Route::patch('/requests/{tempRequest}/review', [ManagerPortalController::class, 'review'])->name('manager.requests.review');
            Route::post('/requests/bulk-approve', [ManagerPortalController::class, 'bulkApprove'])->name('manager.requests.bulk-approve');
            Route::post('/requests/finalize', [ManagerPortalController::class, 'finalize'])->name('manager.requests.finalize');
            Route::post('/requests/export-csv', [ManagerPortalController::class, 'exportCsv'])->name('manager.requests.export-csv');
            Route::delete('/requests/{tempRequest}', [ManagerPortalController::class, 'destroyTemp'])->name('manager.requests.destroy-temp');
            Route::patch('/final-requests/{finalRequest}', [ManagerPortalController::class, 'updateFinal'])->name('manager.requests.update-final');
            Route::delete('/final-requests/{finalRequest}', [ManagerPortalController::class, 'destroyFinal'])->name('manager.requests.destroy-final');
            Route::post('/invitations', [ManagerInvitationController::class, 'store'])->name('manager.invitations.store');
        });
});
