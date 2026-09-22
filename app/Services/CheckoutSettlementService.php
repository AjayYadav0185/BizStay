<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\CheckoutException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Notice & check-out workflow.
 *
 * The manager sees a full settlement preview before confirming: final prorated
 * rent, unbilled utility share, maintenance, food credit, damages, arrears and
 * the security deposit position. On confirmation the whole thing is written in
 * one transaction:
 *
 *   1. arrears are cleared with the deposit (oldest invoice first)
 *   2. a settlement invoice is raised for the final, part-used cycle
 *   3. the rest of the deposit is applied to it
 *   4. whatever is left is refunded to the guest (ledger row, outflow)
 *   5. a shortfall, if any, stays outstanding on the settlement invoice
 *   6. the booking closes and BookingObserver frees the bed
 */
final class CheckoutSettlementService
{
    public function __construct(
        private readonly ProrationEngine $proration,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Full settlement breakdown — safe to call while the guest is still living
     * in the room (no writes).
     *
     * @return array<string, mixed>
     */
    public function preview(
        Booking $booking,
        ?Carbon $checkoutDate = null,
        float $damages = 0.0,
        float $otherCredits = 0.0,
    ): array {
        $checkoutDate = ($checkoutDate ?? now())->copy()->startOfDay();
        $booking->loadMissing(['guest', 'bed.room']);

        [$cycleStart, $cycleEnd] = $this->proration->cycleContaining($checkoutDate);

        $occupancyStart = $booking->check_in_date->greaterThan($cycleStart)
            ? $booking->check_in_date->copy()
            : $cycleStart->copy();

        $rentDue = $this->proration->proratedRent(
            (float) $booking->monthly_rent,
            $cycleStart,
            $cycleEnd,
            $occupancyStart,
            $checkoutDate,
        );

        $maintenanceDue = $this->proration->proratedMaintenance(
            $cycleStart,
            $cycleEnd,
            $occupancyStart,
            $checkoutDate,
        );

        $cycleInvoices = $booking->invoices()
            ->whereDate('billing_cycle_start', $cycleStart->toDateString())
            ->where('is_checkout_settlement', false)
            ->get();

        $rentBilled = (float) $cycleInvoices->sum('rent_amount');
        $maintenanceBilled = (float) $cycleInvoices->sum('maintenance_charges');

        $rentAdjustment = round($rentDue - $rentBilled, 2);
        $maintenanceAdjustment = round(max(0.0, $maintenanceDue - $maintenanceBilled), 2);

        $utilityDue = $this->invoices->unbilledUtilityFor($booking, $checkoutDate);

        $foodDeduction = $this->proration->mealDeduction($booking, $cycleStart, $checkoutDate);

        $arrears = $this->invoices->arrearsFor($booking);

        $charges = round($arrears + max(0.0, $rentAdjustment) + $maintenanceAdjustment + $utilityDue + $damages, 2);
        $credits = round($foodDeduction + $otherCredits + max(0.0, -$rentAdjustment), 2);

        $netPayable = round($charges - $credits, 2);
        $totalPayable = max(0.0, $netPayable);
        $refundableCredit = max(0.0, -$netPayable);

        $depositHeld = $booking->depositHeld();
        $depositApplied = round(min($depositHeld, $totalPayable), 2);
        $refundDue = round($depositHeld - $depositApplied + $refundableCredit, 2);
        $balanceToCollect = round($totalPayable - $depositApplied, 2);

        return [
            'booking_id' => $booking->id,
            'guest_name' => $booking->guest?->full_name ?? '—',
            'room_number' => $booking->bed?->room?->room_number ?? '—',
            'bed_code' => $booking->bed?->bed_code ?? '—',
            'checkout_date' => $checkoutDate->toDateString(),
            'cycle_start' => $cycleStart->toDateString(),
            'cycle_end' => $cycleEnd->toDateString(),
            'occupied_days_in_cycle' => $this->proration->overlapDays($cycleStart, $cycleEnd, $occupancyStart, $checkoutDate),
            'cycle_days' => $this->proration->daysInclusive($cycleStart, $cycleEnd),
            'daily_rent' => $this->proration->dailyRent((float) $booking->monthly_rent, $cycleStart, $cycleEnd),
            'rent_due' => $rentDue,
            'rent_billed' => $rentBilled,
            'rent_adjustment' => $rentAdjustment,
            'maintenance_due' => $maintenanceAdjustment,
            'utility_due' => $utilityDue,
            'food_deduction' => $foodDeduction,
            'damages' => round($damages, 2),
            'other_credits' => round($otherCredits, 2),
            'arrears' => $arrears,
            'charges_total' => $charges,
            'credits_total' => $credits,
            'total_payable' => $totalPayable,
            'deposit_held' => $depositHeld,
            'deposit_applied' => $depositApplied,
            'refund_due' => $refundDue,
            'balance_to_collect' => $balanceToCollect,
        ];
    }

    /**
     * Executes the check-out: clears arrears with the deposit, raises the final
     * settlement invoice, refunds the balance and closes the stay.
     *
     * @param  array{
     *     damages?: float|string|null,
     *     other_credits?: float|string|null,
     *     payment_method?: string|null,
     *     transaction_id?: string|null,
     *     refund_method?: string|null,
     *     notes?: string|null
     * }  $options
     * @return array{settlement: array<string, mixed>, invoice: Invoice, refund_payment: Payment|null, collected: float}
     *
     * @throws CheckoutException
     */
    public function checkout(Booking $booking, ?Carbon $checkoutDate = null, array $options = []): array
    {
        return DB::transaction(function () use ($booking, $checkoutDate, $options): array {
            /** @var Booking $locked */
            $locked = Booking::query()->with(['guest', 'bed.room'])->lockForUpdate()->findOrFail($booking->id);

            if (! $locked->status->canCheckOut()) {
                throw CheckoutException::bookingNotLive(
                    $locked->guest?->full_name ?? 'guest',
                    $locked->status->getLabel(),
                );
            }

            $date = ($checkoutDate ?? now())->copy()->startOfDay();
            $damages = round((float) ($options['damages'] ?? 0), 2);
            $otherCredits = round((float) ($options['other_credits'] ?? 0), 2);

            $settlement = $this->preview($locked, $date, $damages, $otherCredits);

            $unappliedDeposit = (float) $settlement['deposit_held'];

            // 1. Clear arrears first so historical invoices close cleanly.
            foreach ($locked->unpaidInvoices()->get() as $arrear) {
                $balance = $arrear->balanceDue();

                if ($balance <= 0 || $unappliedDeposit <= 0) {
                    continue;
                }

                $applied = min($unappliedDeposit, $balance);
                $this->invoices->applyDepositCredit($arrear, $applied, "Deposit adjusted at check-out {$date->toDateString()}");
                $unappliedDeposit = round($unappliedDeposit - $applied, 2);
            }

            // 2. Final settlement invoice for the part-used cycle.
            // A monthly invoice may already exist for this cycle start, and
            // (booking_id, billing_cycle_start) is UNIQUE — so a settlement
            // reuses that row instead of inserting a duplicate.
            $existing = Invoice::query()
                ->where('booking_id', $locked->id)
                ->whereDate('billing_cycle_start', $settlement['cycle_start'])
                ->first();

            $payload = [
                'booking_id' => $locked->id,
                'billing_cycle_start' => $settlement['cycle_start'],
                'billing_cycle_end' => $settlement['cycle_end'],
                'rent_amount' => max(0.0, (float) $settlement['rent_adjustment']),
                'utility_amount' => (float) $settlement['utility_due'],
                'maintenance_charges' => (float) $settlement['maintenance_due'],
                'food_deduction' => (float) $settlement['food_deduction'],
                // Negative other_charges carry the unused-rent / goodwill credits.
                'other_charges' => round($damages - max(0.0, -(float) $settlement['rent_adjustment']) - $otherCredits, 2),
                'previous_balance' => 0.0,
                'due_date' => $date->toDateString(),
                'status' => InvoiceStatus::Unpaid,
                'is_checkout_settlement' => true,
                'notes' => $options['notes'] ?? "Check-out settlement for {$settlement['guest_name']}",
            ];

            $invoice = $existing ? tap($existing)->update($payload) : Invoice::query()->create($payload);
            $invoice = $invoice->refresh();

            // 3. Claim this room's still-unbilled readings for the final cycle.
            $this->invoices->claimUnbilledReadings($locked, $date, $invoice);

            // 4. Apply the remaining deposit to the settlement invoice.
            $balanceOnSettlement = $invoice->refresh()->balanceDue();

            if ($unappliedDeposit > 0 && $balanceOnSettlement > 0) {
                $applied = min($unappliedDeposit, $balanceOnSettlement);
                $this->invoices->applyDepositCredit($invoice, $applied, "Deposit applied to check-out settlement {$date->toDateString()}");
                $unappliedDeposit = round($unappliedDeposit - $applied, 2);
            }

            // 5. Refund whatever is left of the deposit (ledger outflow row).
            $refundPayment = null;
            $refundAmount = round($unappliedDeposit, 2);

            if ($refundAmount > 0) {
                $refundPayment = $invoice->payments()->create([
                    'booking_id' => $locked->id,
                    'payment_type' => PaymentType::Refund,
                    'amount' => $refundAmount,
                    'payment_method' => $options['refund_method'] ?? PaymentMethod::Cash->value,
                    'transaction_id' => $options['transaction_id'] ?? null,
                    'status' => PaymentStatus::Success,
                    'paid_on' => $date->toDateString(),
                    'collected_by' => auth()->id(),
                    'notes' => 'Security deposit refund on check-out',
                ]);
            }

            // 6. Record the shortfall if the manager collected it right now.
            $collected = 0.0;
            $invoice->refresh();
            $outstanding = $invoice->balanceDue();

            if ($outstanding > 0 && filled($options['payment_method'] ?? null)) {
                $this->invoices->recordPayment($invoice, [
                    'amount' => $outstanding,
                    'payment_method' => (string) $options['payment_method'],
                    'transaction_id' => $options['transaction_id'] ?? null,
                    'payment_type' => PaymentType::Settlement->value,
                    'paid_on' => $date->toDateString(),
                    'notes' => 'Collected at check-out',
                ]);

                $collected = $outstanding;
            }

            // 7. Close the stay — the observer hands the bed back to the pool.
            $locked->forceFill([
                'status' => BookingStatus::CheckedOut,
                'actual_check_out_date' => $date->toDateString(),
                'checked_out_at' => now(),
                'deposit_refunded_amount' => $refundAmount,
                'checkout_settlement' => array_merge($settlement, [
                    'refund_paid' => $refundAmount,
                    'collected_at_checkout' => $collected,
                    'settlement_invoice' => $invoice->invoice_number,
                    'settled_at' => now()->toIso8601String(),
                ]),
            ])->save();

            return [
                'settlement' => $locked->checkout_settlement,
                'invoice' => $invoice->refresh(),
                'refund_payment' => $refundPayment,
                'collected' => $collected,
            ];
        });
    }
}