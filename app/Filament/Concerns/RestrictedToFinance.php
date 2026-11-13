<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\User;

/**
 * Hides a resource from staff / wardens: only admin and manager logins can
 * see owner money (expenses, P&L, settings, reports).
 */
trait RestrictedToFinance
{
    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManageFinance();
    }
}
