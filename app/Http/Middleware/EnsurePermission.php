<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind one or more permission slugs.
 *
 * Usage: ->middleware('permission:campaigns.create')
 *        ->middleware('permission:contacts.update|contacts.delete')  // any of
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        // A single argument may itself list alternatives with "|".
        $slugs = [];
        foreach ($permissions as $permission) {
            $slugs = array_merge($slugs, explode('|', $permission));
        }

        if (! $user->hasAnyPermission(...$slugs)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
