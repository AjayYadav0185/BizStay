<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Ledger rows (invoices, payments) are evidence. Only the owner/admin login
 * may delete them; everyone else corrects with a new entry.
 */
trait LedgerDeletesAdminOnly
{
    public static function canDelete(Model $record): bool
    {
        return static::currentUserIsAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return static::currentUserIsAdmin();
    }

    protected static function currentUserIsAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin();
    }
}
