<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Derived state of a room, always kept in sync with its beds by
 * App\Observers\BedObserver. Never set this by hand outside that flow.
 */
enum RoomStatus: string implements HasColor, HasIcon, HasLabel
{
    case Available = 'available';
    case Partial = 'partial';
    case Full = 'full';
    case Maintenance = 'maintenance';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'All Beds Free',
            self::Partial => 'Partially Occupied',
            self::Full => 'Full',
            self::Maintenance => 'Under Maintenance',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Available => 'success',
            self::Partial => 'warning',
            self::Full => 'primary',
            self::Maintenance => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::Partial => 'heroicon-o-adjustments-horizontal',
            self::Full => 'heroicon-o-lock-closed',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
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
