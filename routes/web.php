<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PublicStayController;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
| Public page of the building: availability, amenities, enquiry form.
*/
Route::get('/stay', [PublicStayController::class, 'show'])->name('stay.show');
Route::post('/stay/enquire', [PublicStayController::class, 'enquire'])
    ->middleware('throttle:5,1')
    ->name('stay.enquire');

/*
| Documents and outbound actions opened from the admin panel.
|
| Auth is enforced through the panel's own Authenticate middleware, which
| redirects guests to the Filament login and refuses tenant logins.
*/
Route::middleware(['web', Authenticate::class])->group(function (): void {
    Route::get('/invoices/{invoice}/print', [DocumentController::class, 'invoice'])->name('invoices.print');
    Route::get('/invoices/{invoice}/whatsapp', [DocumentController::class, 'whatsappReminder'])->name('invoices.whatsapp');
    Route::get('/guests/{guest}/rent-receipts', [DocumentController::class, 'rentReceipts'])->name('guests.rent-receipts');
    Route::get('/guests/{guest}/whatsapp-welcome', [DocumentController::class, 'whatsappWelcome'])->name('guests.whatsapp-welcome');
    Route::get('/reports/{report}.csv', [DocumentController::class, 'report'])->name('reports.download');
});
