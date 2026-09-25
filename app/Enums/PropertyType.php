<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PropertyType: string implements HasLabel, HasColor, HasIcon
{
    case Boys = 'boys';
    case Girls = 'girls';
    case CoLive = 'colive';
    case Hotel = 'hotel';
    case PgCumHotel = 'pg_hotel';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Boys => 'Boys PG',
            self::Girls => 'Girls PG',
            self::CoLive => 'Co-Living',
            self::Hotel => 'Hotel',
            self::PgCumHotel => 'PG + Hotel',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Boys => 'info',
            self::Girls => 'danger',
            self::CoLive => 'success',
            self::Hotel => 'warning',
            self::PgCumHotel => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Boys => 'heroicon-o-user',
            self::Girls => 'heroicon-o-user-group',
            self::CoLive => 'heroicon-o-home-modern',
            self::Hotel => 'heroicon-o-building-office',
            self::PgCumHotel => 'heroicon-o-building-office-2',
        };
    }

    public function isHotel(): bool
    {
        return in_array($this, [self::Hotel, self::PgCumHotel], true);
    }
}
