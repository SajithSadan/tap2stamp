<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Http\Requests\UpdateShopSettingsRequest;
use App\Http\Requests\UpdateShopThemeCustomRequest;
use App\Http\Requests\UpdateShopThemeRequest;
use App\Models\CustomerShopCard;
use App\Models\CustomTheme;
use App\Models\Review;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use App\Models\StampLog;
use App\Support\CuratedFonts;
use App\Support\StampIcons;
use App\Support\ThemeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner dashboard sections. Every action reads auth()->user()->shop - there's
 * never a shop param in the URL, so one owner can't reach another's data.
 */
class DashboardController extends Controller
{
    private const CHART_DAYS = 14;

    public function index(Request $request): Response
    {
        $shop = $request->user()->shop;
        $maxStamps = $shop->max_stamps;

        return Inertia::render('Dashboard/Overview', [
            'shop' => $this->shopSummary($shop),
            'stats' => [
                'customer_count' => $shop->cards()->count(),
                'new_customers_7d' => $shop->cards()->where('created_at', '>=', now()->subDays(7))->count(),
                'stamps_today' => $shop->stampLogs()
                    ->whereDate('created_at', today())
                    ->where('action_type', ActionType::StampAdded)
                    ->count(),
                'rewards_redeemed' => $shop->stampLogs()->where('action_type', ActionType::RewardRedeemed)->count(),
                'rewards_ready' => $shop->cards()->where('current_stamps', '>=', $maxStamps)->count(),
                'average_rating' => round((float) $shop->reviews()->avg('rating'), 1),
                'review_count' => $shop->reviews()->count(),
            ],
            'chart' => $this->dailyActivity($shop),
            'recentActivity' => $this->activityQuery($shop)->limit(5)->get()->map($this->activityRow(...)),
        ]);
    }

    public function customers(Request $request): Response
    {
        $shop = $request->user()->shop;
        $search = trim((string) $request->query('q', ''));

        return Inertia::render('Dashboard/Customers', [
            'shop' => $this->shopSummary($shop),
            'search' => $search,
            'maxStamps' => $shop->max_stamps,
            // No phone numbers here - same stance as the activity feed.
            'customers' => $shop->cards()
                ->with('customer:id,name')
                ->when($search !== '', fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', '%'.$search.'%')))
                ->orderByDesc('last_stamped_at')
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (CustomerShopCard $card) => [
                    'id' => $card->id,
                    'name' => $card->customer->name,
                    'stamps' => $card->current_stamps,
                    'rewards_claimed' => $card->rewards_claimed,
                    'last_visit' => $card->last_stamped_at?->timezone('Europe/London')->format('j M Y'),
                    'joined' => $card->created_at->timezone('Europe/London')->format('j M Y'),
                    'marketing_consent' => $card->marketing_consent,
                ]),
        ]);
    }

    public function activity(Request $request): Response
    {
        $shop = $request->user()->shop;

        return Inertia::render('Dashboard/Activity', [
            'shop' => $this->shopSummary($shop),
            // Served by the stamp_logs(shop_id, created_at) index. Name only -
            // the owner never needs a customer's phone number here.
            'activity' => $this->activityQuery($shop)->paginate(15)->through($this->activityRow(...)),
        ]);
    }

    public function reviews(Request $request): Response
    {
        $shop = $request->user()->shop;
        $counts = $shop->reviews()->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating');

        return Inertia::render('Dashboard/Reviews', [
            'shop' => $this->shopSummary($shop),
            'summary' => [
                'average' => round((float) $shop->reviews()->avg('rating'), 1),
                'count' => (int) $counts->sum(),
                'distribution' => collect([5, 4, 3, 2, 1])->map(fn (int $stars) => [
                    'stars' => $stars,
                    'count' => (int) ($counts[$stars] ?? 0),
                ]),
            ],
            'reviews' => $shop->reviews()
                ->with('customer:id,name')
                ->latest('updated_at')
                ->paginate(10)
                ->through(fn (Review $review) => [
                    'id' => $review->id,
                    'customer_name' => $review->customer->name,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'date' => $review->updated_at->timezone('Europe/London')->format('j M Y'),
                ]),
        ]);
    }

    public function staff(Request $request): Response
    {
        $shop = $request->user()->shop;
        $devices = $shop->staffDevices()->with('staffMember')->latest()->get();

        // Which device (if any) each staff member is signed in on right now.
        $signedInOn = $devices
            ->filter(fn (StaffDevice $device) => ! $device->revoked_at && $device->activeStaffMember())
            ->mapWithKeys(fn (StaffDevice $device) => [$device->staff_member_id => $device->name]);

        return Inertia::render('Dashboard/Staff', [
            'shop' => $this->shopSummary($shop),
            'staffMembers' => $shop->staffMembers()
                ->active()
                ->withCount([
                    'stampLogs as stamps_today' => fn ($q) => $q
                        ->whereDate('created_at', today())
                        ->where('action_type', ActionType::StampAdded),
                    'stampLogs as stamps_total' => fn ($q) => $q->where('action_type', ActionType::StampAdded),
                ])
                ->withMax('stampLogs as last_scan_at', 'created_at')
                ->orderBy('name')
                ->get()
                ->map(fn (StaffMember $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'stamps_today' => $member->stamps_today,
                    'stamps_total' => $member->stamps_total,
                    'last_scan' => $member->last_scan_at ? Carbon::parse($member->last_scan_at)->diffForHumans() : null,
                    'signed_in_on' => $signedInOn[$member->id] ?? null,
                ]),
            'staffDevices' => $devices->map(fn (StaffDevice $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'revoked' => $device->revoked_at !== null,
                'signed_in' => $device->revoked_at ? null : $device->activeStaffMember()?->name,
                'last_used_at' => $device->last_used_at?->diffForHumans(),
                'created_at' => $device->created_at->diffForHumans(),
            ]),
        ]);
    }

    public function settings(Request $request): Response
    {
        $shop = $request->user()->shop;

        return Inertia::render('Dashboard/Settings', [
            'shop' => [
                ...$this->shopSummary($shop),
                'max_stamps' => $shop->max_stamps,
                'reward_title' => $shop->reward_title,
                'google_review_url' => $shop->google_review_url,
                'instagram_url' => $shop->instagram_url,
                'wifi_ssid' => $shop->wifi_ssid,
                'wifi_password' => $shop->wifi_password,
            ],
        ]);
    }

    public function updateSettings(UpdateShopSettingsRequest $request): RedirectResponse
    {
        $request->user()->shop->update($request->validated());

        return redirect()->route('dashboard.settings');
    }

    public function theme(Request $request): Response
    {
        $shop = $request->user()->shop;

        return Inertia::render('Dashboard/Theme', [
            'shop' => [
                ...$this->shopSummary($shop),
                'reward_title' => $shop->reward_title,
                'max_stamps' => $shop->max_stamps,
            ],
            'currentTheme' => ThemeCatalog::forShop($shop->theme)['slug'],
            'appliedTheme' => $shop->appliedTheme(),
            'customTheme' => $shop->theme_custom,
            'defaultTheme' => ThemeCatalog::DEFAULT,
            'themeInDashboard' => $shop->theme_in_dashboard,
            'stampIcon' => StampIcons::resolve($shop->stamp_icon),
            'bannerUrl' => $shop->bannerUrl(),
            'themes' => ThemeCatalog::all(),
            'availableFonts' => CuratedFonts::all(),
            'radiusPresets' => CustomTheme::RADIUS_PRESETS,
        ]);
    }

    /** Picking a catalog theme starts fresh - any customisation is dropped. */
    public function updateTheme(UpdateShopThemeRequest $request): RedirectResponse
    {
        $request->user()->shop->update(['theme' => $request->string('theme')->value(), 'theme_custom' => null]);

        return redirect()->route('dashboard.theme');
    }

    /** Back to the site's default look (null, so a future default change applies too). */
    public function resetTheme(Request $request): RedirectResponse
    {
        $request->user()->shop->update(['theme' => null, 'theme_custom' => null]);

        return redirect()->route('dashboard.theme');
    }

    public function updateCustomTheme(UpdateShopThemeCustomRequest $request): RedirectResponse
    {
        $request->user()->shop->update(['theme_custom' => $request->validated()]);

        return redirect()->route('dashboard.theme');
    }

    /** Drops the owner's tweaks, back to the catalog theme as-is. */
    public function resetCustomTheme(Request $request): RedirectResponse
    {
        $request->user()->shop->update(['theme_custom' => null]);

        return redirect()->route('dashboard.theme');
    }

    public function updateStampIcon(Request $request): RedirectResponse
    {
        $validated = $request->validate(['stamp_icon' => ['required', 'string', Rule::in(StampIcons::KEYS)]]);

        $request->user()->shop->update(['stamp_icon' => $validated['stamp_icon']]);

        return redirect()->route('dashboard.theme');
    }

    public function updateDashboardTheme(Request $request): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $request->user()->shop->update(['theme_in_dashboard' => $validated['enabled']]);

        return redirect()->route('dashboard.theme');
    }

    private function shopSummary(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            'slug' => $shop->slug,
            'name' => $shop->name,
            // Read by OwnerLayout on every section; null keeps the default look.
            'dashboard_theme' => $shop->theme_in_dashboard ? $shop->appliedTheme() : null,
        ];
    }

    private function activityQuery(Shop $shop)
    {
        return $shop->stampLogs()
            ->with(['customer:id,name', 'staffMember:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function activityRow(StampLog $log): array
    {
        return [
            'id' => $log->id,
            'customer_name' => $log->customer->name,
            'staff_name' => $log->staffMember?->name,
            'action' => $log->action_type->value,
            'created_at' => $log->created_at->timezone('Europe/London')->format('j M, g:i A'),
        ];
    }

    /**
     * Stamps and redemptions per day for the last CHART_DAYS days, oldest
     * first, with zero-filled days. Grouped in PHP (not SQL DATE()) so the
     * day boundaries follow the app timezone.
     */
    private function dailyActivity(Shop $shop): array
    {
        $start = today()->subDays(self::CHART_DAYS - 1);

        $byDay = $shop->stampLogs()
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'action_type'])
            ->groupBy(fn (StampLog $log) => $log->created_at->format('Y-m-d'));

        return collect(range(0, self::CHART_DAYS - 1))->map(function (int $offset) use ($start, $byDay) {
            $day = $start->copy()->addDays($offset);
            $logs = $byDay->get($day->format('Y-m-d'), collect());

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('j M'),
                'weekday' => $day->format('D'),
                'stamps' => $logs->where('action_type', ActionType::StampAdded)->count(),
                'redeemed' => $logs->where('action_type', ActionType::RewardRedeemed)->count(),
            ];
        })->all();
    }
}
