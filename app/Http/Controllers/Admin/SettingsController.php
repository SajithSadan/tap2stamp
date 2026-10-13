<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopCodeAttempt;
use App\Models\ShopCodeLock;
use App\Models\User;
use App\Services\MenuItemImages;
use App\Services\ShopCodeJoin;
use App\Support\Features;
use App\Support\WelcomeMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(MenuItemImages $images): Response
    {
        $clientId = (string) config('services.google.client_id');

        return Inertia::render('Admin/Settings', [
            'google' => [
                'enabled' => (bool) Setting::get(Setting::GOOGLE_AUTH, true),
                'configured' => GoogleAuthController::configured(),
                // Not a secret, but no need to show it whole either.
                'client_id_hint' => $clientId !== '' ? substr($clientId, 0, 12).'…' : null,
                // The exact URL to register under "Authorised redirect URIs".
                'redirect_uri' => url(config('services.google.redirect')),
                // Owners with no password can only sign in with Google.
                'google_only_owners' => User::where('role', UserRole::Owner)
                    ->whereNotNull('google_id')
                    ->whereNull('password')
                    ->count(),
            ],
            'sidebar' => Setting::get(Setting::SIDEBAR_COLORS),
            'bank' => Setting::get(Setting::BANK_DETAILS),
            'welcome' => [...WelcomeMessage::settings(), 'defaults' => ['message' => WelcomeMessage::DEFAULT_MESSAGE, 'video_url' => WelcomeMessage::DEFAULT_VIDEO]],
            // Owner features: the platform default, and how many shops the admin set otherwise.
            'features' => self::featureSummary(),
            'menuPhotos' => [
                // What's chosen (the default is AI) and what actually applies.
                'chosen' => Setting::get(Setting::MENU_PHOTO_CHECK, MenuItemImages::MODE_AI),
                'mode' => $images->mode(),
                'ai_available' => $images->aiAvailable(),
                'catalog_configured' => $images->enabled(),
            ],
            'shopCodeLocks' => self::shopCodeLocks(),
        ]);
    }

    /**
     * Staff phones (or whole networks) locked for typing too many wrong shop
     * codes (App\Services\ShopCodeJoin), newest first, to unlock.
     *
     * @return list<array<string, mixed>>
     */
    private static function shopCodeLocks(): array
    {
        return ShopCodeLock::active()->latest()->limit(200)->get()
            ->map(function (ShopCodeLock $lock) {
                $column = $lock->browser_id ? 'browser_id' : 'ip';
                $value = $lock->browser_id ?? $lock->ip;
                // The wrong codes that led to it: that day (since any earlier unlock), up to the lock.
                $from = collect([
                    $lock->created_at->copy()->startOfDay(),
                    ShopCodeLock::where($column, $value)->where('unlocked_at', '<', $lock->created_at)->max('unlocked_at'),
                ])->filter()->map(fn ($t) => Carbon::parse($t))->max();
                $wrong = ShopCodeAttempt::where($column, $value)->where('success', false)
                    ->whereBetween('created_at', [$from, $lock->created_at]);

                return [
                    'id' => $lock->id,
                    'kind' => $lock->browser_id ? 'device' : 'network',
                    'device_id' => $lock->browser_id ? ShopCodeLock::shortId($lock->browser_id) : null,
                    // A device lock's network: where its last try came from.
                    'ip' => $lock->ip ?? ShopCodeAttempt::where('browser_id', $lock->browser_id)->latest('id')->value('ip'),
                    'device' => ShopCodeJoin::describe($lock->user_agent),
                    'user_agent' => $lock->user_agent,
                    'wrong_codes' => (clone $wrong)->count(),
                    'devices_tried' => $lock->browser_id ? null : (clone $wrong)->distinct()->count('browser_id'),
                    'locked_at' => $lock->created_at->timezone(config('app.timezone'))->format('j M, H:i'),
                    'locked_ago' => $lock->created_at->diffForHumans(),
                ];
            })->all();
    }

    /**
     * Where shops send bank transfers for orders we arrange with them. Shown
     * to owners on their unpaid orders, with the order's reference.
     */
    public function updateBank(Request $request): RedirectResponse
    {
        $bank = $request->validate([
            'account_name' => ['nullable', 'string', 'max:100'],
            'sort_code' => ['nullable', 'string', 'regex:/^\d{2}-?\d{2}-?\d{2}$/'],
            'account_number' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'bank_name' => ['nullable', 'string', 'max:100'],
        ], [
            'sort_code.regex' => 'Enter a sort code like 12-34-56.',
            'account_number.regex' => 'Enter the 8-digit account number.',
        ]);

        if (blank($bank['account_name'] ?? null) && blank($bank['sort_code'] ?? null) && blank($bank['account_number'] ?? null)) {
            Setting::where('key', Setting::BANK_DETAILS)->delete();

            return back()->with('status', 'Bank details removed.');
        }

        $digits = preg_replace('/\D/', '', (string) ($bank['sort_code'] ?? ''));
        $bank['sort_code'] = $digits ? implode('-', str_split($digits, 2)) : null;
        Setting::set(Setting::BANK_DETAILS, $bank);

        return back()->with('status', 'Bank details saved.');
    }

    /** The WhatsApp welcome message for new shops (the WhatsApp button's "Welcome message"). */
    public function updateWelcome(Request $request): RedirectResponse
    {
        $welcome = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'video_url' => ['nullable', 'url:http,https', 'max:300'],
        ], [
            'message.required' => 'Write the message.',
            'video_url.url' => 'Paste the full video link, starting with https://',
        ]);

        Setting::set(Setting::WHATSAPP_WELCOME, [
            'message' => trim(str_replace("\r\n", "\n", $welcome['message'])),
            'video_url' => filled($welcome['video_url'] ?? null) ? trim($welcome['video_url']) : null,
        ]);

        return back()->with('status', 'Welcome message saved.');
    }

    /** Sidebar + mobile tab bar colours for every dashboard (admin, and owners on the standard look). */
    public function updateSidebar(Request $request): RedirectResponse
    {
        $hex = ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $colors = $request->validate(['bg' => $hex, 'text' => $hex]);

        $colors = array_map(fn ($c) => $c ? strtoupper($c) : null, $colors + ['bg' => null, 'text' => null]);

        if ($colors['bg'] === null && $colors['text'] === null) {
            Setting::where('key', Setting::SIDEBAR_COLORS)->delete();

            return back()->with('status', 'Sidebar colours are back to the default.');
        }

        Setting::set(Setting::SIDEBAR_COLORS, $colors);

        return back()->with('status', 'Sidebar colours saved.');
    }

    /** @return list<array{key: string, label: string, description: string, default: bool, forced_on: int, forced_off: int}> */
    private static function featureSummary(): array
    {
        $overrides = Shop::whereNotNull('features')->pluck('features');

        return collect(Features::ALL)->map(fn (array $feature, string $key) => [
            'key' => $key,
            ...$feature,
            'default' => Features::default($key),
            'forced_on' => $overrides->filter(fn ($f) => ($f[$key] ?? null) === true)->count(),
            'forced_off' => $overrides->filter(fn ($f) => ($f[$key] ?? null) === false)->count(),
        ])->values()->all();
    }

    /**
     * An owner feature's platform default. Switching one off keeps it on for
     * the shops already using it (their own override), so nobody loses a
     * menu or WhatsApp history they've built; the admin can still turn it off
     * for them on their settings page.
     */
    public function updateFeature(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'feature' => ['required', Rule::in(array_keys(Features::ALL))],
            'enabled' => ['required', 'boolean'],
        ]);
        $feature = $input['feature'];
        $label = Features::ALL[$feature]['label'];
        $kept = 0;

        DB::transaction(function () use ($feature, $input, &$kept) {
            if (! $input['enabled'] && Features::default($feature) && ($inUse = Features::inUse($feature))) {
                $inUse->get()->each(function (Shop $shop) use ($feature, &$kept) {
                    if ($shop->featureOverride($feature) === null) {
                        $shop->forceFill(['features' => [...($shop->features ?? []), $feature => true]])->save();
                        $kept++;
                    }
                });
            }

            Features::setDefault($feature, (bool) $input['enabled']);
        });

        return back()->with('status', $input['enabled']
            ? "{$label} is now on by default."
            : "{$label} is now off by default.".($kept ? " Kept on for the {$kept} ".($kept === 1 ? 'shop' : 'shops').' already using it.' : ''));
    }

    /**
     * Who checks menu item photos from the catalog: Gemini, or the person
     * finding photos in the menu editor ("Is this …?" yes / no).
     */
    public function updateMenuPhotos(Request $request, MenuItemImages $images): RedirectResponse
    {
        $mode = $request->validate([
            'mode' => ['required', Rule::in([MenuItemImages::MODE_AI, MenuItemImages::MODE_MANUAL])],
        ])['mode'];

        if ($mode === MenuItemImages::MODE_AI && ! $images->aiAvailable()) {
            throw ValidationException::withMessages(['mode' => 'Add GEMINI_API_KEY to .env first.']);
        }

        Setting::set(Setting::MENU_PHOTO_CHECK, $mode);

        return back()->with('status', $mode === MenuItemImages::MODE_AI
            ? 'Menu photos are now checked by AI.'
            : 'Menu photos are now confirmed by hand.');
    }

    public function updateGoogle(Request $request): RedirectResponse
    {
        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        if ($enabled && ! GoogleAuthController::configured()) {
            throw ValidationException::withMessages([
                'enabled' => 'Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env first.',
            ]);
        }

        Setting::set(Setting::GOOGLE_AUTH, (bool) $enabled);

        return back()->with('status', $enabled ? 'Google sign-in is on.' : 'Google sign-in is off.');
    }
}
