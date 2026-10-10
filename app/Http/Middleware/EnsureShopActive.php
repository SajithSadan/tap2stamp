<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer routes for one shop ({shop} in the URL): a shop the admin has
 * blocked shows "this card isn't active" instead of the card, and its JSON
 * endpoints (register, card state, review) answer 403 `shop_inactive`.
 * Not a 404 - the card page only forgets a customer's id on a 404, and
 * their cards at other shops must keep working.
 */
class EnsureShopActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->route('shop');

        // The admin can still open the "customer page" preview to check the look.
        $adminPreview = $request->boolean('preview') && $request->user()?->isAdmin();

        if ($shop instanceof Shop && $shop->isBlocked() && ! $adminPreview) {
            return self::inactive($request, $shop);
        }

        return $next($request);
    }

    public static function inactive(Request $request, Shop $shop): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'status' => 'error',
                'code' => 'shop_inactive',
                'message' => "{$shop->name}'s loyalty card isn't active at the moment.",
            ], 403);
        }

        return Inertia::render('ShopInactive', ['shopName' => $shop->name])
            ->toResponse($request)
            ->setStatusCode(403);
    }
}
