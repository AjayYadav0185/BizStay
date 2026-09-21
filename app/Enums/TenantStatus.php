<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TenantStatus: string implements HasLabel, HasColor, HasIcon
{
    case Inquiry = 'inquiry';
    case Prospective = 'prospective';
    case Active = 'active';
    case NoticePeriod = 'notice_period';
    case Vacated = 'vacated';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Inquiry => 'Inquiry',
            self::Prospective => 'Prospective',
            self::Active => 'Active',
            self::NoticePeriod => 'Notice Period',
            self::Vacated => 'Vacated',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Inquiry => 'gray',
            self::Prospective => 'info',
            self::Active => 'success',
            self::NoticePeriod => 'warning',
            self::Vacated => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::NoticePeriod => 'heroicon-o-clock',
            self::Vacated => 'heroicon-o-arrow-right-on-rectangle',
            default => 'heroicon-o-user',
        };
    }
}
