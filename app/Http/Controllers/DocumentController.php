<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\RentReceiptService;
use App\Services\ReportService;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Printable documents and one-click outbound actions opened from the admin
 * panel. Every route here sits behind the panel's Authenticate middleware,
 * which already refuses tenant logins (User::canAccessPanel).
 */
final class DocumentController extends Controller
{
    public function invoice(Invoice $invoice): View
    {
        $invoice->loadMissing(['booking.guest', 'booking.bed.room', 'payments' => fn ($query) => $query->settled()->with('collectedBy')]);
        $property = Property::current();
        $balance = $invoice->balanceDue();

        return view('invoices.print', [
            'invoice' => $invoice,
            'property' => $property,
            'payments' => $invoice->payments,
            'balance' => $balance,
            'upiUri' => $balance > 0 ? $property?->upiPaymentUri($balance, 'Rent '.$invoice->invoice_number) : null,
        ]);
    }

    public function rentReceipts(Request $request, Guest $guest, RentReceiptService $receipts): View
    {
        $fy = (int) $request->integer('fy', RentReceiptService::financialYearOf());

        return view('receipts.hra', [
            'guest' => $guest->loadMissing('currentBooking.bed.room'),
            'property' => Property::current(),
            'fyStart' => $fy,
            'data' => $receipts->forGuest($guest, $fy),
        ]);
    }

    /**
     * Logs the reminder touchpoint, then hands off to WhatsApp.
     */
    public function whatsappReminder(Invoice $invoice, InvoiceService $invoices): RedirectResponse
    {
        $link = WhatsApp::link($invoice->booking?->guest?->phone, WhatsApp::rentReminder($invoice));

        if ($link === null) {
            return back()->with('error', 'This resident has no valid Indian mobile number.');
        }

        $invoices->markReminded($invoice, 'whatsapp');

        return redirect()->away($link);
    }

    public function whatsappWelcome(Guest $guest): RedirectResponse
    {
        $link = WhatsApp::link($guest->phone, WhatsApp::welcome($guest->loadMissing('currentBooking.bed')));

        abort_if($link === null, 422, 'This resident has no valid Indian mobile number.');

        return redirect()->away($link);
    }

    public function report(Request $request, string $report, ReportService $reports): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canManageFinance(), 403);
        abort_unless(array_key_exists($report, ReportService::catalogue()), 404);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return $reports->download(
            $report,
            isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null,
            isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null,
        );
    }
}
