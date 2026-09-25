<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillingController
{
    public function invoices(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['booking.guest', 'booking.bed.room']);

        if ($request->user()->isTenant() && $request->user()->guest_id) {
            $bookingIds = Booking::query()->where('guest_id', $request->user()->guest_id)->select('id');
            $query->whereIn('booking_id', $bookingIds);
        }

        $query->when($request->string('status')->toString(), function ($q, $status): void {
            if ($status === 'outstanding') {
                $q->outstanding();
            } elseif (InvoiceStatus::tryFrom($status)) {
                $q->where('status', $status);
            }
        });

        $invoices = $query->orderByDesc('due_date')->paginate(30);

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
        ]));
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

        $property = \App\Models\Property::current();

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
            ] : null,
            'payments' => Payment::query()
                ->whereIn('booking_id', $guest->bookings()->select('id'))
                ->settled()->orderByDesc('paid_on')->limit(10)
                ->get(['id', 'amount', 'payment_method', 'transaction_id', 'paid_on']),
        ]);
    }
}
