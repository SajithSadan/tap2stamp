<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `feature:menu` on owner routes: 404 unless the signed-in owner's shop has
 * that feature switched on (App\Support\Features - platform default, or the
 * admin's override for this shop).
 */
class EnsureShopFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Features::exists($feature) && $request->user()?->shop?->hasFeature($feature), 404);

        return $next($request);
    }
}
