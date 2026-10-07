<?php

namespace App\Http\Controllers;

use App\Enums\ActionType;
use App\Enums\OrderStatus;
use App\Http\Requests\UpdateShopContactRequest;
use App\Http\Requests\UpdateShopHeaderStyleRequest;
use App\Http\Requests\UpdateShopSettingsRequest;
use App\Http\Requests\UpdateShopThemeCustomRequest;
use App\Http\Requests\UpdateShopThemeRequest;
use App\Models\CustomerShopCard;
use App\Models\CustomTheme;
use App\Models\Order;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\StaffDevice;
use App\Models\StaffMember;
use App\Models\StampLog;
use App\Services\ActivityLogger;
use App\Services\ShopInsights;
use App\Services\StripeGateway;
use App\Support\Countries;
use App\Support\CuratedFonts;
use App\Support\HeaderStyle;
use App\Support\ShopContact;
use App\Support\SignupIcons;
use App\Support\StampIcons;
use App\Support\ThemeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner dashboard sections. Every action reads auth()->user()->shop - there's
 * never a shop param in the URL, so one owner can't reach another's data.
 */
class DashboardController extends Controller
{
    private const CHART_DAYS = 14;

    public function index(Request $request, StripeGateway $stripe): Response
    {
        $shop = $request->user()->shop;
        $maxStamps = $shop->max_stamps;

        return Inertia::render('Dashboard/Overview', [
            'shop' => $this->shopSummary($shop),
            'orderOffer' => $this->orderOffer($shop, $stripe),
            // After ordering: the latest order still on its way (until delivered),
            // or one waiting for their bank transfer.
            'activeOrder' => $this->openOrders($shop)
                ->whereNull('delivered_at')
                ->latest()
                ->first()
                ?->summary(),
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
            'customers' => $this->customersQuery($shop, $search)
                ->with('customer:id,name,phone')
                ->orderByDesc('last_stamped_at')->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (CustomerShopCard $card) => [
                    'id' => $card->id,
                    'name' => $card->customer->name,
                    'phone' => $card->customer->phone,
                    'stamps' => $card->current_stamps,
                    'rewards_claimed' => $card->rewards_claimed,
                    'last_visit' => $card->last_stamped_at?->timezone('Europe/London')->format('j M Y'),
                    'joined' => $card->created_at->timezone('Europe/London')->format('j M Y'),
                    'marketing_consent' => $card->marketing_consent,
                ]),
        ]);
    }

    public function exportCustomers(Request $request): StreamedResponse
    {
        $shop = $request->user()->shop;
        $search = trim((string) $request->query('q', ''));
        $query = $this->customersQuery($shop, $search)
            ->with('customer:id,name,phone')
            ->orderByDesc('last_stamped_at')->orderByDesc('id');

        // Phone numbers leave the app here, so who downloaded them is on record.
        ActivityLogger::record('customers.exported', 'Downloaded the customer list'.($search !== '' ? " (search: {$search})" : ''), $shop->id);

        return response()->streamDownload(function () use ($query, $shop) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Name', 'Phone', 'Stamps', 'Stamps required', 'Rewards claimed', 'Last visit', 'Joined', 'SMS offers opted in']);

            $query->chunk(500, function ($cards) use ($output, $shop) {
                foreach ($cards as $card) {
                    fputcsv($output, [
                        $this->spreadsheetSafe($card->customer->name),
                        $this->spreadsheetSafe($card->customer->phone),
                        $card->current_stamps,
                        $shop->max_stamps,
                        $card->rewards_claimed,
                        $card->last_stamped_at?->timezone('Europe/London')->format('j M Y') ?? '',
                        $card->created_at->timezone('Europe/London')->format('j M Y'),
                        $card->marketing_consent ? 'Yes' : 'No',
                    ]);
                }
            });

            fclose($output);
        }, (Str::slug($shop->name) ?: 'shop').'-customers-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function customersQuery(Shop $shop, string $search)
    {
        return $shop->cards()->when($search !== '', fn ($q) => $q->whereHas('customer', fn ($customer) => $customer
            ->where('name', 'like', '%'.$search.'%')
            ->orWhere('phone', 'like', '%'.$search.'%')));
    }

    private function spreadsheetSafe(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'{$value}" : $value;
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

    /**
     * Insights: regulars, who's due back or drifting away, whether the card
     * is working, and busy times. Each section is a lazy prop with its own
     * period (?regulars=30&loyalty=365&busy=90), so a card's filter reloads
     * only that card - like the admin dashboard.
     */
    public function insights(Request $request): Response
    {
        $shop = $request->user()->shop;
        $insights = new ShopInsights($shop);
        $range = fn (string $section) => in_array($request->integer($section), ShopInsights::RANGES, true)
            ? $request->integer($section)
            : ShopInsights::DEFAULT_RANGE;

        return Inertia::render('Dashboard/Insights', [
            'shop' => $this->shopSummary($shop),
            'ranges' => ShopInsights::RANGES,
            'regulars' => fn () => $insights->regulars($range('regulars')),
            'rewards' => fn () => $insights->closeToReward(),
            'dueBack' => fn () => $insights->dueBack(),
            'loyalty' => fn () => $insights->loyalty($range('loyalty')),
            'busy' => fn () => $insights->busyTimes($range('busy')),
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
                'google_review_direct' => $shop->google_review_direct,
                'instagram_url' => $shop->instagram_url,
                'wifi_ssid' => $shop->wifi_ssid,
                'wifi_password' => $shop->wifi_password,
            ],
            'contact' => $shop->contactDetails(),
            'countries' => Countries::options(),
        ]);
    }

    public function updateSettings(UpdateShopSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $shop = $request->user()->shop;
        $googleUrl = array_key_exists('google_review_url', $validated)
            ? $validated['google_review_url']
            : $shop->google_review_url;
        $directRequested = array_key_exists('google_review_direct', $validated)
            ? filter_var($validated['google_review_direct'], FILTER_VALIDATE_BOOLEAN)
            : $shop->google_review_direct;

        $validated['google_review_direct'] = (bool) $googleUrl && $directRequested;
        $shop->update($validated);

        return redirect()->route('dashboard.settings');
    }

    /** Business contact + location - its own form, so older shops' main settings keep saving. */
    public function updateContact(UpdateShopContactRequest $request): RedirectResponse
    {
        $request->user()->shop->update(ShopContact::attributes($request->validated()));

        return redirect()->route('dashboard.settings')->with('status', 'Contact details saved.');
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
            'signupIcon' => SignupIcons::resolve($shop->signup_icon),
            'headerStyle' => $shop->headerStyle(),
            'maxTint' => HeaderStyle::MAX_TINT,
            'bannerUrl' => $shop->bannerUrl(),
            'logoUrl' => $shop->logoUrl(),
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

    /** Shop name / reward colour, banner tint and title shadow on the card page header. */
    public function updateHeaderStyle(UpdateShopHeaderStyleRequest $request): RedirectResponse
    {
        $request->user()->shop->update(['header_style' => HeaderStyle::resolve($request->validated())]);

        return redirect()->route('dashboard.theme');
    }

    public function updateStampIcon(Request $request): RedirectResponse
    {
        $validated = $request->validate(['stamp_icon' => ['required', 'string', Rule::in(StampIcons::KEYS)]]);

        $request->user()->shop->update(['stamp_icon' => $validated['stamp_icon']]);

        return redirect()->route('dashboard.theme');
    }

    /** The decorative icon on the customer sign-up screen (separate from the stamp icon). */
    public function updateSignupIcon(Request $request): RedirectResponse
    {
        $validated = $request->validate(['signup_icon' => ['required', 'string', Rule::in(SignupIcons::KEYS)]]);

        $request->user()->shop->update(['signup_icon' => $validated['signup_icon']]);

        return redirect()->route('dashboard.theme');
    }

    public function updateDashboardTheme(Request $request): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $request->user()->shop->update(['theme_in_dashboard' => $validated['enabled']]);

        return redirect()->route('dashboard.theme');
    }

    /** Order more (any product on sale, any quantity) and follow each order's progress. */
    public function orders(Request $request, StripeGateway $stripe): Response|RedirectResponse
    {
        $shop = $request->user()->shop;

        // UK only; anywhere else the owner downloads the QR codes we assigned.
        if (! $shop->canOrderProducts()) {
            return redirect()->route('dashboard.qr-codes');
        }

        return Inertia::render('Dashboard/Orders', [
            'shop' => $this->shopSummary($shop),
            'products' => Product::active()
                ->orderByDesc('is_featured')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'price_pence', 'price_tiers']),
            // Paid, or arranged with us and waiting for their transfer. Not
            // abandoned Stripe checkouts or cancelled orders.
            'orders' => $this->openOrders($shop)
                ->latest()
                ->get()
                ->map(fn (Order $order) => $order->summary()),
            // Where to send a bank transfer (Admin → Settings), shown on unpaid orders.
            'bankDetails' => Setting::get(Setting::BANK_DETAILS),
            'canPayOnline' => $stripe->configured(),
            'deliveryAddress' => $shop->deliveryAddress(),
            'maxQuantity' => Order::MAX_QUANTITY,
        ]);
    }

    /**
     * The QR codes the admin mapped to this shop (qr_codes.shop_id), to
     * download in the design the admin picked for it (null = plain QR). The
     * PNG / PDF are drawn in the browser by the same renderer as the admin's
     * prints (lib/qrPrint.js). In the menu for overseas shops; UK shops order
     * the counter display instead.
     */
    public function qrCodes(Request $request): Response
    {
        $shop = $request->user()->shop;

        return Inertia::render('Dashboard/QrCodes', [
            'shop' => $this->shopSummary($shop),
            'codes' => $shop->qrCodes()->orderBy('qr_batch_id')->orderBy('serial')->get()->map(fn (QrCode $qr) => [
                'id' => $qr->id,
                'code' => $qr->code,
                'scan_url' => $qr->scanUrl(),
                'serial' => $qr->serial,
                // What scanning it opens, in the owner's words (never the card link itself).
                'opens' => match (true) {
                    $qr->destination_url === null => null,
                    str_contains($qr->destination_url, '/menu/') => 'Your menu',
                    str_contains($qr->destination_url, '/s/') => 'Your loyalty card',
                    default => 'A web page',
                },
            ]),
            'design' => $shop->qrDesign?->toClient(),
        ]);
    }

    /** The shop's real orders: paid, or arranged by us and awaiting payment. */
    private function openOrders(Shop $shop)
    {
        return $shop->orders()->where(fn ($q) => $q
            ->where('status', OrderStatus::Paid)
            ->orWhere(fn ($pending) => $pending->where('status', OrderStatus::Pending)->where('payment_method', '!=', 'stripe')));
    }

    /**
     * "What's next? Order your counter display" - offered until the shop has
     * ordered (online, or recorded by the admin), then never again.
     */
    private function orderOffer(Shop $shop, StripeGateway $stripe): ?array
    {
        // We only ship to the UK; overseas shops get their QR codes to download instead.
        if (! $shop->canOrderProducts()) {
            return null;
        }

        // Also not while an order we arranged with them waits for their transfer.
        $arranged = $shop->orders()->where('status', OrderStatus::Pending)->where('payment_method', '!=', 'stripe')->exists();
        $product = $shop->product_ordered_at || $arranged ? null : Product::featured();

        if (! $product) {
            return null;
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            ...$product->pricing(),
            'delivery_address' => $shop->deliveryAddress(),
            // No Stripe keys yet: show the offer, but ask them to get in touch.
            'can_pay_online' => $stripe->configured(),
        ];
    }

    public static function shopSummary(Shop $shop): array
    {
        return [
            'id' => $shop->id,
            // The card link only reaches the owner's pages if the admin allows it.
            'slug' => $shop->show_card_link ? $shop->slug : null,
            'show_card_link' => $shop->show_card_link,
            'name' => $shop->name,
            // UK: order the counter display. Elsewhere: download the assigned QR codes.
            'can_order' => $shop->canOrderProducts(),
            'google_review_url' => $shop->google_review_url,
            // Read by OwnerLayout on every section; null keeps the default look.
            'dashboard_theme' => $shop->theme_in_dashboard ? $shop->appliedTheme() : null,
        ];
    }

    private function activityQuery(Shop $shop)
    {
        return $shop->stampLogs()
            ->with(['customer:id,name', 'staffMember:id,name', 'owner:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    private function activityRow(StampLog $log): array
    {
        return [
            'id' => $log->id,
            'customer_name' => $log->customer->name,
            'staff_name' => $log->staffMember?->name ?? $log->owner?->name ?? '—',
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
