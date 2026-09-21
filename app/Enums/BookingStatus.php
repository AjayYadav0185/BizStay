<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Status of a stay contract (a booking ties one guest to one bed).
 */
enum BookingStatus: string implements HasColor, HasIcon, HasLabel
{
    case Active = 'active';
    case NoticePeriod = 'notice_period';
    case CheckedOut = 'checked_out';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::NoticePeriod => 'Notice Period',
            self::CheckedOut => 'Checked Out',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active => 'success',
            self::NoticePeriod => 'warning',
            self::CheckedOut => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Active => 'heroicon-o-check-circle',
            self::NoticePeriod => 'heroicon-o-clock',
            self::CheckedOut => 'heroicon-o-arrow-right-on-rectangle',
        };
    }

    /**
     * A live stay: the guest physically occupies the bed and is billed.
     */
    public function occupiesBed(): bool
    {
        return in_array($this, [self::Active, self::NoticePeriod], true);
    }

    /**
     * Whether recurring monthly invoices should still be raised.
     */
    public function isBillable(): bool
    {
        return $this->occupiesBed();
    }

    /**
     * Whether the guest can still be checked out of this booking.
     */
    public function canCheckOut(): bool
    {
        return $this->occupiesBed();
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
