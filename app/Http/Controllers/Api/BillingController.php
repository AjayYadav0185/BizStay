<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Services\InvoiceService;
use App\Services\RazorpayService;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillingController
{
    public function invoices(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['booking.guest', 'booking.bed.room']);

        if ($request->user()->isTenant()) {
            $bookingIds = Booking::query()->where('guest_id', $request->user()->guest_id)->select('id');
            $query->whereIn('booking_id', $bookingIds);
        }

        $query->when($request->string('status')->toString(), function ($q, $status): void {
            if ($status === 'outstanding') {
                $q->outstanding();
            } elseif ($status === 'overdue') {
                $q->overdue();
            } elseif ($status === 'all') {
                // No filter — return everything.
            } elseif (InvoiceStatus::tryFrom($status)) {
                $q->where('status', $status);
            }
        });

        $invoices = $query->orderByDesc('due_date')->paginate(30);
        $isStaff = $request->user()->isStaff();
        $property = Property::current();

        return response()->json($invoices->through(fn (Invoice $i) => [
            'id' => $i->id,
            'invoice_number' => $i->invoice_number,
            'guest' => $i->booking?->guest?->full_name,
            'bed' => $i->booking?->bed?->bed_code,
            'cycle' => $i->billing_cycle_start?->format('M Y'),
            'rent' => (float) $i->rent_amount,
            'utility' => (float) $i->utility_amount,
            'gst_percent' => (float) ($i->gst_percent ?? 0),
            'gst_amount' => (float) ($i->gst_amount ?? 0),
            'total_due' => (float) $i->total_due,
            'paid' => (float) $i->amount_paid,
            'balance' => $i->balanceDue(),
            'due_date' => $i->due_date?->toDateString(),
            'status' => $i->status->value,
            'pay_link' => $i->payment_link_url,
            'phone' => $isStaff ? $i->booking?->guest?->phone : null,
            'last_reminded' => $isStaff ? $i->last_reminded_at?->toDateString() : null,
            'upi_uri' => $i->balanceDue() > 0 ? $property?->upiPaymentUri($i->balanceDue(), 'Rent '.$i->invoice_number) : null,
        ]));
    }

    /**
     * Logs a WhatsApp reminder and returns the wa.me link with the message
     * pre-filled; the app opens it in WhatsApp.
     */
    public function remind(Invoice $invoice, InvoiceService $service): JsonResponse
    {
        $url = WhatsApp::link($invoice->booking?->guest?->phone, WhatsApp::rentReminder($invoice));

        if ($url === null) {
            return response()->json(['message' => 'This resident has no valid Indian mobile number.'], 422);
        }

        $service->markReminded($invoice, 'whatsapp');

        return response()->json(['url' => $url]);
    }

    /**
     * Online payment link for an invoice's balance (Razorpay). Tenants may
     * only request links for their own invoices.
     */
    public function payLink(Request $request, Invoice $invoice, RazorpayService $razorpay): JsonResponse
    {
        $user = $request->user();

        if ($user->isTenant()) {
            abort_unless((int) $invoice->booking?->guest_id === (int) $user->guest_id, 404);
        }

        if (! $razorpay->isEnabled()) {
            return response()->json(['message' => 'Online payment is not enabled for this property. Pay by UPI and share the UTR with the office.'], 422);
        }

        try {
            $url = $razorpay->paymentLinkFor($invoice);
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['url' => $url, 'amount' => $invoice->balanceDue()]);
    }

    public function collect(Request $request, Invoice $invoice, InvoiceService $service): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:upi,netbanking,cash,card,cheque'],
            'transaction_id' => ['nullable', 'string', 'max:80'],
            'paid_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $service->recordPayment($invoice, [
            'amount' => (float) $data['amount'],
            'payment_method' => $data['payment_method'],
            'transaction_id' => $data['transaction_id'] ?? null,
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json([
            'payment_id' => $payment->id,
            'balance' => $invoice->refresh()->balanceDue(),
            'status' => $invoice->status->value,
        ], 201);
    }

    public function myStay(Request $request): JsonResponse
    {
        $guest = $request->user()->guest;
        abort_unless($guest instanceof Guest, 404, 'No guest linked to this login.');
        $guest->loadMissing(['currentBooking.bed.room', 'bookings']);

        $property = Property::current();

        $due = Invoice::query()
            ->whereIn('booking_id', $guest->bookings()->select('id'))
            ->outstanding()
            ->orderBy('due_date')
            ->first();

        return response()->json([
            'guest' => ['full_name' => $guest->full_name, 'phone' => $guest->phone],
            'bed' => $guest->currentBooking?->bed?->bed_code,
            'room' => $guest->currentBooking?->bed?->room?->room_number,
            'outstanding' => $guest->outstandingBalance(),
            // Where to send the money — shown as a UPI card in the app.
            'property' => $property ? [
                'name' => $property->name,
                'upi_id' => $property->upi_id,
                'whatsapp' => $property->whatsapp_number ?? $property->contact_phone,
                'gstin' => $property->gstin,
                'check_in_time' => $property->check_in_time,
                'check_out_time' => $property->check_out_time,
            ] : null,
            'due' => $due ? [
                'id' => $due->id,
                'invoice_number' => $due->invoice_number,
                'total_due' => (float) $due->total_due,
                'balance' => $due->balanceDue(),
                'due_date' => $due->due_date?->toDateString(),
                'upi_uri' => $property?->upiPaymentUri($due->balanceDue(), 'Rent '.$due->invoice_number),
                'pay_link' => $due->payment_link_url,
            ] : null,
            'online_payments' => app(RazorpayService::class)->isEnabled(),
            'payments' => Payment::query()
                ->whereIn('booking_id', $guest->bookings()->select('id'))
                ->settled()->orderByDesc('paid_on')->limit(10)
                ->get(['id', 'amount', 'payment_method', 'transaction_id', 'paid_on']),
        ]);
    }
}
