<?php

use App\Models\Property;
use App\Services\InvoiceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Automation
|--------------------------------------------------------------------------
| The ledger maintains itself: overdue invoices are flagged every night and
| invoices are raised on the billing cycle anchor day configured on the
| building profile. Both tasks are idempotent (unique booking+cycle index and
| a status guard), so a re-run can never double-bill a guest.
*/
Schedule::call(function (): void {
    app(InvoiceService::class)->markOverdue();
})->dailyAt('00:30')->name('bizstay:flag-overdue-invoices')->withoutOverlapping();

Schedule::call(function (): void {
    $cycleStartDay = Property::current()?->billing_cycle_start_day ?? 1;

    if ((int) now()->day === (int) $cycleStartDay) {
        app(InvoiceService::class)->generateForCycle();
    }
})->dailyAt('02:00')->name('bizstay:generate-monthly-invoices')->withoutOverlapping();

