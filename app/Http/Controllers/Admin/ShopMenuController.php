<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MenuController;
use App\Http\Requests\Admin\ReadMenuRequest;
use App\Http\Requests\Admin\SaveShopMenuRequest;
use App\Models\Shop;
use App\Services\MenuReader;
use App\Services\ShopMenu;
use App\Support\MenuThemes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A shop's menu, managed by the admin only: Gemini reads a photo/PDF of the
 * printed menu into the editor, the admin checks it and saves.
 */
class ShopMenuController extends Controller
{
    public function edit(Shop $shop, ShopMenu $menu, MenuReader $reader): Response
    {
        return Inertia::render('Admin/Menu/Edit', [
            // What the menu page's header shows, for the live preview.
            'shop' => MenuController::header($shop) + ['id' => $shop->id],
            'sections' => $menu->sections($shop),
            'menuUrl' => route('menu.show', $shop),
            'aiEnabled' => $reader->configured(),
            'maxFiles' => MenuReader::MAX_FILES,
            'themes' => collect(MenuThemes::all())->map(fn ($theme, $key) => ['key' => $key, ...$theme])->values(),
            'currentTheme' => $shop->menuTheme()['key'],
        ]);
    }

    /** "Choose theme": saved straight away, it's only the look. */
    public function updateTheme(Request $request, Shop $shop): RedirectResponse
    {
        $validated = $request->validate(['theme' => ['required', Rule::in(array_keys(MenuThemes::all()))]]);

        $shop->update(['menu_theme' => $validated['theme']]);

        return back()->with('status', 'Menu theme set to '.MenuThemes::resolve($validated['theme'])['name'].'.');
    }

    /** Reads the uploaded menu with Gemini. Saves nothing - the editor shows it for checking. */
    public function read(ReadMenuRequest $request, MenuReader $reader): JsonResponse
    {
        abort_unless($reader->configured(), 404);

        // Reading a long menu can take a while; shared hosting defaults to 30 s.
        @set_time_limit(120);

        $result = $reader->read($request->file('files'));

        return match ($result['status']) {
            'ok' => response()->json(['sections' => $result['sections']]),
            'unreadable' => response()->json(['message' => "Couldn't find a menu in that. Try a clearer, straight-on photo."], 422),
            'busy' => response()->json(['message' => 'Gemini is very busy right now. Try again in a minute.'], 503),
            default => response()->json(['message' => 'The menu reader is unavailable right now. Try again in a minute.'], 503),
        };
    }

    public function update(SaveShopMenuRequest $request, Shop $shop, ShopMenu $menu): RedirectResponse
    {
        $menu->replace($shop, $request->validated('sections'));

        return back()->with('status', "Menu saved for {$shop->name}.");
    }

    public function destroy(Shop $shop, ShopMenu $menu): RedirectResponse
    {
        $menu->replace($shop, []);

        return back()->with('status', "{$shop->name}'s menu was cleared.");
    }
}
