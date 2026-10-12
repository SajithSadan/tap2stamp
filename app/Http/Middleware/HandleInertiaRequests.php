<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Support\Industries;
use App\Support\Navigation;
use App\Support\WelcomeMessage;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role->value,
                ] : null,
            ],
            // Admin → "View as owner": who is really looking, at which shop, and whether changes are allowed.
            'viewAs' => fn () => ($admin = $request->attributes->get('viewAsAdmin')) ? [
                'admin_name' => $admin->name,
                'shop_name' => $request->user()->shop?->name,
                'owner_name' => $request->user()->name,
                'editing' => (bool) $request->session()->get(ViewAsOwner::EDIT_KEY),
            ] : null,
            // Sidebar / tab-bar menu from App\Support\Navigation - only the
            // items this user's role may open. Lazy: skipped for guests.
            'navigation' => fn () => $request->user() ? Navigation::for($request->user(), $request) : null,
            // Whether "Find address" can be offered (FINDADDRESS_API_KEY set).
            'addressLookup' => fn () => $request->user() !== null && filled(config('services.findaddress.key')),
            // Owners of shops set up before sign-up asked: the one-time "What kind
            // of business?" popup (OwnerLayout). Never for an admin viewing as owner.
            'industryPrompt' => fn () => $request->routeIs('dashboard.*')
                && $request->user()?->role === UserRole::Owner
                && ! $request->attributes->get('viewAsAdmin')
                && ($shop = $request->user()->shop) !== null
                && $shop->industry === null
                    ? ['industries' => Industries::options()]
                    : null,
            // Admin: the WhatsApp button's "Welcome message" text (Admin → Settings → WhatsApp).
            'whatsappWelcome' => fn () => $request->user()?->role === UserRole::Admin ? WelcomeMessage::text() : null,
            // Admin → Settings sidebar colours ({bg, text}, null = default look).
            'sidebarColors' => fn () => $request->user() ? Setting::get(Setting::SIDEBAR_COLORS) : null,
            // One-time reveal values (generated owner password, staff device
            // token) are flashed to the session rather than stored, so a page
            // refresh never shows them a second time.
            'flash' => [
                'generatedPassword' => $request->session()->get('generatedPassword'),
                'createdOwnerEmail' => $request->session()->get('createdOwnerEmail'),
                'staffToken' => $request->session()->get('staffToken'),
                // Plain one-line success message after an admin action.
                'status' => $request->session()->get('status'),
            ],
        ];
    }
}
