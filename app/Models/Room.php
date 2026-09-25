<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BedStatus;
use App\Enums\RoomStatus;
use App\Enums\SharingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A physical room in the building. Bed count always equals the sharing
 * capacity; the room status is derived and written by BedObserver so it can
 * never drift from the beds it contains.
 *
 * @property int $id
 * @property int $floor_no
 * @property string $room_number
 * @property SharingType $sharing_type
 * @property string $base_rent_per_bed
 * @property RoomStatus $status
 * @property-read Collection<int, Bed> $beds
 */
class Room extends Model
{
    /** @use HasFactory<\Database\Factories\RoomFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'floor_no', 'room_number', 'sharing_type', 'base_rent_per_bed', 'nightly_rate',
        'security_deposit_default', 'has_ac', 'attached_bathroom', 'has_balcony',
        'status', 'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sharing_type' => SharingType::class,
            'status' => RoomStatus::class,
            'has_ac' => 'boolean',
            'attached_bathroom' => 'boolean',
            'has_balcony' => 'boolean',
            'floor_no' => 'integer',
            'base_rent_per_bed' => 'decimal:2',
            'nightly_rate' => 'decimal:2',
            'security_deposit_default' => 'integer',
        ];
    }

    public function beds(): HasMany
    {
        return $this->hasMany(Bed::class);
    }

    public function meters(): HasMany
    {
        return $this->hasMany(UtilityMeter::class);
    }

    public function meterReadings(): HasManyThrough
    {
        return $this->hasManyThrough(MeterReading::class, UtilityMeter::class);
    }

    /**
     * @param  Builder<Room>  $query
     * @return Builder<Room>
     */
    public function scopeOnFloor(Builder $query, int $floor): Builder
    {
        return $query->where('floor_no', $floor);
    }

    /**
     * Rooms that still have at least one bed a manager could hand over.
     *
     * @param  Builder<Room>  $query
     * @return Builder<Room>
     */
    public function scopeWithFreeBeds(Builder $query): Builder
    {
        return $query->whereHas('beds', fn (Builder $beds) => $beds->whereIn('status', [
            BedStatus::Available->value,
            BedStatus::Reserved->value,
        ]));
    }

    public function occupiedBedCount(): int
    {
        return $this->beds->where('status', BedStatus::Occupied)->count();
    }

    public function availableBedCount(): int
    {
        return $this->beds->where('status', BedStatus::Available)->count();
    }

    public function maintenanceBedCount(): int
    {
        return $this->beds->where('status', BedStatus::Maintenance)->count();
    }

    public function bedCapacity(): int
    {
        return $this->sharing_type->capacity();
    }

    public function occupancyPercent(): float
    {
        $total = $this->beds->count();

        return $total === 0
            ? 0.0
            : round($this->occupiedBedCount() / $total * 100, 1);
    }

    /**
     * Recomputes the derived room status from its current bed states and
     * persists it only when it actually changed (avoids write amplification
     * during bulk meter/booking operations).
     */
    public function syncStatusFromBeds(): void
    {
        if ($this->status === RoomStatus::Maintenance) {
            return;
        }

        $beds = $this->beds()->get();

        $derived = match (true) {
            $beds->isEmpty() => RoomStatus::Available,
            $beds->every(fn (Bed $bed): bool => $bed->status === BedStatus::Maintenance) => RoomStatus::Maintenance,
            $beds->where('status', BedStatus::Occupied)->count() >= $beds->count() => RoomStatus::Full,
            $beds->where('status', BedStatus::Occupied)->count() > 0 => RoomStatus::Partial,
            default => RoomStatus::Available,
        };

        if ($this->status !== $derived) {
            $this->forceFill(['status' => $derived])->save();
        }
    }

    public function getBedLabelAttribute(): string
    {
        return $this->beds->pluck('bed_code')->implode(', ');
    }

    /**
     * Deterministic bed code for a position: room 101, position 2 -> "101-B".
     */
    public function bedCodeFor(int $position): string
    {
        return $this->room_number.'-'.chr(64 + $position);
    }

    /**
     * Creates any bed missing for the room's sharing capacity (idempotent).
     * Beds are never deleted here: shrinking a room would orphan history, so a
     * manager retires surplus beds explicitly (soft delete).
     *
     * @return int number of beds created
     */
    public function syncBedsToCapacity(): int
    {
        $existingPositions = $this->beds()->pluck('position')->all();
        $created = 0;

        for ($position = 1; $position <= $this->sharing_type->capacity(); $position++) {
            if (in_array($position, $existingPositions, true)) {
                continue;
            }

            $this->beds()->create([
                'bed_code' => $this->bedCodeFor($position),
                'position' => $position,
                'status' => BedStatus::Available,
            ]);

            $created++;
        }

        return $created;
    }
}
