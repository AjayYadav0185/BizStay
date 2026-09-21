<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Head under which money is collected. Invoices are settled by any mix of
 * these; the ledger keeps the split so reports stay meaningful.
 */
enum PaymentType: string implements HasColor, HasLabel
{
    case Rent = 'rent';
    case Utility = 'utility';
    case Maintenance = 'maintenance';
    case Food = 'food';
    case Deposit = 'deposit';
    case Settlement = 'settlement';
    case Refund = 'refund';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rent => 'Rent',
            self::Utility => 'Utility',
            self::Maintenance => 'Maintenance',
            self::Food => 'Food / Mess',
            self::Deposit => 'Security Deposit',
            self::Settlement => 'Check-out Settlement',
            self::Refund => 'Refund',
            self::Other => 'Other',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Rent => 'success',
            self::Utility => 'warning',
            self::Maintenance => 'gray',
            self::Food => 'info',
            self::Deposit => 'primary',
            self::Settlement => 'purple',
            self::Refund => 'danger',
            self::Other => 'gray',
        };
    }

    /**
     * Money flowing back to the guest rather than into the business.
     */
    public function isOutflow(): bool
    {
        return $this === self::Refund;
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
