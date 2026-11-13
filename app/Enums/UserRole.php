<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Who a login belongs to.
 *
 *  Admin   — owner: everything, including staff accounts and deleting ledger rows.
 *  Manager — runs the house: billing, expenses, reports, settings.
 *  Staff   — warden / front desk: residents, visitors, complaints, collect rent.
 *  Tenant  — resident: phone app only, never the admin panel.
 */
enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';
    case Tenant = 'tenant';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Owner / Admin',
            self::Manager => 'Manager',
            self::Staff => 'Staff / Warden',
            self::Tenant => 'Tenant (app only)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Manager => 'primary',
            self::Staff => 'info',
            self::Tenant => 'gray',
        };
    }

    public function isStaff(): bool
    {
        return $this !== self::Tenant;
    }

    /**
     * Can see money that is not a single resident's bill: expenses, P&L,
     * reports, settings.
     */
    public function canManageFinance(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    /**
     * @return array<string, string>
     */
    public static function staffOptions(): array
    {
        return [
            self::Admin->value => self::Admin->getLabel(),
            self::Manager->value => self::Manager->getLabel(),
            self::Staff->value => self::Staff->getLabel(),
        ];
    }
}
