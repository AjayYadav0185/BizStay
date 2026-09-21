<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case Upi = 'upi';
    case NetBanking = 'netbanking';
    case Cash = 'cash';
    case Card = 'card';
    case Cheque = 'cheque';

    /**
     * Internal instrument: the security deposit is applied against dues at
     * check-out. No money moves, so it is tracked separately from cash/UPI.
     */
    case DepositAdjustment = 'deposit_adjustment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::NetBanking => 'Net Banking',
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
            self::DepositAdjustment => 'Deposit Adjustment',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Upi => 'info',
            self::NetBanking => 'primary',
            self::Cash => 'success',
            self::Card => 'purple',
            self::Cheque => 'gray',
            self::DepositAdjustment => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Upi => 'heroicon-o-device-phone-mobile',
            self::NetBanking => 'heroicon-o-building-library',
            self::Cash => 'heroicon-o-banknotes',
            self::Card => 'heroicon-o-credit-card',
            self::Cheque => 'heroicon-o-document-text',
            self::DepositAdjustment => 'heroicon-o-arrow-path-rounded-square',
        };
    }

    /**
     * Digital channels should always carry a transaction reference for audit.
     */
    public function requiresTransactionId(): bool
    {
        return in_array($this, [self::Upi, self::NetBanking, self::Card], true);
    }

    /**
     * Channels a manager can actually collect through (used in forms).
     *
     * @return array<string, string>
     */
    public static function collectionOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            if ($case === self::DepositAdjustment) {
                continue;
            }

            $options[$case->value] = $case->getLabel();
        }

        return $options;
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
