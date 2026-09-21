<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel, HasColor, HasIcon
{
    case Paid = 'paid';
    case Pending = 'pending';
    case Overdue = 'overdue';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Paid => 'success',
            self::Pending => 'warning',
            self::Overdue => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Paid => 'heroicon-o-check-circle',
            self::Pending => 'heroicon-o-clock',
            self::Overdue => 'heroicon-o-exclamation-triangle',
        };
    }
}
