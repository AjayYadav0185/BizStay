<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cheque = 'cheque';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Upi => 'UPI',
            self::BankTransfer => 'Bank Transfer',
            self::Card => 'Card',
            self::Cheque => 'Cheque',
        };
    }
}
