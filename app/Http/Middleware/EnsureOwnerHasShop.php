<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every owner dashboard route reads auth()->user()->shop. A self-service
 * owner has none until they finish shop setup, so send them there first.
 * A shop the admin has blocked gets a "your shop is paused" page instead of
 * the dashboard (and every change is refused) - except for an admin viewing
 * it as the owner, who still sees the real screens.
 */
class EnsureOwnerHasShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->user()->shop()->first();

        if (! $shop) {
            return redirect()->route('onboarding.create');
        }

        if ($shop->isBlocked() && ! $request->attributes->get('viewAsAdmin')) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['code' => 'shop_blocked', 'message' => 'Your shop is paused.'], 403);
            }

            return Inertia::render('Dashboard/Blocked', ['shopName' => $shop->name])
                ->toResponse($request)
                ->setStatusCode(403);
        }

        return $next($request);
    }
}
