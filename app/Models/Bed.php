<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BedStatus;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A bed is the atomic unit of inventory — the thing that is sold, occupied and
 * billed. All status changes funnel through the transition helpers below so the
 * state machine stays in one place; BedObserver then re-derives the room status.
 *
 * @property int $id
 * @property int $room_id
 * @property string $bed_code
 * @property BedStatus $status
 * @property string|null $rent_override
 * @property-read Room $room
 * @property-read Booking|null $currentBooking
 */
class Bed extends Model
{
    /** @use HasFactory<\Database\Factories\BedFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'room_id', 'bed_code', 'position', 'status', 'rent_override',
        'maintenance_since', 'maintenance_note', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BedStatus::class,
            'position' => 'integer',
            'rent_override' => 'decimal:2',
            'maintenance_since' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The live stay occupying this bed, if any.
     */
    public function currentBooking(): HasOne
    {
        return $this->hasOne(Booking::class)
            ->whereIn('status', [
                BookingStatus::Active->value,
                BookingStatus::NoticePeriod->value,
            ])
            ->latestOfMany();
    }

    /**
     * @param  Builder<Bed>  $query
     * @return Builder<Bed>
     */
    public function scopeAllocatable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            BedStatus::Available->value,
            BedStatus::Reserved->value,
        ]);
    }

    /**
     * @param  Builder<Bed>  $query
     * @return Builder<Bed>
     */
    public function scopeOccupied(Builder $query): Builder
    {
        return $query->where('status', BedStatus::Occupied->value);
    }

    /**
     * @param  Builder<Bed>  $query
     * @return Builder<Bed>
     */
    public function scopeWithStatus(Builder $query, BedStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Rent actually charged for this bed: an explicit override wins, otherwise
     * the room's per-bed base rent applies.
     */
    public function effectiveRent(): float
    {
        return (float) ($this->rent_override ?? $this->room->base_rent_per_bed);
    }

    public function getEffectiveRentAttribute(): float
    {
        return $this->effectiveRent();
    }

    public function occupant(): ?Guest
    {
        return $this->currentBooking?->guest;
    }

    public function isAllocatable(): bool
    {
        return $this->status->isAllocatable();
    }

    // ---------------------------------------------------------------------
    // State machine — the only supported way to change a bed's status.
    // ---------------------------------------------------------------------

    public function markOccupied(): bool
    {
        return $this->transitionTo(BedStatus::Occupied);
    }

    public function markAvailable(): bool
    {
        return $this->transitionTo(BedStatus::Available);
    }

    public function markReserved(): bool
    {
        return $this->transitionTo(BedStatus::Reserved);
    }

    public function sendToMaintenance(?string $note = null): bool
    {
        return $this->transitionTo(BedStatus::Maintenance, [
            'maintenance_since' => now()->toDateString(),
            'maintenance_note' => $note,
        ]);
    }

    public function releaseFromMaintenance(): bool
    {
        return $this->transitionTo(BedStatus::Available, [
            'maintenance_since' => null,
            'maintenance_note' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transitionTo(BedStatus $status, array $attributes = []): bool
    {
        if ($this->status === $status && $attributes === []) {
            return false;
        }

        $this->forceFill(array_merge($attributes, ['status' => $status]));

        return $this->save();
    }
}
