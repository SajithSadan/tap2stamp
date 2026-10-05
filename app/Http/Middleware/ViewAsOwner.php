<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin → "View as owner": while the session holds a shop id, the owner
 * dashboard (/dashboard/*) is served as that shop's owner, so the admin sees
 * exactly the owner's screens. Read-only unless the admin switches on
 * "Allow changes" (EDIT_KEY) - e.g. to add staff or a device for an owner
 * who isn't technical. Paying and stamping stay with the owner even then.
 * Every change made this way is logged with the admin who made it.
 *
 * The admin stays signed in as the admin: the owner is only swapped in for
 * the current request (never written to the session), only on dashboard
 * routes, so the admin panel keeps working and the owner's own sessions
 * are untouched.
 */
class ViewAsOwner
{
    public const SESSION_KEY = 'view_as_shop_id';

    public const EDIT_KEY = 'view_as_editing';

    /**
     * Never as the owner, even with changes allowed: the owner would be
     * charged (Stripe checkout - use Admin → Record order instead) or
     * credited with stamps they didn't give - or customers would get a
     * WhatsApp offer the owner never chose to send.
     */
    private const OWNER_ONLY = [
        'dashboard.orders.checkout',
        'dashboard.orders.coupon',
        'dashboard.scan',
        'dashboard.marketing.store',
        'dashboard.marketing.send',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $shopId = $request->session()->get(self::SESSION_KEY);

        if (! $shopId || ! $request->routeIs('dashboard.*')) {
            return $next($request);
        }

        $admin = $request->user();
        $owner = Shop::with('owner')->find($shopId)?->owner;

        // The admin signed out / lost the role, or the shop or its owner is gone.
        if ($admin?->role !== UserRole::Admin || $owner?->role !== UserRole::Owner) {
            $request->session()->forget(self::SESSION_KEY);

            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            $refusal = match (true) {
                ! $request->session()->get(self::EDIT_KEY) => "You're viewing as the owner (read-only), so nothing was changed. Switch on \"Allow changes\" to edit.",
                $request->routeIs(self::OWNER_ONLY) => 'Only the owner can pay for orders, give stamps or send WhatsApp offers. Use Admin → Record order for an order.',
                default => null,
            };

            if ($refusal) {
                return $request->expectsJson() && ! $request->header('X-Inertia')
                    ? response()->json(['message' => $refusal], 403)
                    : back()->withErrors(['view_as' => $refusal]); // an error, so forms don't say "Saved"
            }

            Log::info('Admin changed a shop as its owner', [
                'admin_id' => $admin->id,
                'shop_id' => $shopId,
                'action' => $request->method().' '.$request->path(),
            ]);
        }

        $request->attributes->set('viewAsAdmin', $admin);
        Auth::setUser($owner);

        try {
            return $next($request);
        } finally {
            // Only for this request: never leave the owner behind in a
            // long-lived process (or the next request in a test).
            Auth::setUser($admin);
        }
    }
}
