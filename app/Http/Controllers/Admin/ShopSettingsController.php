<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShopSettingsRequest;
use App\Models\Product;
use App\Models\QrDesign;
use App\Models\Setting;
use App\Models\Shop;
use App\Support\Countries;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShopSettingsController extends Controller
{
    public function edit(Shop $shop): Response
    {
        return Inertia::render('Admin/ShopSettings', [
            'shop' => [
                ...$shop->only([
                    'id', 'name', 'slug', 'max_stamps', 'reward_title',
                    'google_review_url', 'google_review_direct', 'show_card_link', 'instagram_url',
                    'wifi_ssid', 'wifi_password', 'qr_design_id',
                ]),
                'can_order' => $shop->canOrderProducts(),
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
            // Overseas shops: the QR codes mapped to this shop, and the design the owner downloads them in.
            'assignedQrCount' => $shop->qrCodes()->count(),
            'qrDesigns' => QrDesign::orderBy('name')->get(['id', 'name']),
            // What "no design" falls back to for this shop's owner download.
            'defaultQrDesign' => QrDesign::defaultDesign()?->name,
        ]);
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
