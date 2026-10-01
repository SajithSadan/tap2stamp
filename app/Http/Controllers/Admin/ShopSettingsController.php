<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShopSettingsRequest;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ShopSettingsController extends Controller
{
    public function edit(Shop $shop): Response
    {
        return Inertia::render('Admin/ShopSettings', [
            'shop' => $shop->only([
                'id', 'name', 'slug', 'max_stamps', 'reward_title',
                'google_review_url', 'google_review_direct', 'instagram_url',
                'wifi_ssid', 'wifi_password', 'contact_name', 'contact_email',
                'contact_phone', 'address_line1', 'address_line2', 'town',
                'postcode', 'delivery_address',
            ]),
        ]);
    }

    public function update(UpdateShopSettingsRequest $request, Shop $shop): RedirectResponse
    {
        $values = $request->validated();
        $values['google_review_direct'] = filled($values['google_review_url'] ?? null)
            && (bool) ($values['google_review_direct'] ?? false);

        $shop->update($values);

        return back()->with('status', "Settings saved for {$shop->name}.");
    }
}
