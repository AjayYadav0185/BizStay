<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ComplaintPriority: string implements HasLabel, HasColor, HasIcon
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'warning',
            self::High => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Low => 'heroicon-o-arrow-down',
            self::Medium => 'heroicon-o-minus',
            self::High => 'heroicon-o-arrow-up',
        };
    }
}
