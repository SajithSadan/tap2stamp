<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\ShopMenu;
use App\Support\Features;
use App\Support\StampIcons;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /**
     * The public menu page, opened from a QR sticker mapped to it. By the
     * shop's menu_slug, never the card slug, so the URL doesn't give owners
     * their card link (see "Owners don't get their card link"). Old /menu/{id}
     * links (stickers mapped before menu links existed) redirect.
     */
    public function show(string $menuSlug, ShopMenu $menu): Response|RedirectResponse
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

        return Inertia::render('Menu', [
            'shop' => self::header($shop),
            'sections' => $menu->sections($shop),
            // The menu theme the admin chose (colours, fonts and layout).
            'theme' => $shop->menuTheme(),
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
