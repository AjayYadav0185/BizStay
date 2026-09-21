<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ComplaintStatus: string implements HasLabel, HasColor, HasIcon
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Open => 'danger',
            self::InProgress => 'warning',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Open => 'heroicon-o-exclamation-circle',
            self::InProgress => 'heroicon-o-arrow-path',
            self::Resolved => 'heroicon-o-check-circle',
            self::Closed => 'heroicon-o-lock-closed',
        };
    }
}
