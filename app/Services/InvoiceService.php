<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MeterType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Bed;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Property;
use App\Models\UtilityMeter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monthly billing.
 *
 * One invoice per booking per cycle, always composed the same way:
 *   prorated rent + utility share + maintenance + other - food credit + arrears
 *
 * Utility readings are shared by every bed in the room (a room has one meter,
 * many guests). The readings themselves are attached to exactly one invoice per
 * room/cycle so a unit of electricity can never be billed twice, while every
 * guest's share lands on their own invoice.
 */
final class InvoiceService
{
    public function __construct(private readonly ProrationEngine $proration) {}

    /**
     * Bills every live booking that has not yet been billed for the cycle.
     *
     * @return Collection<int, Invoice>
     */
    public function generateForCycle(?Carbon $on = null): Collection
    {
        [$cycleStart, $cycleEnd] = $this->proration->cycleContaining($on ?? now());

        return $this->generateForWindow($cycleStart, $cycleEnd);
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function generateForWindow(Carbon $cycleStart, Carbon $cycleEnd): Collection
    {
        $invoices = new Collection;

        $bookings = Booking::query()
            ->live()
            ->notBilledForCycle($cycleStart)
            ->with(['guest', 'bed.room'])
            ->orderBy('id')
            ->get();

        foreach ($bookings as $booking) {
            $invoices->push($this->generateForBooking($booking, $cycleStart, $cycleEnd));
        }

        return $invoices;
    }

    /**
     * Creates (or refreshes) the invoice of one booking for one cycle.
     */
    public function generateForBooking(
        Booking $booking,
        Carbon $cycleStart,
        Carbon $cycleEnd,
        float $otherCharges = 0.0,
    ): Invoice {
        return DB::transaction(function () use ($booking, $cycleStart, $cycleEnd, $otherCharges): Invoice {
            /** @var Booking $locked */
            $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

            $occupancyStart = $locked->check_in_date->greaterThan($cycleStart)
                ? $locked->check_in_date->copy()
                : $cycleStart->copy();

            $occupancyEnd = $locked->expected_check_out_date && $locked->expected_check_out_date->lessThan($cycleEnd)
                ? $locked->expected_check_out_date->copy()
                : $cycleEnd->copy();

            [$utilityAmount, $claimAmount, $readingIds] = $this->utilityShare($locked, $cycleStart, $cycleEnd);

            // whereDate: SQLite stores `date` columns with a time component
            // via Eloquent, so a plain `=` on 'YYYY-MM-DD' would miss and
            // break idempotency with a UNIQUE violation on re-run.
            $invoice = Invoice::query()
                ->where('booking_id', $locked->id)
                ->whereDate('billing_cycle_start', $cycleStart->toDateString())
                ->first() ?? new Invoice([
                    'booking_id' => $locked->id,
                    'billing_cycle_start' => $cycleStart->toDateString(),
                ]);

            $invoice->fill([
                'billing_cycle_end' => $cycleEnd->toDateString(),
                'rent_amount' => $this->proration->proratedRent(
                    (float) $locked->monthly_rent,
                    $cycleStart,
                    $cycleEnd,
                    $occupancyStart,
                    $occupancyEnd,
                ),
                'utility_amount' => $utilityAmount,
                'maintenance_charges' => $this->proration->proratedMaintenance(
                    $cycleStart,
                    $cycleEnd,
                    $occupancyStart,
                    $occupancyEnd,
                ),
                'food_deduction' => $this->proration->mealDeduction($locked, $cycleStart, $cycleEnd),
                'other_charges' => $otherCharges,
                // Each invoice stands alone: arrears stay on their own rows and
                // are surfaced via Booking::outstandingDues()/InvoiceService::arrearsFor(),
                // so a cycle is never double-counted in the ledger.
                'previous_balance' => 0.0,
                'due_date' => $this->dueDateFor($locked, $cycleStart, $occupancyStart),
                'status' => $invoice->exists ? $invoice->status : InvoiceStatus::Unpaid,
            ]);

            $invoice->save();

            // Claim the room's readings for this cycle on one invoice only.
            if ($claimAmount > 0.0 && $readingIds->isNotEmpty()) {
                MeterReading::query()
                    ->whereIn('id', $readingIds->all())
                    ->whereNull('invoice_id')
                    ->update(['invoice_id' => $invoice->id]);
            }

            return $invoice->refresh();
        });
    }

    /**
     * A booking's share of its room's metered consumption for the window.
     *
     * @return array{0: float, 1: float, 2: Collection<int, int>} [share, claimed total, reading ids]
     */
    public function utilityShare(Booking $booking, Carbon $from, Carbon $to): array
    {
        $room = $booking->bed?->room;

        if (! $room) {
            return [0.0, 0.0, new Collection];
        }

        $total = 0.0;
        $readingIds = new Collection;
        $divisor = max(1, $this->shareDivisor($booking, $from, $to));

        foreach ($room->meters()->active()->get() as $meter) {
            /** @var UtilityMeter $meter */
            $readings = $meter->readings()
                ->whereNull('invoice_id')
                ->between($from, $to)
                ->get();

            foreach ($readings as $reading) {
                $total += (float) $reading->amount;
                $readingIds->push($reading->id);
            }
        }

        // Only the lowest booking id in the room claims the readings; the others
        // still receive an equal share of the same consumption.
        $isClaimant = (int) Booking::query()
            ->whereIn('bed_id', $room->beds()->pluck('id'))
            ->live()
            ->min('id') === (int) $booking->id;

        return [
            round($total / $divisor, 2),
            $isClaimant ? round($total, 2) : 0.0,
            $isClaimant ? $readingIds : new Collection,
        ];
    }

    /**
     * Utility share for a checkout, ignoring the claimant rule (the guest is
     * leaving, so they always settle their own part of the unbilled readings).
     */
    public function unbilledUtilityFor(Booking $booking, Carbon $until): float
    {
        $room = $booking->bed?->room;

        if (! $room) {
            return 0.0;
        }

        [$cycleStart] = $this->proration->cycleContaining($until);

        $total = (float) MeterReading::query()
            ->whereIn('meter_id', $room->meters()->pluck('id'))
            ->whereNull('invoice_id')
            ->between($cycleStart, $until)
            ->sum('amount');

        return round($total / max(1, $this->shareDivisor($booking, $cycleStart, $until)), 2);
    }

    /**
     * Outstanding arrears from earlier cycles, excluding the invoice in hand.
     */
    public function arrearsFor(Booking $booking, ?Invoice $excluding = null): float
    {
        $query = $booking->invoices()->outstanding()
            ->when($excluding?->exists, fn ($builder) => $builder->whereKeyNot($excluding->id));

        $due = (float) (clone $query)->sum('total_due');
        $paid = (float) $booking->invoices()
            ->when($excluding?->exists, fn ($builder) => $builder->whereKeyNot($excluding->id))
            ->sum('amount_paid');

        return round($due - $paid, 2);
    }

    /**
     * Records a collection against an invoice. PaymentObserver recomputes the
     * invoice balance, so no status is written here.
     *
     * @param  array{amount: float, payment_method: string, transaction_id?: string|null, payment_type?: string|null, paid_on?: string|null, notes?: string|null}  $data
     */
    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data): Payment {
            $payment = $invoice->payments()->create([
                'booking_id' => $invoice->booking_id,
                'payment_type' => $data['payment_type'] ?? $this->inferPaymentType($invoice),
                'amount' => round((float) $data['amount'], 2),
                'payment_method' => $data['payment_method'],
                'transaction_id' => $data['transaction_id'] ?? null,
                'status' => PaymentStatus::Success,
                'paid_on' => $data['paid_on'] ?? now()->toDateString(),
                'collected_by' => auth()->id(),
                'notes' => $data['notes'] ?? null,
            ]);

            return $payment->refresh();
        });
    }

    /**
     * Applies a credit (security deposit) against an invoice without any money
     * moving. Recorded as an explicit ledger row so the audit trail is complete.
     */
    public function applyDepositCredit(Invoice $invoice, float $amount, string $notes = 'Security deposit adjusted'): ?Payment
    {
        if ($amount <= 0.0) {
            return null;
        }

        return $invoice->payments()->create([
            'booking_id' => $invoice->booking_id,
            'payment_type' => PaymentType::Deposit,
            'amount' => round($amount, 2),
            'payment_method' => PaymentMethod::DepositAdjustment,
            'status' => PaymentStatus::Success,
            'paid_on' => now()->toDateString(),
            'collected_by' => auth()->id(),
            'notes' => $notes,
        ]);
    }

    /**
     * Binds the room's still-unbilled readings for a window to an invoice, so
     * every unit billed can be traced to the meter that produced it.
     */
    public function claimUnbilledReadings(Booking $booking, Carbon $until, Invoice $invoice): int
    {
        $room = $booking->bed?->room;

        if (! $room) {
            return 0;
        }

        [$cycleStart] = $this->proration->cycleContaining($until);

        return MeterReading::query()
            ->whereIn('meter_id', $room->meters()->pluck('id'))
            ->whereNull('invoice_id')
            ->between($cycleStart, $until)
            ->update(['invoice_id' => $invoice->id]);
    }

    /**
     * Flags outstanding invoices whose due date has passed. Safe to run daily.
     */
    public function markOverdue(): int
    {
        return Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::PartiallyPaid->value])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => InvoiceStatus::Overdue->value]);
    }

    /**
     * Late-fee engine — the "stored but never charged" percent finally charges.
     *
     * For every overdue invoice (balance > 0) the configured
     * late_fee_percent of the outstanding balance is added as a visible
     * other_charges line. Guarded by the late_fee_charged_on marker so a
     * re-run never double-charges the same invoice.
     *
     * @return array{charged: int, amount: float}
     */
    public function applyLateFees(): array
    {
        $charged = 0;
        $total = 0.0;

        $overdue = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Unpaid->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNull('late_fee_charged_on')
            ->with('booking')
            ->get();

        foreach ($overdue as $invoice) {
            $balance = $invoice->balanceDue();

            if ($balance <= 0.0) {
                continue;
            }

            $fee = $this->proration->lateFee($balance);

            if ($fee <= 0.0) {
                continue;
            }

            $invoice->forceFill([
                'other_charges' => round((float) $invoice->other_charges + $fee, 2),
                'late_fee_charged_on' => now()->toDateString(),
                'notes' => trim(($invoice->notes ? $invoice->notes."\n" : '')
                    .sprintf('Late fee %s%% (₹%s) on overdue ₹%s charged %s.',
                        rtrim(rtrim((string) (Property::current()?->late_fee_percent ?? 0), '0'), '.'),
                        number_format($fee, 2),
                        number_format($balance, 2),
                        now()->toDateString())),
            ])->save();

            $charged++;
            $total = round($total + $fee, 2);
        }

        return ['charged' => $charged, 'amount' => $total];
    }

    /**
     * Overdue invoices that have not been reminded in the last 3 days — the
     * rows of the "Remind" action / nightly reminder job.
     *
     * @return Collection<int, Invoice>
     */
    public function invoicesDueForReminder(): Collection
    {
        return Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Unpaid->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->whereDate('due_date', '<', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<', now()->subDays(3)->toDateString());
            })
            ->with(['booking.guest', 'booking.bed.room'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Stamps the reminder marker on an invoice and logs the touchpoint so the
     * audit trail shows when the guest was chased (SMS/WhatsApp/Email ride on
     * top of this later — the ledger only records that it happened).
     */
    public function markReminded(Invoice $invoice, string $channel = 'manual'): void
    {
        $invoice->forceFill(['last_reminded_at' => now()->toDateString()])->save();

        Log::notice('bizstay.invoice.reminder', [
            'invoice' => $invoice->invoice_number,
            'guest' => $invoice->booking?->guest?->full_name,
            'balance' => $invoice->balanceDue(),
            'channel' => $channel,
            'by' => auth()->id(),
        ]);
    }

    /**
     * Total rent expected from live stays (used for the collection ratio).
     */
    public function expectedRentFor(): float
    {
        return round((float) Booking::query()->live()->sum('monthly_rent'), 2);
    }

    /**
     * Convenience for dashboards: money actually collected in a window.
     * Deposit adjustments are excluded — no money moved, so counting them would
     * overstate cash inflow.
     */
    public function collectedBetween(Carbon $from, Carbon $to): float
    {
        return round(
            (float) Payment::query()
                ->settled()
                ->between($from, $to)
                ->where('payment_type', '!=', PaymentType::Refund->value)
                ->where('payment_method', '!=', PaymentMethod::DepositAdjustment->value)
                ->sum('amount'),
            2,
        );
    }

    /**
     * Meter types installed in the building — used by the bulk entry screen.
     *
     * @return array<string, string>
     */
    public function installedMeterTypes(): array
    {
        $options = [];

        foreach (UtilityMeter::query()->active()->distinct()->pluck('meter_type') as $type) {
            $enum = MeterType::tryFrom((string) $type);

            if ($enum) {
                $options[$enum->value] = $enum->getLabel().' ('.$enum->getUnit().')';
            }
        }

        return $options === [] ? MeterType::options() : $options;
    }

    /**
     * Beds that show as occupied but have no live booking behind them — an
     * integrity check the dashboard surfaces so drift is caught immediately.
     *
     * @return Collection<int, string>
     */
    public function orphanedOccupiedBeds(): Collection
    {
        return Bed::query()
            ->occupied()
            ->whereDoesntHave('bookings', fn ($query) => $query->live())
            ->pluck('bed_code');
    }

    private function dueDateFor(Booking $booking, Carbon $cycleStart, Carbon $occupancyStart): Carbon
    {
        $rentDueDay = max(1, min(28, (int) $booking->rent_due_day));
        $dueDate = $cycleStart->copy()->addDays($rentDueDay - 1);

        // A guest who joined mid-cycle gets a short grace window instead.
        $graceDate = $occupancyStart->copy()->addDays(3);

        return $dueDate->lessThan($graceDate) ? $graceDate : $dueDate;
    }

    /**
     * How many paying guests share one room's meter during the window.
     */
    private function shareDivisor(Booking $booking, Carbon $from, Carbon $to): int
    {
        $room = $booking->bed?->room;

        if (! $room) {
            return 1;
        }

        $override = $room->meters()->active()->whereNotNull('fixed_share_count')->value('fixed_share_count');

        if ($override !== null) {
            return (int) $override;
        }

        $occupiedBeds = Booking::query()
            ->whereIn('bed_id', $room->beds()->pluck('id'))
            ->live()
            ->whereDate('check_in_date', '<=', $to->toDateString())
            ->where(function ($query) use ($from): void {
                $query->whereNull('expected_check_out_date')
                    ->orWhereDate('expected_check_out_date', '>=', $from->toDateString());
            })
            ->count();

        return max(1, $occupiedBeds);
    }

    private function inferPaymentType(Invoice $invoice): PaymentType
    {
        return match (true) {
            $invoice->is_checkout_settlement => PaymentType::Settlement,
            (float) $invoice->rent_amount > 0.0 => PaymentType::Rent,
            (float) $invoice->utility_amount > 0.0 => PaymentType::Utility,
            default => PaymentType::Other,
        };
    }
}
