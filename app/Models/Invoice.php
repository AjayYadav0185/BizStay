<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A monthly (or settlement) demand raised against a booking.
 *
 * total_due is always recomputed from its components; amount_paid is always
 * recomputed from settled payments. Both live in observers so no controller or
 * resource can write an inconsistent ledger.
 *
 * @property int $id
 * @property string $invoice_number
 * @property int $booking_id
 * @property Carbon $billing_cycle_start
 * @property Carbon $billing_cycle_end
 * @property string $rent_amount
 * @property string $utility_amount
 * @property string $total_due
 * @property string $amount_paid
 * @property InvoiceStatus $status
 */
class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'invoice_number', 'booking_id', 'billing_cycle_start', 'billing_cycle_end',
        'rent_amount', 'utility_amount', 'maintenance_charges', 'food_deduction',
        'other_charges', 'gst_percent', 'gst_amount', 'previous_balance', 'total_due', 'amount_paid',
        'due_date', 'status', 'is_checkout_settlement', 'generated_at', 'paid_at', 'notes',
        'late_fee_charged_on', 'last_reminded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'billing_cycle_start' => 'date',
            'billing_cycle_end' => 'date',
            'due_date' => 'date',
            'generated_at' => 'datetime',
            'paid_at' => 'datetime',
            'is_checkout_settlement' => 'boolean',
            'late_fee_charged_on' => 'date',
            'last_reminded_at' => 'date',
            'gst_percent' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'rent_amount' => 'decimal:2',
            'utility_amount' => 'decimal:2',
            'maintenance_charges' => 'decimal:2',
            'food_deduction' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'previous_balance' => 'decimal:2',
            'total_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Unpaid->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Overdue->value,
        ]);
    }

    /**
     * @param  Builder<Invoice>  $query
     * @return Builder<Invoice>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', [
            InvoiceStatus::Unpaid->value,
            InvoiceStatus::PartiallyPaid->value,
        ])->whereDate('due_date', '<', now()->toDateString());
    }

    /**
     * Sum of the charge components minus credits. Kept static so the observer,
     * the invoice service and tests all compute identical numbers.
     * GST is stored as its own line (gst_amount) for the hotel slab.
     */
    public static function composeTotal(
        float $rent,
        float $utility,
        float $maintenance,
        float $foodDeduction,
        float $otherCharges,
        float $previousBalance,
        float $gstAmount = 0.0,
    ): float {
        return round(
            $rent + $utility + $maintenance + $otherCharges + $gstAmount + $previousBalance - $foodDeduction,
            2,
        );
    }

    public function balanceDue(): float
    {
        return round((float) $this->total_due - (float) $this->amount_paid, 2);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOutstanding() && $this->due_date->isPast();
    }

    /**
     * Outstanding + already-invoiced credit, used for settlement maths.
     */
    public function settledAmount(): float
    {
        return (float) $this->payments()
            ->where('status', PaymentStatus::Success->value)
            ->sum('amount');
    }

    /**
     * Deterministic invoice number: BZ-YYYYMM-#### (sequence per month).
     */
    public static function nextNumber(?Carbon $on = null): string
    {
        $on ??= now();
        $prefix = 'BZ-'.$on->format('Ym').'-';

        $last = static::withTrashed()
            ->where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function getGuestNameAttribute(): string
    {
        return $this->booking?->guest?->full_name ?? '—';
    }
}
