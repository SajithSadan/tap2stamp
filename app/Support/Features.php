<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Builder;

/**
 * Owner features the admin can switch on or off: a platform default
 * (Admin → Settings → Features, Setting::FEATURES) and, per shop, an
 * override (shops.features; Admin → shop settings). Read a shop's answer
 * with Shop::hasFeature(). A feature that's off is gone from the owner's
 * menu, its owner routes 404 (the `feature:` middleware) and, for the menu,
 * the public menu page too.
 */
final class Features
{
    public const MENU = 'menu';

    public const WHATSAPP = 'whatsapp';

    public const ALL = [
        self::MENU => [
            'label' => 'Menu',
            'description' => 'The menu editor (incl. finding and uploading photos, reading a printed menu) and the public menu page.',
        ],
        self::WHATSAPP => [
            'label' => 'WhatsApp',
            'description' => 'WhatsApp offers to customers who opted in. Unsubscribe links always keep working.',
        ],
    ];

    /** The platform default. Never set = on, so adding this switch changed nothing for anyone. */
    public static function default(string $feature): bool
    {
        return (bool) ((Setting::get(Setting::FEATURES) ?? [])[$feature] ?? true);
    }

    public static function setDefault(string $feature, bool $on): void
    {
        Setting::set(Setting::FEATURES, [...(Setting::get(Setting::FEATURES) ?? []), $feature => $on]);
    }

    /** Shops already using the feature - kept on when its default is switched off. */
    public static function inUse(string $feature): Builder
    {
        return match ($feature) {
            self::MENU => Shop::query()->whereHas('menuSections'),
            self::WHATSAPP => Shop::query()->whereHas('marketingCampaigns'),
        };
    }

    public static function exists(string $feature): bool
    {
        return isset(self::ALL[$feature]);
    }
}
