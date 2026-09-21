<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasLabel, HasColor, HasIcon
{
    case New = 'new';
    case Contacted = 'contacted';
    case Visited = 'visited';
    case Booked = 'booked';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::Visited => 'purple',
            self::Booked => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::New => 'heroicon-o-sparkles',
            self::Booked => 'heroicon-o-check-circle',
            self::Cancelled => 'heroicon-o-x-circle',
            default => 'heroicon-o-phone',
        };
    }
}
