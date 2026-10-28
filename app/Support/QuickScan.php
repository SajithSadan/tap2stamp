<?php

namespace App\Support;

use App\Models\Shop;

/**
 * Quick scan (Features::QUICK_SCAN): scanning the shop's own QR - a sticker or
 * counter display (/qr/{code}) or the Settings counter QR (/s/{slug}?scan=1) -
 * opens the card page with a short-lived signed `tap` token, and only a page
 * opened with one may stamp itself. Opening the card any other way (My Cards,
 * the bottom nav, the home-screen app, history) never stamps.
 *
 * Token = "{expires}.{signature}", signed with the app key for one shop. A
 * photo of the printed QR still works from anywhere; the stamp's usual wait
 * is what limits that to one stamp per visit window.
 */
final class QuickScan
{
    public static function enabled(Shop $shop): bool
    {
        return $shop->hasFeature(Features::QUICK_SCAN);
    }

    public static function token(Shop $shop): string
    {
        $expires = now()->addMinutes(config('loyalty.quick_scan_link_minutes'))->getTimestamp();

        return $expires.'.'.self::sign($shop, $expires);
    }

    public static function valid(Shop $shop, ?string $token): bool
    {
        if (! $token || ! preg_match('/^(\d{10})\.([a-f0-9]{40})$/', $token, $m)) {
            return false;
        }

        return (int) $m[1] >= now()->getTimestamp() && hash_equals(self::sign($shop, (int) $m[1]), $m[2]);
    }

    /** The card page a scan lands on: with a token when Quick scan is on, else the plain card link. */
    public static function landingUrl(Shop $shop): string
    {
        return route('card.show', ['shop' => $shop->slug, ...(self::enabled($shop) ? ['tap' => self::token($shop)] : [])]);
    }

    /** Is this /qr/{code} destination exactly this site's card link for a shop? Returns the shop, else null. */
    public static function shopForDestination(?string $url): ?Shop
    {
        if (! $url || strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower(request()->getHost())) {
            return null;
        }

        if (! preg_match('~^/s/([^/]+)/?$~', parse_url($url, PHP_URL_PATH) ?: '', $m) || parse_url($url, PHP_URL_QUERY)) {
            return null;
        }

        return Shop::where('slug', rawurldecode($m[1]))->first();
    }

    private static function sign(Shop $shop, int $expires): string
    {
        return substr(hash_hmac('sha256', "quick-scan|{$shop->id}|{$expires}", config('app.key')), 0, 40);
    }
}
