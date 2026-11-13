<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoints shared by staff and tenants. A tenant login must be linked to a
 * guest record (and active) — otherwise tenant scoping would have nothing to
 * filter by and the request is refused instead of falling through to "all".
 */
class EnsureTenantLinked
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isActive(), 403, 'This login is disabled.');

        if ($user->isTenant()) {
            abort_if($user->guest_id === null, 403, 'This login is not linked to a resident.');
        }

        return $next($request);
    }
}
