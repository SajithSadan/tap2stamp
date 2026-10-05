<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\ShopMenu;
use App\Support\StampIcons;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /**
     * The public menu page, opened from a QR sticker mapped to it. By shop id,
     * not slug, so the URL doesn't give owners their card link (see
     * "Owners don't get their card link").
     */
    public function show(Shop $shop, ShopMenu $menu): Response
    {
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
