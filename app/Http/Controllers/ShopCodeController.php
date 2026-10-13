<?php

namespace App\Http\Controllers;

use App\Models\ShopCodeLock;
use App\Services\ActivityLogger;
use App\Services\ShopCodeJoin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shop codes: a staff phone joining its shop by typing the code, the owner
 * changing their shop's code, and the admin unlocking a locked phone or
 * network. The rules are in App\Services\ShopCodeJoin.
 */
class ShopCodeController extends Controller
{
    /** The staff app's "Set up this device" screen (no device token yet). */
    public function join(Request $request, ShopCodeJoin $join): JsonResponse
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'browser_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{16,64}$/'],
        ], [
            'code.required' => 'Enter the shop code.',
        ]);

        [$status, $body] = $join->attempt($input['code'], $input['browser_id'], (string) $request->ip(), $request->userAgent());

        return response()->json($body, $status);
    }

    /** The same screen on opening: already locked? (So a reload doesn't show the form again.) */
    public function status(Request $request, ShopCodeJoin $join): JsonResponse
    {
        $input = $request->validate([
            'browser_id' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{16,64}$/'],
        ]);

        return response()->json($join->activeLock($input['browser_id'], (string) $request->ip())
            ? ['locked' => true, 'device_id' => ShopCodeLock::shortId($input['browser_id'])]
            : ['locked' => false]);
    }

    /** Owner: a new code (the old one stops working; devices already set up are unaffected). */
    public function regenerate(Request $request): RedirectResponse
    {
        $shop = $request->user()->shop;
        // Not fillable: only ever set here and in Shop::booted().
        $shop->forceFill(['shop_code' => $shop::uniqueShopCode()])->save();

        return back()->with('status', 'Your shop has a new code. The old one no longer works.');
    }

    /** Admin: let a locked phone or network try codes again. */
    public function unlock(Request $request, ShopCodeLock $shopCodeLock, ShopCodeJoin $join): RedirectResponse
    {
        abort_if($shopCodeLock->unlocked_at, 404);

        $join->unlock($shopCodeLock, $request->user()->id);
        ActivityLogger::record('shop_code.unlocked', $shopCodeLock->browser_id
            ? 'Unlocked device '.ShopCodeLock::shortId($shopCodeLock->browser_id).' for shop codes'
            : "Unlocked network {$shopCodeLock->ip} for shop codes");

        return back()->with('status', 'Unlocked. It can try shop codes again.');
    }
}
