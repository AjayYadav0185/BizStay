<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ExpenseCategory: string implements HasLabel, HasColor
{
    case Electricity = 'electricity';
    case Water = 'water';
    case Internet = 'internet';
    case Housekeeping = 'housekeeping';
    case Repairs = 'repairs';
    case Gas = 'gas';
    case Salary = 'salary';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Electricity => 'warning',
            self::Water => 'info',
            self::Internet => 'primary',
            self::Housekeeping => 'success',
            self::Repairs => 'danger',
            default => 'gray',
        };
    }
}
