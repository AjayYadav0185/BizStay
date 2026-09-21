<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Settlement state of an invoice. Recomputed atomically by
 * App\Observers\PaymentObserver whenever money lands against it.
 */
enum InvoiceStatus: string implements HasColor, HasIcon, HasLabel
{
    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Unpaid => 'warning',
            self::PartiallyPaid => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Unpaid => 'heroicon-o-document-currency-rupee',
            self::PartiallyPaid => 'heroicon-o-adjustments-horizontal',
            self::Paid => 'heroicon-o-check-badge',
            self::Overdue => 'heroicon-o-exclamation-triangle',
        };
    }

    /**
     * Whether the invoice still expects money.
     */
    public function isOutstanding(): bool
    {
        return $this !== self::Paid;
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
