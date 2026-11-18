<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Reject the request when the authenticated user does not hold one of the allowed roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role->value;

        if ($role === null || ! in_array($role, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
