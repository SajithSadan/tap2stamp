<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShopSettingsRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
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
                    'wifi_ssid', 'wifi_password',
                ]),
                // Contact person/email default to the owner's login.
                ...$shop->contactDetails(),
            ],
            'ownerEmail' => $shop->owner?->email,
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
        ]);
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
