<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\PaymentStatus;
use App\Services\ProrationEngine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A stay contract: one guest, one bed, one set of commercial terms.
 *
 * Business rules attached to this model:
 *  - Activating a booking occupies the bed; checking out frees it
 *    (enforced by BookingObserver, not by callers).
 *  - Rent is prorated on entry/exit through ProrationEngine.
 *  - Check-out builds a settlement invoice that offsets dues against the
 *    security deposit (CheckoutSettlementService).
 *
 * @property int $id
 * @property int $guest_id
 * @property int $bed_id
 * @property Carbon $check_in_date
 * @property Carbon|null $expected_check_out_date
 * @property Carbon|null $actual_check_out_date
 * @property string $monthly_rent
 * @property string $security_deposit_amount
 * @property BookingStatus $status
 * @property-read Guest $guest
 * @property-read Bed $bed
 */
class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'guest_id', 'bed_id', 'check_in_date', 'expected_check_out_date',
        'actual_check_out_date', 'monthly_rent', 'security_deposit_amount',
        'deposit_refunded_amount', 'rent_due_day', 'food_included',
        'notice_served_on', 'status', 'checked_out_at', 'checkout_settlement', 'notes',
        'assigned_marketer', 'converted_inquiry_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'check_in_date' => 'date',
            'expected_check_out_date' => 'date',
            'actual_check_out_date' => 'date',
            'notice_served_on' => 'date',
            'checked_out_at' => 'datetime',
            'checkout_settlement' => 'array',
            'food_included' => 'boolean',
            'rent_due_day' => 'integer',
            'monthly_rent' => 'decimal:2',
            'security_deposit_amount' => 'decimal:2',
            'deposit_refunded_amount' => 'decimal:2',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function leaveLogs(): HasMany
    {
        return $this->hasMany(LeaveLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function assignedMarketer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_marketer');
    }

    public function convertedFromInquiry(): BelongsTo
    {
        return $this->belongsTo(Inquiry::class, 'converted_inquiry_id');
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BookingStatus::Active->value,
            BookingStatus::NoticePeriod->value,
        ]);
    }

    /**
     * Billable and not yet billed for the given cycle start.
     *
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function scopeNotBilledForCycle(Builder $query, Carbon $cycleStart): Builder
    {
        return $query->whereDoesntHave('invoices', fn (Builder $invoices) => $invoices
            ->whereDate('billing_cycle_start', $cycleStart->toDateString()));
    }

    public function isLive(): bool
    {
        return $this->status->occupiesBed();
    }

    public function isOnNotice(): bool
    {
        return $this->status === BookingStatus::NoticePeriod;
    }

    /**
     * The billing cycle currently in progress for this stay.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function currentCycle(): array
    {
        return app(ProrationEngine::class)->cycleFor($this);
    }

    /**
     * Days the guest has been (or was) staying.
     */
    public function stayedDays(): int
    {
        $end = $this->actual_check_out_date ?? $this->expected_check_out_date ?? now();

        return max(0, (int) $this->check_in_date->diffInDays($end));
    }

    public function unpaidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)
            ->whereIn('status', [
                InvoiceStatus::Unpaid->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->orderBy('billing_cycle_start');
    }

    /**
     * Outstanding dues across every invoice of this stay.
     * Uses balance (total - paid) so partial payments never overstate dues.
     */
    public function outstandingDues(): float
    {
        return round((float) $this->invoices()
            ->whereIn('status', [
                InvoiceStatus::Unpaid->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->sum('total_due') - (float) $this->invoices()
            ->whereIn('status', [
                InvoiceStatus::Unpaid->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->sum('amount_paid'), 2);
    }

    /**
     * Money actually received (settled payments only).
     */
    public function totalReceived(): float
    {
        return (float) $this->payments()
            ->where('status', PaymentStatus::Success->value)
            ->sum('amount');
    }

    /**
     * Deposit still held by the house.
     */
    public function depositHeld(): float
    {
        return round((float) $this->security_deposit_amount - (float) $this->deposit_refunded_amount, 2);
    }

    /**
     * Approved food opt-out days inside a window, used for mess deductions.
     */
    public function foodOptOutDays(Carbon $from, Carbon $to): int
    {
        return (int) $this->leaveLogs()
            ->where('food_opt_out', true)
            ->whereIn('status', [
                LeaveStatus::Approved->value,
                LeaveStatus::Completed->value,
            ])
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get()
            ->sum(fn (LeaveLog $leave): int => $leave->daysWithin($from, $to));
    }
}
