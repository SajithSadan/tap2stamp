<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces App\Support\Navigation's `roles` on the menu pages themselves, so
 * a page is blocked for exactly the roles whose menu doesn't show it. Routes
 * that aren't menu pages (form posts, JSON endpoints) pass straight through
 * to their own guards.
 */
class EnsureNavigationAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $roles = Navigation::rolesFor($request->route()?->getName());

        abort_if($roles !== null && ! in_array($request->user()?->role, $roles, true), 403);

        return $next($request);
    }
}
