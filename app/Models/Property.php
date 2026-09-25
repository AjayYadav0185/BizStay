<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BedStatus;
use App\Enums\MeterType;
use App\Enums\PropertyType;
use App\Services\InvoiceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The single PG building. Exactly one active row is expected; all billing
 * settings live here so the money engine has a single source of truth.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property PropertyType $type
 * @property string $address
 * @property int $total_floors
 * @property int $security_deposit_months
 * @property int $notice_period_days
 * @property int $billing_cycle_start_day
 * @property string $electricity_rate_per_unit
 * @property string $water_rate_per_unit
 * @property string $meal_charge_per_day
 * @property string $maintenance_charge_per_bed
 * @property string $late_fee_percent
 */
class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'code', 'type', 'address', 'locality', 'city', 'state', 'pincode', 'gstin', 'upi_id',
        'check_in_time', 'check_out_time',
        'manager_name', 'contact_phone', 'contact_email', 'total_floors', 'amenities',
        'security_deposit_months', 'notice_period_days', 'billing_cycle_start_day',
        'electricity_rate_per_unit', 'water_rate_per_unit', 'meal_charge_per_day',
        'maintenance_charge_per_bed', 'late_fee_percent', 'is_active', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'amenities' => 'array',
            'is_active' => 'boolean',
            'total_floors' => 'integer',
            'security_deposit_months' => 'integer',
            'notice_period_days' => 'integer',
            'billing_cycle_start_day' => 'integer',
            'electricity_rate_per_unit' => 'decimal:2',
            'water_rate_per_unit' => 'decimal:2',
            'meal_charge_per_day' => 'decimal:2',
            'maintenance_charge_per_bed' => 'decimal:2',
            'late_fee_percent' => 'decimal:2',
        ];
    }

    /**
     * The building profile. Deliberately not memoised in a static so that
     * RefreshDatabase style tests never see a stale row.
     */
    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->orderBy('id')->first()
            ?? static::query()->orderBy('id')->first();
    }

    /**
     * Single-property mode: inventory is queried directly instead of through a
     * property_id foreign key. Rooms/beds stay property-agnostic so the domain
     * can later become multi-property with one additive migration.
     */
    public function bedCount(): int
    {
        return Bed::query()->count();
    }

    public function occupiedBedCount(): int
    {
        return Bed::query()->where('status', BedStatus::Occupied->value)->count();
    }

    public function occupancyPercent(): float
    {
        $total = $this->bedCount();

        return $total === 0
            ? 0.0
            : round($this->occupiedBedCount() / $total * 100, 1);
    }

    /**
     * Applies the configured tariff for a utility, used when a meter does not
     * carry its own override rate.
     */
    public function rateFor(MeterType $meterType): float
    {
        return (float) $this->{$meterType->getSettingKey()};
    }

    public function getFullAddressAttribute(): string
    {
        return trim(implode(', ', array_filter([
            $this->address,
            $this->locality,
            $this->city,
            $this->state,
            $this->pincode,
        ])), ' ,');
    }

    /**
     * Money actually collected from guests in the calendar month of $on.
     * Deposit adjustments are excluded — no cash moved.
     */
    public function collectedInMonth(?Carbon $on = null): float
    {
        $on ??= now();
        $from = $on->copy()->startOfMonth();
        $to = $on->copy()->endOfMonth();

        return app(InvoiceService::class)->collectedBetween($from, $to);
    }

    /**
     * Operating spend in the calendar month of $on.
     */
    public function expensesInMonth(?Carbon $on = null): float
    {
        $on ??= now();

        return round((float) Expense::query()
            ->between($on->copy()->startOfMonth(), $on->copy()->endOfMonth())
            ->sum('amount'), 2);
    }

    /**
     * Simple P&L for a month: collected (inflows) minus operating expenses.
     * No accruals — this is a cash view a PG owner actually runs the house on.
     */
    public function monthlyPnL(?Carbon $on = null): array
    {
        $collected = $this->collectedInMonth($on);
        $expenses = $this->expensesInMonth($on);

        return [
            'collected' => $collected,
            'expenses' => $expenses,
            'profit' => round($collected - $expenses, 2),
        ];
    }
}
