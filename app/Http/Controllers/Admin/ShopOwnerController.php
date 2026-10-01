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
use Illuminate\Http\RedirectResponse;
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
    public function index(): Response
    {
        $monthAgo = now()->subDays(30);
        $quietSince = now()->subDays(self::QUIET_DAYS);

        // "Has it ordered the primary product?" - the featured one (the
        // counter display), not just anything, once there are more products.
        $primary = Product::where('is_featured', true)->first();
        $primaryOrders = fn ($q) => $q->where('product_id', $primary?->id ?? 0);

        $shops = Shop::with('owner:id,name,email,google_id')
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
                'reviews',
            ])
            ->withAvg('reviews as rating', 'rating')
            ->withMax('stampLogs as last_activity_at', 'created_at')
            ->latest()
            ->get();

        return Inertia::render('Admin/Index', [
            'quietDays' => self::QUIET_DAYS,
            'primaryProduct' => $primary?->name,
            'shops' => $shops->map(function (Shop $shop) use ($quietSince) {
                $lastActivity = $shop->last_activity_at ? Carbon::parse($shop->last_activity_at) : null;

                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'slug' => $shop->slug,
                    'owner_name' => $shop->owner?->name,
                    'owner_email' => $shop->owner?->email,
                    'owner_via_google' => $shop->owner?->google_id !== null,
                    'max_stamps' => $shop->max_stamps,
                    'reward_title' => $shop->reward_title,
                    // Business contact + location from shop setup (null for older shops).
                    'contact_name' => $shop->contact_name,
                    'contact_email' => $shop->contact_email,
                    'contact_phone' => $shop->contact_phone,
                    'town' => $shop->town,
                    'postcode' => $shop->postcode,
                    'address' => collect([$shop->address_line1, $shop->address_line2, $shop->town, $shop->postcode])->filter()->implode(', ') ?: null,
                    'delivery_address' => $shop->delivery_address,
                    // The primary product: ordered (latest paid), awaiting the shop's
                    // bank transfer, or not ordered.
                    'product_ordered_at' => $shop->primary_paid_at ? Carbon::parse($shop->primary_paid_at)->toIso8601String() : null,
                    'product_ordered_label' => $shop->primary_paid_at ? Carbon::parse($shop->primary_paid_at)->format('j M Y') : null,
                    'product_awaiting' => ! $shop->primary_paid_at && $shop->primary_awaiting,
                    'customers' => $shop->customers,
                    'new_customers_30d' => $shop->new_customers_30d,
                    'stamps_30d' => $shop->stamps_30d,
                    'rewards_total' => $shop->rewards_total,
                    'rating' => $shop->rating === null ? null : round((float) $shop->rating, 1),
                    'reviews' => $shop->reviews_count,
                    'staff' => $shop->staff,
                    // Active = stamped recently; quiet = used to, stopped; not started = never stamped.
                    'status' => match (true) {
                        $lastActivity === null => 'not_started',
                        $lastActivity->lt($quietSince) => 'quiet',
                        default => 'active',
                    },
                    // ISO for sorting, plus a human label for display.
                    'last_activity_at' => $lastActivity?->toIso8601String(),
                    'last_activity' => $lastActivity?->diffForHumans(),
                    'created_at' => $shop->created_at->toIso8601String(),
                    'created_label' => $shop->created_at->format('j M Y'),
                ];
            }),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Create');
    }

    public function store(StoreShopOwnerRequest $request): RedirectResponse
    {
        // Randomly generated rather than owner-chosen: there's no self-service
        // registration (see CLAUDE.md), so the admin hands this to the owner
        // out of band. Shown once via the flashed 'generatedPassword' prop.
        $password = Str::password(16);

        DB::transaction(function () use ($request, $password) {
            $owner = User::create([
                'name' => $request->string('owner_name')->value(),
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
            ]);
        });

        return redirect()->route('admin.index')
            ->with('generatedPassword', $password)
            ->with('createdOwnerEmail', $request->string('owner_email')->value());
    }
}
