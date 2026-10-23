<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShopSettingsRequest;
use App\Models\Plan;
use App\Models\Product;
use App\Models\QrDesign;
use App\Models\Setting;
use App\Models\Shop;
use App\Models\ShopNote;
use App\Models\ShopTask;
use App\Services\CrmAssistant;
use App\Services\ShopHealth;
use App\Services\ShopPayments;
use App\Services\ShopTimeline;
use App\Support\Countries;
use App\Support\CrmStage;
use App\Support\Features;
use App\Support\Industries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShopSettingsController extends Controller
{
    public function edit(Shop $shop, ShopHealth $health, ShopTimeline $timeline, CrmAssistant $assistant): Response
    {
        return Inertia::render('Admin/ShopSettings', [
            // WhatsApp tab: the shop's own number for offers (null = not connected).
            'whatsappAccount' => $shop->whatsappAccount?->summary(),
            // Account tab (MONETISATION-PLAN.md phase 1): usage health and the sales pipeline.
            'health' => $health->forShop($shop),
            'healthModel' => ['ladder' => ShopHealth::LADDER, 'score' => ShopHealth::SCORE],
            'pipeline' => [
                'crm_stage' => $shop->crm_stage,
                'trial_ends_at' => $shop->trial_ends_at?->timezone('Europe/London')->format('Y-m-d'),
                'lost_reason' => $shop->lost_reason,
                // Subscription: the plan, and paid up to (null = never paid, on the trial).
                'plan_id' => $shop->plan_id,
                'subscription_ends_at' => $shop->subscription_ends_at?->timezone('Europe/London')->format('Y-m-d'),
            ],
            'subscription' => $shop->subscriptionSummary(),
            'plans' => Plan::ordered()->get(['id', 'name', 'is_active', 'is_default']),
            // Every payment of the shop: plan payments and paid orders, newest first (the Payments tab).
            'payments' => app(ShopPayments::class)->rows($shop),
            'crmStages' => CrmStage::STORED,
            // Phase 2: notes, tags, follow-ups, the shop's timeline, AI "Brief me".
            'crm' => [
                'tags' => $shop->crm_tags ?? [],
                'lead_status' => $shop->lead_status,
                'tag_suggestions' => self::TAG_SUGGESTIONS,
                'notes' => $shop->notes()->with('author:id,name')->latest()->latest('id')->limit(50)->get()->map(fn (ShopNote $note) => $note->toRow()),
                // Open first (soonest due), then the last 10 done.
                'tasks' => $shop->tasks()->open()->orderByRaw('due_on IS NULL, due_on')->get()
                    ->concat($shop->tasks()->whereNotNull('done_at')->latest('done_at')->limit(10)->get())
                    ->map(fn (ShopTask $task) => $task->toRow())->values(),
                'timeline' => $timeline->for($shop),
                'ai' => $assistant->enabled(),
            ],
            'shop' => [
                ...$shop->only([
                    'id', 'name', 'slug', 'max_stamps', 'reward_title',
                    'google_review_url', 'google_review_direct', 'show_card_link', 'instagram_url',
                    'wifi_ssid', 'wifi_password', 'qr_design_id', 'industry', 'industry_other',
                ]),
                'can_order' => $shop->canOrderProducts(),
                'whatsapp' => $shop->whatsappNumber(),
                'welcome_sent_label' => $shop->owner?->welcome_sent_at?->timezone('Europe/London')->format('j M Y'),
                // "View menu" on the loyalty card (Menu tab), switchable only while Menu is on.
                'show_menu_on_card' => (bool) $shop->show_menu_on_card,
                'menu_enabled' => $shop->hasFeature(Features::MENU),
                'blocked_at' => $shop->blocked_at?->toIso8601String(),
                'blocked_label' => $shop->blocked_at?->timezone('Europe/London')->format('j M Y, H:i'),
                'blocked_reason' => $shop->blocked_reason,
                // Contact person/email default to the owner's login.
                ...$shop->contactDetails(),
            ],
            'ownerEmail' => $shop->owner?->email,
            // Owner features: this shop's override (on / off / null = the platform default).
            'features' => collect(Features::ALL)->map(fn (array $feature, string $key) => [
                'key' => $key,
                ...$feature,
                'default' => Features::default($key),
                'override' => $shop->featureOverride($key),
            ])->values(),
            'menuItemsCount' => $shop->menuItems()->count(),
            'menuUrl' => $shop->menuUrl(),
            'previewUrl' => route('card.show', ['shop' => $shop, 'preview' => 1]),
            // Product orders: what's been ordered, and the "record an order"
            // form for payments taken outside the app.
            'productOrderedAt' => $shop->product_ordered_at?->timezone('Europe/London')->format('j M Y'),
            'orders' => $shop->orders()->with('placedBy:id,name')->latest()->get()->map(OrderController::row(...)),
            'products' => Product::active()->orderByDesc('is_featured')->orderBy('name')->get(['id', 'name', 'price_pence', 'price_tiers']),
            'paymentMethods' => collect(PaymentMethod::manual())->map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()]),
            'deliveryAddress' => $shop->deliveryAddress(),
            'bankDetailsSet' => filled(Setting::get(Setting::BANK_DETAILS)),
            'countries' => Countries::options(),
            'industries' => Industries::options(),
            // Overseas shops: the QR codes mapped to this shop, and the design the owner downloads them in.
            'assignedQrCount' => $shop->qrCodes()->count(),
            'qrDesigns' => QrDesign::orderBy('name')->get(['id', 'name']),
            // What "no design" falls back to for this shop's owner download.
            'defaultQrDesign' => QrDesign::defaultDesign()?->name,
        ]);
    }

    /** One-tap tags on the Account tab (any other text works too). */
    public const TAG_SUGGESTIONS = ['Hot lead', 'Owner not techy', 'Needs display', 'Staff forget to scan', 'Price objection', 'Multiple branches', 'Wants a call back'];

    /**
     * The shop's place in the sales pipeline: stage, trial end (e.g. extended
     * for an active shop) and why it was lost. Logged by RecordsActivity.
     */
    /** Pause (or resume) this shop's scanning - the admin's call, never automatic. Logged by RecordsActivity. */
    public function updateScanning(Request $request, Shop $shop): RedirectResponse
    {
        $paused = (bool) $request->validate(['paused' => ['required', 'boolean']])['paused'];
        $shop->forceFill(['scanning_paused_at' => $paused ? ($shop->scanning_paused_at ?? now()) : null])->save();

        return back()->with('status', $paused ? 'Scanning paused for this shop.' : 'Scanning resumed for this shop.');
    }

    public function updateAccount(Request $request, Shop $shop): RedirectResponse
    {
        $input = $request->validate([
            'crm_stage' => ['required', Rule::in(array_keys(CrmStage::STORED))],
            'trial_ends_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        $shop->forceFill([
            'crm_stage' => $input['crm_stage'],
            // End of that day, UK time - "ends 30 Oct" means all of the 30th.
            'trial_ends_at' => filled($input['trial_ends_at'] ?? null)
                ? Carbon::parse($input['trial_ends_at'], 'Europe/London')->endOfDay()
                : null,
            'lost_reason' => $input['crm_stage'] === CrmStage::LOST ? (trim((string) ($input['lost_reason'] ?? '')) ?: null) : null,
            // Not sent = unchanged (older forms); sent empty = no plan / never paid.
            ...$request->has('plan_id') ? ['plan_id' => $input['plan_id'] ?? null] : [],
            ...$request->has('subscription_ends_at') ? ['subscription_ends_at' => filled($input['subscription_ends_at'] ?? null)
                ? Carbon::parse($input['subscription_ends_at'], 'Europe/London')->endOfDay()
                : null] : [],
        ])->save();

        return back()->with('status', "Account saved for {$shop->name}.");
    }

    /** Per-shop feature overrides: {features: {menu: 'default' | 'on' | 'off', ...}}. */
    public function updateFeatures(Request $request, Shop $shop): RedirectResponse
    {
        $input = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['required', Rule::in(['default', 'on', 'off'])],
        ]);

        $features = $shop->features ?? [];
        foreach (array_intersect_key($input['features'], Features::ALL) as $key => $choice) {
            if ($choice === 'default') {
                unset($features[$key]);
            } else {
                $features[$key] = $choice === 'on';
            }
        }

        $shop->forceFill(['features' => $features ?: null])->save();

        return back()->with('status', "Features saved for {$shop->name}.");
    }

    public function update(UpdateShopSettingsRequest $request, Shop $shop): RedirectResponse
    {
        $values = $request->shopValues();
        $values['google_review_direct'] = filled($values['google_review_url'] ?? null)
            && (bool) ($values['google_review_direct'] ?? false);

        $shop->update($values);

        return back()->with('status', "Settings saved for {$shop->name}.");
    }
}
