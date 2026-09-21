<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Bed;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\LeaveLog;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Observers\BedObserver;
use App\Observers\BookingObserver;
use App\Observers\InvoiceObserver;
use App\Observers\LeaveLogObserver;
use App\Observers\MeterReadingObserver;
use App\Observers\PaymentObserver;
use App\Services\BedAllocationService;
use App\Services\CheckoutSettlementService;
use App\Services\InvoiceService;
use App\Services\MeterReadingService;
use App\Services\ProrationEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Domain services are stateless; sharing one instance keeps the
        // container graph shallow and predictable.
        $this->app->singleton(ProrationEngine::class);
        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(MeterReadingService::class);
        $this->app->singleton(BedAllocationService::class);
        $this->app->singleton(CheckoutSettlementService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ------------------------------------------------------------------
        // State machines and ledger integrity. Business rules live here, not in
        // controllers or Filament actions, so no caller can bypass them.
        // ------------------------------------------------------------------
        Bed::observe(BedObserver::class);
        Booking::observe(BookingObserver::class);
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);
        MeterReading::observe(MeterReadingObserver::class);
        LeaveLog::observe(LeaveLogObserver::class);

        // Fail loudly when a mass assignment silently drops an attribute
        // (catches typos in fillable / form fields before they reach users).
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}

