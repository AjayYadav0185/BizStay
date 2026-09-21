<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Approval state of a guest leave record. A leave with food_opt_out = true
 * produces a mess/food deduction on the next invoice.
 */
enum LeaveStatus: string implements HasColor, HasIcon, HasLabel
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Completed => 'Completed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Requested => 'info',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Completed => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Requested => 'heroicon-o-paper-airplane',
            self::Approved => 'heroicon-o-check-badge',
            self::Rejected => 'heroicon-o-x-circle',
            self::Completed => 'heroicon-o-flag',
        };
    }

    /**
     * Only approved (or completed) leaves reduce the food bill.
     */
    public function qualifiesForFoodDeduction(): bool
    {
        return in_array($this, [self::Approved, self::Completed], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }
}
