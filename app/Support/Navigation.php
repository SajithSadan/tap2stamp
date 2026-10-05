<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The single place that lists every dashboard page and WHO may open it.
 *
 * - The sidebars and mobile tab bars (AdminLayout, OwnerLayout) are built
 *   from this, shared to every page as the `navigation` prop - only the
 *   items the signed-in user's role allows are ever sent.
 * - The same `roles` are enforced on the pages themselves by the
 *   `nav.access` middleware (EnsureNavigationAccess), so hiding a menu item
 *   and blocking its page can't drift apart.
 *
 * To add a page: add its route, add an entry here, and map its `icon` key in
 * resources/js/lib/navIcons.js. Tests (NavigationTest) check every entry is
 * reachable by exactly its roles.
 */
class Navigation
{
    /**
     * Every entry:
     *  - route:   route name the item links to (also its identity)
     *  - label:   menu text
     *  - icon:    key in resources/js/lib/navIcons.js
     *  - roles:   who may see it AND open its page
     *  - active:  route patterns that highlight it (default: its own route)
     *  - section: 'main' (the menu) or 'footer' (the sidebar's bottom block)
     *  - group:   optional section heading
     *  - href:    optional closure(User): ?string for links that aren't a
     *             plain route (null hides the item)
     *  - external: opens in a new tab and isn't access-checked here
     *
     * @return list<array<string, mixed>>
     */
    public static function items(): array
    {
        $admin = [UserRole::Admin];
        $owner = [UserRole::Owner];

        return [
            // --- Admin console -------------------------------------------
            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'overview', 'roles' => $admin],
            // "Add shop" is a button on this page, not its own menu item.
            ['route' => 'admin.index', 'label' => 'Shops', 'icon' => 'shops', 'roles' => $admin, 'active' => ['admin.index', 'admin.shops.*']],
            // "Products" and "Coupons" are buttons on this page, not their own menu items.
            ['route' => 'admin.orders.index', 'label' => 'Orders', 'icon' => 'orders', 'roles' => $admin, 'active' => ['admin.orders.*', 'admin.products.*', 'admin.coupons.*']],
            ['route' => 'admin.qr-codes.index', 'label' => 'QR codes', 'icon' => 'qr', 'roles' => $admin, 'active' => ['admin.qr-codes.*']],
            ['route' => 'admin.settings', 'label' => 'Settings', 'icon' => 'settings', 'roles' => $admin, 'active' => ['admin.settings*']],

            // --- Owner dashboard -----------------------------------------
            ['route' => 'dashboard.index', 'label' => 'Overview', 'icon' => 'overview', 'roles' => $owner, 'mobile_primary' => true],
            ['route' => 'dashboard.customers', 'label' => 'Customers', 'icon' => 'customers', 'roles' => $owner, 'mobile_primary' => true],
            ['route' => 'dashboard.insights', 'label' => 'Insights', 'icon' => 'insights', 'roles' => $owner],
            ['route' => 'dashboard.activity', 'label' => 'Activity', 'icon' => 'activity', 'roles' => $owner],
            ['route' => 'dashboard.reviews', 'label' => 'Reviews', 'icon' => 'reviews', 'roles' => $owner],
            ['route' => 'dashboard.marketing', 'label' => 'WhatsApp', 'icon' => 'whatsapp', 'roles' => $owner],
            ['route' => 'dashboard.staff', 'label' => 'Staff', 'icon' => 'staff', 'roles' => $owner, 'mobile_primary' => true],
            ['route' => 'dashboard.orders', 'label' => 'Orders', 'icon' => 'orders', 'roles' => $owner, 'active' => ['dashboard.orders*']],
            ['route' => 'dashboard.theme', 'label' => 'Theme', 'icon' => 'theme', 'roles' => $owner, 'active' => ['dashboard.theme*']],
            ['route' => 'dashboard.settings', 'label' => 'Settings', 'icon' => 'settings', 'roles' => $owner, 'active' => ['dashboard.settings*']],
            [
                'route' => 'card.show',
                'label' => 'Customer page',
                'icon' => 'external',
                'roles' => $owner,
                'section' => 'footer',
                'external' => true,
                // Only when the admin allows this shop to see its card link.
                'href' => fn (User $user) => $user->shop?->show_card_link ? route('card.show', $user->shop) : null,
            ],
        ];
    }

    /**
     * The menu for one user, ready for the frontend: only their role's items,
     * with resolved links and which one is active on this request.
     *
     * @return array{main: list<array<string, mixed>>, footer: list<array<string, mixed>>}
     */
    public static function for(User $user, Request $request): array
    {
        $menu = ['main' => [], 'footer' => []];

        foreach (self::items() as $item) {
            if (! in_array($user->role, $item['roles'], true)) {
                continue;
            }

            $href = isset($item['href']) ? ($item['href'])($user) : route($item['route']);

            if ($href === null) {
                continue;
            }

            $menu[$item['section'] ?? 'main'][] = [
                'label' => $item['label'],
                'href' => $href,
                'icon' => $item['icon'],
                'group' => $item['group'] ?? null,
                'external' => $item['external'] ?? false,
                'active' => $request->routeIs(...($item['active'] ?? [$item['route']])),
                'mobile_primary' => $item['mobile_primary'] ?? false,
            ];
        }

        return $menu;
    }

    /**
     * Roles allowed to open a (non-external) menu page, or null when the
     * route isn't a menu page - those are left to their route's own guards.
     *
     * @return list<UserRole>|null
     */
    public static function rolesFor(?string $routeName): ?array
    {
        foreach (self::items() as $item) {
            if (! ($item['external'] ?? false) && $item['route'] === $routeName) {
                return $item['roles'];
            }
        }

        return null;
    }
}
