<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\ShopMenuController;
use App\Http\Requests\Admin\ReadMenuRequest;
use App\Http\Requests\Admin\SaveShopMenuRequest;
use App\Services\MenuReader;
use App\Services\ShopMenu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's own menu (/dashboard/menu): the admin's menu editor, always for
 * auth()->user()->shop - no shop in the URL, like every owner dashboard route.
 */
class OwnerMenuController extends Controller
{
    public function edit(Request $request, ShopMenu $menu, MenuReader $reader): Response
    {
        $shop = $request->user()->shop;
        $props = ShopMenuController::editorProps($shop, $menu, $reader, ['base' => '/dashboard/menu', 'back' => null]);

        // OwnerLayout reads the dashboard summary from `shop`; the preview reads the header.
        $props['shop'] += DashboardController::shopSummary($shop);

        return Inertia::render('Dashboard/Menu', $props);
    }

    public function read(ReadMenuRequest $request, MenuReader $reader): JsonResponse
    {
        return app(ShopMenuController::class)->read($request, $reader);
    }

    public function update(SaveShopMenuRequest $request, ShopMenu $menu): RedirectResponse
    {
        $menu->replace($request->user()->shop, $request->validated('sections'));

        return back()->with('status', 'Menu saved.');
    }

    public function destroy(Request $request, ShopMenu $menu): RedirectResponse
    {
        $menu->replace($request->user()->shop, []);

        return back()->with('status', 'Your menu was cleared.');
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        return ShopMenuController::saveTheme($request, $request->user()->shop);
    }
}
