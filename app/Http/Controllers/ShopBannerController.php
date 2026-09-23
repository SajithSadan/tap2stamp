<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateShopBannerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The owner's banner photo, shown on their customer card page and behind the
 * registration sheet. Always the logged-in owner's own shop - no shop param.
 */
class ShopBannerController extends Controller
{
    public function update(UpdateShopBannerRequest $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        $old = $shop->banner_path;

        // Random file name (hashName) - never the owner's original filename.
        $path = $request->file('banner')->store("banners/{$shop->id}", 'uploads');

        $shop->update(['banner_path' => $path]);

        if ($old) {
            Storage::disk('uploads')->delete($old);
        }

        return redirect()->route('dashboard.theme');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;

        if ($shop->banner_path) {
            Storage::disk('uploads')->delete($shop->banner_path);
            $shop->update(['banner_path' => null]);
        }

        return redirect()->route('dashboard.theme');
    }
}
