<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum BedStatus: string implements HasLabel, HasColor, HasIcon
{
    case Vacant = 'vacant';
    case Occupied = 'occupied';
    case Blocked = 'blocked';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Vacant => 'success',
            self::Occupied => 'info',
            self::Blocked => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Vacant => 'heroicon-o-check-circle',
            self::Occupied => 'heroicon-o-user',
            self::Blocked => 'heroicon-o-no-symbol',
        };
    }
}
