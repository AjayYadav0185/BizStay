<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OpsController;
use App\Http\Controllers\Api\StayController;
use Illuminate\Support\Facades\Route;

/*
 | BizStay Flutter API (Sanctum tokens).
 | Login is role-agnostic: response carries role = manager|tenant.
 | Tenant-scoped endpoints filter by users.guest_id automatically.
 */
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', DashboardController::class);

    Route::get('/rooms', [StayController::class, 'rooms']);
    Route::get('/guests', [StayController::class, 'guests']);
    Route::get('/bookings', [StayController::class, 'bookings']);

    Route::get('/invoices', [BillingController::class, 'invoices']);
    Route::post('/invoices/{invoice}/collect', [BillingController::class, 'collect']);
    Route::get('/me/stay', [BillingController::class, 'myStay']);

    Route::get('/complaints', [OpsController::class, 'complaints']);
    Route::post('/complaints', [OpsController::class, 'raiseComplaint']);
    Route::get('/inquiries', [OpsController::class, 'inquiries']);
});
