<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Tenant police verification — mandatory for PG operators in Delhi
 * (Delhi Police), Haryana (Harsamay) and UP (UPCOP).
 */
enum PoliceVerificationStatus: string implements HasColor, HasIcon, HasLabel
{
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotSubmitted => 'Not submitted',
            self::Submitted => 'Submitted',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NotSubmitted => 'danger',
            self::Submitted => 'warning',
            self::Verified => 'success',
            self::Rejected => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NotSubmitted => 'heroicon-o-exclamation-triangle',
            self::Submitted => 'heroicon-o-clock',
            self::Verified => 'heroicon-o-shield-check',
            self::Rejected => 'heroicon-o-x-circle',
        };
    }

    /**
     * Still needs action from the house.
     */
    public function isPending(): bool
    {
        return in_array($this, [self::NotSubmitted, self::Submitted], true);
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
