<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin → "View as owner": while the session holds a shop id, the owner
 * dashboard (/dashboard/*) is served as that shop's owner, so the admin sees
 * exactly the owner's screens. Read-only unless the admin switches on
 * "Allow changes" (EDIT_KEY) - e.g. to add staff or a device for an owner
 * who isn't technical. Paying and stamping stay with the owner even then.
 * Every change made this way is in the activity log, as the admin who made it.
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

    /** The shop last viewed, kept after "Back to admin" so a stale owner page leads back to it. */
    public const ENDED_KEY = 'view_as_ended_shop_id';

    /**
     * Never as the owner, even with changes allowed: the owner would be
     * charged (Stripe checkout - use Admin → Record order instead) or
     * credited with stamps they didn't give - or customers would get a
     * WhatsApp offer the owner never chose to send.
     */
    private const OWNER_ONLY = [
        'dashboard.orders.checkout',
        // Plan payments: the owner pays with their own card / UPI.
        'dashboard.plan.pay',
        'dashboard.plan.pay.confirm',
        'dashboard.orders.coupon',
        'dashboard.scan',
        'dashboard.marketing.store',
        'dashboard.marketing.send',
        // The owner connects their own number (they fill in Meta's forms).
        'dashboard.whatsapp.connect',
        'dashboard.whatsapp.connect-keys',
        'dashboard.whatsapp.disconnect',
        // Customers would get a reply the owner never wrote.
        'dashboard.whatsapp.reply',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $shopId = $request->session()->get(self::SESSION_KEY);

        if (! $request->routeIs('dashboard.*')) {
            return $next($request);
        }

        // An owner page opened after "Back to admin" (browser Back, another tab):
        // back to that shop's settings rather than role:owner's bare 403.
        if (! $shopId) {
            $ended = $request->session()->get(self::ENDED_KEY);

            return $ended && $request->user()?->role === UserRole::Admin && $request->isMethodSafe() && ! $request->expectsJson()
                ? redirect()->route('admin.shops.settings.edit', $ended)->with('status', "You're no longer viewing as the owner. Choose View as owner to open their dashboard again.")
                : $next($request);
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
                $request->routeIs(self::OWNER_ONLY) => 'Only the owner can pay for orders, give stamps, or connect WhatsApp and send offers. Use Admin → Record order for an order.',
                default => null,
            };

            if ($refusal) {
                return $request->expectsJson() && ! $request->header('X-Inertia')
                    ? response()->json(['message' => $refusal], 403)
                    : back()->withErrors(['view_as' => $refusal]); // an error, so forms don't say "Saved"
            }
        }

        // Read by ActivityLogger: every change made this way is recorded as this admin ("as owner").
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
