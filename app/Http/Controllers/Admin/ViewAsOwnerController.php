<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ViewAsOwner;
use App\Models\Shop;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Starts / stops the admin's "View as owner", and switches changes on or off (see ViewAsOwner). */
class ViewAsOwnerController extends Controller
{
    public function start(Request $request, Shop $shop): RedirectResponse
    {
        if ($shop->owner?->role !== UserRole::Owner) {
            return back()->with('status', "{$shop->name} has no owner account to view as.");
        }

        // Always starts read-only; changes are switched on from the banner.
        $request->session()->put(ViewAsOwner::SESSION_KEY, $shop->id);
        $request->session()->forget(ViewAsOwner::EDIT_KEY);
        ActivityLogger::record('view_as.started', "Started viewing {$shop->name} as its owner", $shop->id, $shop);

        return redirect()->route('dashboard.index');
    }

    /** The banner's "Allow changes" / "Back to read-only" switch. */
    public function editing(Request $request): RedirectResponse
    {
        abort_unless($request->session()->has(ViewAsOwner::SESSION_KEY), 404);

        $request->session()->put(ViewAsOwner::EDIT_KEY, $request->boolean('editing'));
        ActivityLogger::record('view_as.editing', $request->boolean('editing') ? 'Allowed changes while viewing as owner' : 'Back to read-only while viewing as owner', $request->session()->get(ViewAsOwner::SESSION_KEY));

        return back();
    }

    public function stop(Request $request): RedirectResponse
    {
        $request->session()->forget(ViewAsOwner::EDIT_KEY);
        $shopId = $request->session()->pull(ViewAsOwner::SESSION_KEY);
        if ($shopId) {
            ActivityLogger::record('view_as.stopped', 'Stopped viewing as owner', Shop::whereKey($shopId)->value('id'));
        }

        return $shopId && Shop::whereKey($shopId)->exists()
            ? redirect()->route('admin.shops.settings.edit', $shopId)
            : redirect()->route('admin.index');
    }
}
