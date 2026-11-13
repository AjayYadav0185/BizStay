<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manager-side API endpoints (every guest, bed, lead and the P&L) are for
 * active staff only. Pass "finance" to further restrict to admin/manager.
 */
class EnsureStaff
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $level = null): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isStaff() && $user->isActive(), 403, 'Staff access only.');

        if ($level === 'finance') {
            abort_unless($user->canManageFinance(), 403, 'Manager access only.');
        }

        return $next($request);
    }
}
