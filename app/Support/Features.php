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

    /** Customers may collect more than one stamp a day (StampService: a short gap instead of the usual wait). */
    public const MULTIPLE_STAMPS = 'multiple_stamps';

    /**
     * `default`: what applies while the admin never set the platform default -
     * chosen so adding a switch changed nothing for anyone. `keep_in_use`:
     * switching the default off keeps it on for shops already using it.
     */
    public const ALL = [
        self::MENU => [
            'label' => 'Menu',
            'description' => 'The menu editor (incl. finding and uploading photos, reading a printed menu) and the public menu page.',
            'default' => true,
            'keep_in_use' => true,
        ],
        self::WHATSAPP => [
            'label' => 'WhatsApp',
            'description' => 'WhatsApp offers to customers who opted in. Unsubscribe links always keep working.',
            'default' => true,
            'keep_in_use' => true,
        ],
        self::MULTIPLE_STAMPS => [
            'label' => 'Multiple stamps a day',
            'description' => 'Customers can collect more than one stamp a day, a couple of minutes apart (so a double scan never counts twice). Off: one stamp, then the usual wait.',
            'default' => false,
            'keep_in_use' => false,
        ],
    ];

    /** The platform default, or the feature's own starting value until the admin sets one. */
    public static function default(string $feature): bool
    {
        return (bool) ((Setting::get(Setting::FEATURES) ?? [])[$feature] ?? self::ALL[$feature]['default'] ?? true);
    }

    public static function setDefault(string $feature, bool $on): void
    {
        Setting::set(Setting::FEATURES, [...(Setting::get(Setting::FEATURES) ?? []), $feature => $on]);
    }

    /** Shops already using the feature - kept on when its default is switched off. Null = not tracked. */
    public static function inUse(string $feature): ?Builder
    {
        return match ($feature) {
            self::MENU => Shop::query()->whereHas('menuSections'),
            self::WHATSAPP => Shop::query()->whereHas('marketingCampaigns'),
            default => null,
        };
    }

    public static function exists(string $feature): bool
    {
        return isset(self::ALL[$feature]);
    }
}
