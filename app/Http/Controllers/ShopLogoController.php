<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShopLogoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The owner's logo, shown in the round badge on their customer card page and
 * sign-up screen. Always the logged-in owner's own shop - no shop param.
 * Same storage as the banner (ShopBannerController).
 */
class ShopLogoController extends Controller
{
    public function update(UpdateShopLogoRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $old = $shop->logo_path;

        // Random file name (hashName) - never the owner's original filename.
        $path = $request->file('logo')->store("logos/{$shop->id}", 'uploads');

        $shop->update(['logo_path' => $path]);

        if ($old) {
            Storage::disk('uploads')->delete($old);
        }

        return redirect()->route('dashboard.theme');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shop->logo_path) {
            Storage::disk('uploads')->delete($shop->logo_path);
            $shop->update(['logo_path' => null]);
        }

        return redirect()->route('dashboard.theme');
    }
}
