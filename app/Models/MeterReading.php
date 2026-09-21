<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single meter reading. consumption and amount are always derived
 * server-side by MeterReadingObserver (never trusted from a form), so the
 * ledger cannot be corrupted by a manager typing an inflated delta.
 *
 * @property int $id
 * @property int $meter_id
 * @property Carbon $reading_date
 * @property string $previous_reading
 * @property string $current_reading
 * @property string $consumption
 * @property string $rate_per_unit
 * @property string $amount
 */
class MeterReading extends Model
{
    /** @use HasFactory<\Database\Factories\MeterReadingFactory> */
    use HasFactory;

    protected $fillable = [
        'meter_id', 'reading_date', 'previous_reading', 'current_reading',
        'consumption', 'rate_per_unit', 'amount', 'invoice_id', 'recorded_by', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'previous_reading' => 'decimal:2',
            'current_reading' => 'decimal:2',
            'consumption' => 'decimal:2',
            'rate_per_unit' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(UtilityMeter::class, 'meter_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @param  Builder<MeterReading>  $query
     * @return Builder<MeterReading>
     */
    public function scopeUnbilled(Builder $query): Builder
    {
        return $query->whereNull('invoice_id');
    }

    /**
     * @param  Builder<MeterReading>  $query
     * @return Builder<MeterReading>
     */
    public function scopeBetween(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->whereDate('reading_date', '>=', $from->toDateString())
            ->whereDate('reading_date', '<=', $to->toDateString());
    }

    /**
     * consumption = (current - previous) * multiplier, floored at zero so a
     * replaced/reset meter never produces a negative bill.
     */
    public static function consumptionFor(float $previous, float $current, float $multiplier): float
    {
        return round(max(0.0, $current - $previous) * $multiplier, 2);
    }

    public static function amountFor(float $consumption, float $ratePerUnit): float
    {
        return round($consumption * $ratePerUnit, 2);
    }

    public function roomNumber(): string
    {
        return $this->meter?->room?->room_number ?? '—';
    }
}
