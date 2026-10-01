<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\Navigation;
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
            // Sidebar / tab-bar menu from App\Support\Navigation - only the
            // items this user's role may open. Lazy: skipped for guests.
            'navigation' => fn () => $request->user() ? Navigation::for($request->user(), $request) : null,
            // Whether "Find address" can be offered (FINDADDRESS_API_KEY set).
            'addressLookup' => fn () => $request->user() !== null && filled(config('services.findaddress.key')),
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
