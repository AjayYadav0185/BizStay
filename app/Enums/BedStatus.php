<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Lifecycle state of a physical bed.
 *
 * The state machine lives in App\Observers\BookingObserver:
 *  - booking activated / guest checked in -> Occupied
 *  - booking on notice period             -> Occupied (guest is still paying)
 *  - booking checked out / cancelled      -> Available
 *  - maintenance blocks allocation entirely
 *  - reserved holds the bed for a booking whose check-in is in the future
 */
enum BedStatus: string implements HasColor, HasIcon, HasLabel
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Maintenance = 'maintenance';
    case Reserved = 'reserved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Occupied => 'Occupied',
            self::Maintenance => 'Maintenance',
            self::Reserved => 'Reserved',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Available => 'success',
            self::Occupied => 'info',
            self::Maintenance => 'danger',
            self::Reserved => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::Occupied => 'heroicon-o-user',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
            self::Reserved => 'heroicon-o-bookmark',
        };
    }

    /**
     * Whether the bed can be handed over to a new guest right now.
     */
    public function isAllocatable(): bool
    {
        return in_array($this, [self::Available, self::Reserved], true);
    }

    /**
     * Whether the bed is generating revenue (used by occupancy metrics).
     */
    public function isRevenueBearing(): bool
    {
        return $this === self::Occupied;
    }

    /**
     * Hex colour used by the visual bed-stack badge on the room grid.
     */
    public function hex(): string
    {
        return match ($this) {
            self::Available => '#22c55e',
            self::Occupied => '#3b82f6',
            self::Maintenance => '#ef4444',
            self::Reserved => '#f59e0b',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
