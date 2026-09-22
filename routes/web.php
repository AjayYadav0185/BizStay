<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Printable invoice / payment receipt.
|
| Opened from the admin (Filament "Print" action) — auth is enforced through
| the panel's own Authenticate middleware, which redirects guests to the
| Filament login instead of the (nonexistent) default login route.
*/
Route::get('/invoices/{invoice}/print', function (Invoice $invoice) {
    $invoice->loadMissing(['booking.guest', 'booking.bed.room', 'payments' => fn ($query) => $query->settled()->with('collectedBy')]);
    $property = \App\Models\Property::current();

    return view('invoices.print', [
        'invoice' => $invoice,
        'property' => $property,
        'payments' => $invoice->payments,
        'balance' => $invoice->balanceDue(),
    ]);
})->middleware(['web', \Filament\Http\Middleware\Authenticate::class])
    ->name('invoices.print')
    ->scopeBindings();
