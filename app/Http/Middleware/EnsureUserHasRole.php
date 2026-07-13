<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware role-check per route group (section 8 desain MVP: "Middleware
 * role-check tegas per route group (admin, tutor, siswa)").
 *
 * Pemakaian di route: ->middleware('role:tutor,admin')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki izin untuk mengakses resource ini.');
        }

        return $next($request);
    }
}
