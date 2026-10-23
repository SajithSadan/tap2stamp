<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShopOwnerRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopHealth;
use App\Support\Countries;
use App\Support\CrmStage;
use App\Support\Industries;
use App\Support\StampIcons;
use App\Support\ThemeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShopOwnerController extends Controller
{
    /** A shop with no stamps for this many days counts as "quiet". */
    public const QUIET_DAYS = 14;

    /**
     * Every shop as one row for the admin's data grid. All shops are sent at
     * once - sorting, search, filters and paging happen in the browser, which
     * is instant at this platform's size (hundreds of shops, not millions).
     */
    public function index(ShopHealth $health): Response
    {
        $monthAgo = now()->subDays(30);
        $quietSince = now()->subDays(self::QUIET_DAYS);

        // "Has it ordered the primary product?" - the featured one (the
        // counter display), not just anything, once there are more products.
        $primary = Product::where('is_featured', true)->first();
        $primaryOrders = fn ($q) => $q->where('product_id', $primary?->id ?? 0);

        $shops = Shop::with(['owner:id,name,email,google_id,welcome_sent_at', 'plan:id,name'])
            ->withMax(['orders as primary_paid_at' => fn ($q) => $primaryOrders($q)->where('status', OrderStatus::Paid)], 'paid_at')
            ->withExists(['orders as primary_awaiting' => fn ($q) => $primaryOrders($q)
                ->where('status', OrderStatus::Pending)
                ->where('payment_method', '!=', PaymentMethod::Stripe->value)])
            ->withCount([
                'cards as customers',
                'cards as new_customers_30d' => fn ($q) => $q->where('created_at', '>=', $monthAgo),
                'stampLogs as stamps_30d' => fn ($q) => $q->where('action_type', ActionType::StampAdded)->where('created_at', '>=', $monthAgo),
                'stampLogs as rewards_total' => fn ($q) => $q->where('action_type', ActionType::RewardRedeemed),
                'staffMembers as staff' => fn ($q) => $q->whereNull('deactivated_at'),
                'tasks as open_tasks' => fn ($q) => $q->whereNull('done_at'),
                'notes',
                'reviews',
            ])
            ->withAvg('reviews as rating', 'rating')
            ->withMax('stampLogs as last_activity_at', 'created_at')
            ->latest()
            ->get();

        // Usage health for every shop at once (a fixed number of grouped queries).
        $healthByShop = $health->forShops($shops);

        return Inertia::render('Admin/Index', [
            'quietDays' => self::QUIET_DAYS,
            'segments' => ShopHealth::SEGMENTS,
            'stages' => CrmStage::DISPLAY,
            'primaryProduct' => $primary?->name,
            'shops' => $shops->map(function (Shop $shop) use ($quietSince, $healthByShop) {
                $lastActivity = $shop->last_activity_at ? Carbon::parse($shop->last_activity_at) : null;

                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'owner_name' => $shop->owner?->name,
                    'owner_email' => $shop->owner?->email,
                    'owner_via_google' => $shop->owner?->google_id !== null,
                    // The admin's first WhatsApp welcome to this owner (null = not sent yet).
                    'welcome_sent_at' => $shop->owner?->welcome_sent_at?->toIso8601String(),
                    'welcome_sent_label' => $shop->owner?->welcome_sent_at?->timezone('Europe/London')->format('j M Y'),
                    'max_stamps' => $shop->max_stamps,
                    'reward_title' => $shop->reward_title,
                    // Business contact + location from shop setup (null for older shops).
                    'contact_name' => $shop->contact_name,
                    'contact_email' => $shop->contact_email,
                    // Code + number together ("+44 7700900123"), and the same for tel: links.
                    'contact_phone' => $shop->contactPhone(),
                    'contact_phone_tel' => $shop->contactPhoneTel(),
                    'contact_whatsapp' => $shop->whatsappNumber(),
                    'town' => $shop->town,
                    'state' => $shop->state,
                    'postcode' => $shop->postcode,
                    'country' => Countries::name($shop->country),
                    // For the grid's country filter (UK / India / ...).
                    'country_code' => $shop->country,
                    // Kind of business (null = the owner hasn't been asked yet).
                    'industry' => $shop->industry,
                    'industry_label' => $shop->industryLabel(),
                    'address' => collect([$shop->address_line1, $shop->address_line2, $shop->town, $shop->state, $shop->postcode])->filter()->implode(', ') ?: null,
                    'delivery_address' => $shop->delivery_address,
                    // The primary product: ordered (latest paid), awaiting the shop's
                    // bank transfer, or not ordered.
                    'product_ordered_at' => $shop->primary_paid_at ? Carbon::parse($shop->primary_paid_at)->toIso8601String() : null,
                    'product_ordered_label' => $shop->primary_paid_at ? Carbon::parse($shop->primary_paid_at)->format('j M Y') : null,
                    'product_awaiting' => ! $shop->primary_paid_at && $shop->primary_awaiting,
                    // Owners who print their own QR (overseas, or shown the card link):
                    // did they find the QR page, did the download work? Null = uses the display.
                    'qr' => $shop->downloadsOwnQr() ? [
                        'state' => match (true) {
                            $shop->qr_downloaded_at !== null => 'downloaded',
                            $shop->qr_download_failures > 0 => 'failed',
                            $shop->qr_page_viewed_at !== null => 'opened',
                            default => 'not_opened',
                        },
                        'downloaded_at' => $shop->qr_downloaded_at?->toIso8601String(),
                        'downloaded_label' => $shop->qr_downloaded_at?->format('j M Y'),
                        'opened_label' => $shop->qr_page_viewed_at?->format('j M Y'),
                        'downloads' => $shop->qr_downloads,
                        'failures' => $shop->qr_download_failures,
                    ] : null,
                    'customers' => $shop->customers,
                    'new_customers_30d' => $shop->new_customers_30d,
                    'stamps_30d' => $shop->stamps_30d,
                    'rewards_total' => $shop->rewards_total,
                    'rating' => $shop->rating === null ? null : round((float) $shop->rating, 1),
                    'reviews' => $shop->reviews_count,
                    'staff' => $shop->staff,
                    'blocked_reason' => $shop->blocked_reason,
                    // Blocked by the admin; active = stamped recently; quiet = used to, stopped; not started = never stamped.
                    'status' => match (true) {
                        $shop->isBlocked() => 'blocked',
                        $lastActivity === null => 'not_started',
                        $lastActivity->lt($quietSince) => 'quiet',
                        default => 'active',
                    },
                    // ISO for sorting, plus a human label for display.
                    'last_activity_at' => $lastActivity?->toIso8601String(),
                    'last_activity' => $lastActivity?->diffForHumans(),
                    // MONETISATION-PLAN.md phase 1: usage health, pipeline stage, trial.
                    'health' => collect($healthByShop[$shop->id])->only(['score', 'segment', 'step', 'step_label', 'reasons'])->all(),
                    'stage' => CrmStage::display($shop->crm_stage, $healthByShop[$shop->id]['metrics']['first_stamp_at'] !== null),
                    'tags' => $shop->crm_tags ?? [],
                    'open_tasks' => $shop->open_tasks,
                    // The admin's notes on the shop (the grid's notes icon shows the count).
                    'notes' => $shop->notes_count,
                    // The admin's follow-up status: active / hot / warm / cold (null = not set).
                    'lead_status' => $shop->lead_status,
                    'trial_ends_at' => $shop->trial_ends_at?->toIso8601String(),
                    'trial_ends_label' => $shop->trial_ends_at?->timezone('Europe/London')->format('j M Y'),
                    // Whole days left (negative = ended that many days ago).
                    'trial_days_left' => $shop->trial_ends_at ? (int) now()->startOfDay()->diffInDays($shop->trial_ends_at->copy()->startOfDay(), false) : null,
                    // Yearly plan: active (paid) / trial / expired, and whether scanning is paused.
                    'plan' => $shop->plan?->name,
                    'subscription' => collect($shop->subscriptionSummary())->only(['status', 'paid', 'paid_until_label', 'ended_label', 'paused'])->all(),
                    'created_at' => $shop->created_at->toIso8601String(),
                    'created_label' => $shop->created_at->format('j M Y'),
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Create', [
            'themes' => ThemeCatalog::all(),
            'defaultTheme' => ThemeCatalog::DEFAULT,
            'countries' => Countries::options(),
            'industries' => Industries::options(),
        ]);
    }

    /**
     * Live card-link check for the "Add shop" form: is the typed link free,
     * and if not, a free one based on it.
     */
    public function slug(Request $request): JsonResponse
    {
        $slug = Str::slug($request->string('slug')->value());

        return response()->json([
            'available' => $slug !== '' && ! Shop::where('slug', $slug)->exists(),
            'suggestion' => Shop::suggestSlug($slug),
            'ideas' => Shop::slugIdeas($request->string('name')->value()),
        ]);
    }

    public function store(StoreShopOwnerRequest $request): RedirectResponse
    {
        // Set by the admin (typed or generated on the form), who hands it to
        // the owner out of band. Flashed once so it can be copied with the email.
        $password = $request->string('owner_password')->value();
        $theme = $request->string('shop_theme')->value();
        $stampIcon = $request->string('shop_stamp_icon')->value();

        DB::transaction(function () use ($request, $password, $theme, $stampIcon) {
            $owner = User::create([
                // The form only asks for a login; the owner's display name starts as the shop's.
                'name' => $request->string('shop_name')->value(),
                'email' => $request->string('owner_email')->value(),
                'password' => $password,
                'role' => UserRole::Owner,
            ]);

            Shop::create([
                'user_id' => $owner->id,
                'name' => $request->string('shop_name')->value(),
                'slug' => $request->string('shop_slug')->value(),
                'max_stamps' => $request->integer('shop_max_stamps'),
                'reward_title' => $request->string('shop_reward_title')->value(),
                // The owner is the business contact until someone says otherwise.
                'contact_name' => $owner->name,
                'contact_email' => $owner->email,
                'country' => $request->input('shop_country') ?: Countries::UK,
                ...Industries::attributes($request->validated(), 'shop_'),
                // Defaults stay null, so a later change of the default still reaches this shop.
                'theme' => $theme === ThemeCatalog::DEFAULT ? null : $theme,
                'stamp_icon' => $stampIcon === StampIcons::DEFAULT ? null : $stampIcon,
            ]);
        });

        return redirect()->route('admin.index')
            ->with('generatedPassword', $password)
            ->with('createdOwnerEmail', $request->string('owner_email')->value());
    }
}
