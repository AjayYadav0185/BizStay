<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentType: string implements HasLabel, HasColor
{
    case Rent = 'rent';
    case Deposit = 'deposit';
    case Utility = 'utility';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rent => 'success',
            self::Deposit => 'info',
            self::Utility => 'warning',
            self::Maintenance => 'gray',
            self::Other => 'purple',
        };
    }
}
