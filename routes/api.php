<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OpsController;
use App\Http\Controllers\Api\RazorpayWebhookController;
use App\Http\Controllers\Api\StayController;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureTenantLinked;
use Illuminate\Support\Facades\Route;

/*
 | BizStay Flutter API (Sanctum tokens).
 | Login is role-agnostic: response carries role = admin|manager|staff|tenant.
 |
 |  - Shared endpoints (me, invoices, complaints) scope tenants to their own
 |    guest record automatically.
 |  - Staff endpoints (dashboard, rooms, guests, bookings, leads, collect)
 |    are refused for tenant tokens.
 */
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::post('/webhooks/razorpay', RazorpayWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.razorpay');

Route::middleware(['auth:sanctum', EnsureTenantLinked::class])->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/invoices', [BillingController::class, 'invoices']);
    Route::post('/invoices/{invoice}/pay-link', [BillingController::class, 'payLink'])->middleware('throttle:10,1');
    Route::get('/me/stay', [BillingController::class, 'myStay']);

    Route::get('/complaints', [OpsController::class, 'complaints']);
    Route::post('/complaints', [OpsController::class, 'raiseComplaint'])->middleware('throttle:20,1');

    Route::middleware(EnsureStaff::class)->group(function (): void {
        Route::get('/rooms', [StayController::class, 'rooms']);
        Route::get('/guests', [StayController::class, 'guests']);
        Route::get('/bookings', [StayController::class, 'bookings']);

        Route::post('/invoices/{invoice}/collect', [BillingController::class, 'collect']);

        Route::get('/dashboard', DashboardController::class);
        Route::get('/inquiries', [OpsController::class, 'inquiries']);
        Route::get('/visitors', [OpsController::class, 'visitors']);
    });
});
