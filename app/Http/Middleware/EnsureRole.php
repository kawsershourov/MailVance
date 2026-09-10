<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind one or more role slugs.
 *
 * Usage: ->middleware('role:super-admin')
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403, 'This area is restricted.');
        }

        return $next($request);
    }
}
