<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Block / unblock a shop (closing down, subscription unpaid). While blocked
 * its card page, menu, staff app and owner dashboard stop working; nothing
 * is deleted, and unblocking brings it all back as it was. Logged in the
 * activity log by Shop's RecordsActivity (blocked_at / blocked_reason).
 */
class ShopBlockController extends Controller
{
    public function store(Request $request, Shop $shop): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $shop->forceFill([
            'blocked_at' => $shop->blocked_at ?? now(),
            'blocked_reason' => $validated['reason'] ?? null,
        ])->save();

        return back()->with('status', "{$shop->name} is blocked.");
    }

    public function destroy(Shop $shop): RedirectResponse
    {
        $shop->forceFill(['blocked_at' => null, 'blocked_reason' => null])->save();

        return back()->with('status', "{$shop->name} is active again.");
    }
}
