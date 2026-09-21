<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum RoomStatus: string implements HasLabel, HasColor, HasIcon
{
    case Available = 'available';
    case Full = 'full';
    case Maintenance = 'maintenance';

    public function getLabel(): ?string
    {
        return $this->value === 'full' ? 'Full' : ucfirst($this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Available => 'success',
            self::Full => 'warning',
            self::Maintenance => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Available => 'heroicon-o-check-circle',
            self::Full => 'heroicon-o-no-symbol',
            self::Maintenance => 'heroicon-o-wrench-screwdriver',
        };
    }
}
