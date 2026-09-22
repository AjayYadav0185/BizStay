<?php

use App\Models\Property;
use App\Services\InvoiceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

/*
| Late-fee engine + overdue reminders.
|
| Fees: overdue invoices (balance > 0) get the property's late_fee_percent
| added as a visible other_charges line, guarded by late_fee_charged_on so a
| re-run never double-charges.
|
| Reminders: every overdue invoice not reminded in the last 3 days is logged
| as a touchpoint (last_reminded_at + structured log entry) — the SMS/WhatsApp
| sender can later hook into the same spot.
*/
Schedule::call(function (): void {
    $result = app(InvoiceService::class)->applyLateFees();

    if ($result['charged'] > 0) {
        Log::notice('bizstay.late_fees.charged', $result);
    }
})->dailyAt('06:00')->name('bizstay:apply-late-fees')->withoutOverlapping();

Schedule::call(function (): void {
    $reminded = 0;

    foreach (app(InvoiceService::class)->invoicesDueForReminder() as $invoice) {
        app(InvoiceService::class)->markReminded($invoice, 'scheduled');
        $reminded++;
    }

    if ($reminded > 0) {
        Log::notice('bizstay.overdue_reminders.sent', ['count' => $reminded]);
    }
})->dailyAt('10:00')->name('bizstay:remind-overdue-invoices')->withoutOverlapping();

