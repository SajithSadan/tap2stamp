<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureShopActive;
use App\Models\Shop;
use App\Services\MenuBanners;
use App\Services\ShopMenu;
use App\Support\Features;
use App\Support\StampIcons;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MenuController extends Controller
{
    /**
     * The public menu page, opened from a QR sticker mapped to it. By the
     * shop's menu_slug, never the card slug, so the URL doesn't give owners
     * their card link (see "Owners don't get their card link"). Old /menu/{id}
     * links (stickers mapped before menu links existed) redirect.
     */
    public function show(string $menuSlug, ShopMenu $menu): Response|RedirectResponse|SymfonyResponse
    {
        $shop = Shop::where('menu_slug', $menuSlug)->first();

        if (! $shop && ctype_digit($menuSlug)) {
            $old = Shop::find((int) $menuSlug);
            abort_unless($old, 404);

            // 302, not 301: browsers would cache a 301 forever.
            return redirect()->to($old->menuUrl());
        }

        // Menu switched off for this shop (Admin → Features): not available.
        abort_unless($shop && $shop->hasFeature(Features::MENU), 404);

        if ($shop->isBlocked()) {
            return EnsureShopActive::inactive(request(), $shop);
        }

        return Inertia::render('Menu', [
            'shop' => self::header($shop),
            // Hidden items aren't sent at all; unavailable ones are, greyed out.
            'sections' => $menu->publicSections($shop),
            // The menu theme the admin chose (colours, fonts and layout).
            'theme' => $shop->menuTheme(),
            // The promotion carousel above the menu.
            'banners' => app(MenuBanners::class)->forClient($shop),
        ]);
    }

    /** What the menu page header shows (also the admin's live preview). */
    public static function header(Shop $shop): array
    {
        return [
            'name' => $shop->name,
            'stamp_icon' => StampIcons::resolve($shop->stamp_icon),
            'banner_url' => $shop->bannerUrl(),
            'logo_url' => $shop->logoUrl(),
            'header_style' => $shop->headerStyle(),
        ];
    }
}
