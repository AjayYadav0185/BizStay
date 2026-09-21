<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sub-meter installed in a room. One meter per utility per room; the meter
 * owns the multiplier (CT ratio / dial factor) and may override the tariff
 * configured on the property profile.
 *
 * @property int $id
 * @property int $room_id
 * @property MeterType $meter_type
 * @property string $meter_serial_no
 * @property string $multiplier
 * @property string|null $rate_per_unit
 * @property-read Room $room
 */
class UtilityMeter extends Model
{
    /** @use HasFactory<\Database\Factories\UtilityMeterFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'room_id', 'meter_type', 'meter_serial_no', 'multiplier', 'rate_per_unit',
        'fixed_share_count', 'installed_on', 'is_active', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meter_type' => MeterType::class,
            'multiplier' => 'decimal:3',
            'rate_per_unit' => 'decimal:2',
            'fixed_share_count' => 'integer',
            'installed_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class, 'meter_id');
    }

    /**
     * @param  Builder<UtilityMeter>  $query
     * @return Builder<UtilityMeter>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<UtilityMeter>  $query
     * @return Builder<UtilityMeter>
     */
    public function scopeOfType(Builder $query, MeterType $type): Builder
    {
        return $query->where('meter_type', $type->value);
    }

    public function latestReading(): ?MeterReading
    {
        return $this->readings()->orderByDesc('reading_date')->orderByDesc('id')->first();
    }

    /**
     * Opening reading for a new entry: the last recorded value, else zero.
     */
    public function latestReadingValue(): float
    {
        return (float) ($this->latestReading()?->current_reading ?? 0);
    }

    /**
     * Effective tariff: explicit meter override wins, else the property rate.
     */
    public function resolveRate(): float
    {
        if ($this->rate_per_unit !== null) {
            return (float) $this->rate_per_unit;
        }

        return Property::current()?->rateFor($this->meter_type) ?? 0.0;
    }

    /**
     * Label for selects / bulk-entry rows, e.g. "101 · Electricity (E-4471)".
     */
    public function getDisplayLabelAttribute(): string
    {
        return sprintf(
            '%s · %s (%s)',
            $this->room?->room_number ?? '—',
            $this->meter_type->getLabel(),
            $this->meter_serial_no,
        );
    }
}
