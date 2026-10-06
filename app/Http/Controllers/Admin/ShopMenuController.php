<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MenuController;
use App\Http\Requests\Admin\ReadMenuRequest;
use App\Http\Requests\Admin\SaveShopMenuRequest;
use App\Models\MenuItem;
use App\Models\Shop;
use App\Services\MenuItemImages;
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
 * A shop's menu, managed by the admin for any shop (here) or by the owner for
 * their own (App\Http\Controllers\OwnerMenuController, same editor and rules):
 * Gemini reads a photo/PDF of the printed menu into the editor, it's checked
 * and saved.
 */
class ShopMenuController extends Controller
{
    public function edit(Shop $shop, ShopMenu $menu, MenuReader $reader): Response
    {
        return Inertia::render('Admin/Menu/Edit', self::editorProps($shop, $menu, $reader, [
            'base' => "/admin/shops/{$shop->id}/menu",
            'back' => "/admin/shops/{$shop->id}/settings#menu",
        ]));
    }

    /**
     * Everything the shared editor (Components/Menu/MenuEditor.jsx) needs.
     * $urls: `base` (GET/PUT/DELETE the menu, + /read, /theme) and `back` (or null).
     */
    public static function editorProps(Shop $shop, ShopMenu $menu, MenuReader $reader, array $urls): array
    {
        return [
            // What the menu page's header shows, for the live preview.
            'shop' => MenuController::header($shop) + ['id' => $shop->id],
            'sections' => $menu->sections($shop),
            'menuUrl' => $shop->menuUrl(),
            'urls' => $urls,
            'aiEnabled' => $reader->configured(),
            'imagesEnabled' => app(MenuItemImages::class)->enabled(),
            'maxFiles' => MenuReader::MAX_FILES,
            'themes' => collect(MenuThemes::all())->map(fn ($theme, $key) => ['key' => $key, ...$theme])->values(),
            'currentTheme' => $shop->menuTheme()['key'],
        ];
    }

    /** Next batch of menu item photos (or one item's "Find again"). */
    public function images(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        return self::fetchImages($request, $shop, $images);
    }

    /**
     * Body: none = the next few items never looked for; {item, query?} =
     * look again for one item of this shop (404 for anyone else's).
     */
    public static function fetchImages(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        abort_unless($images->enabled(), 404);
        $validated = $request->validate([
            'item' => ['nullable', 'integer'],
            'query' => ['nullable', 'string', 'max:120'],
            // "Find photos" with nothing left to try: look again for items that got none
            // (not those whose photo was taken off by hand).
            'retry' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('retry')) {
            MenuItem::whereIn('id', $shop->menuItems()->select('menu_items.id'))
                ->whereIn('image_status', ['not_found', 'rejected'])
                ->update(['image_status' => null, 'image_confidence' => null, 'image_reason' => null]);
        }

        // Catalog + Gemini calls take a few seconds each; shared hosting defaults to 30 s.
        @set_time_limit(120);

        if (isset($validated['item'])) {
            $item = $shop->menuItems()->where('menu_items.id', $validated['item'])->select('menu_items.*')->firstOrFail();
            $status = $images->fetchFor($item, $validated['query'] ?? null);

            return response()->json([
                'done' => $status === null ? 0 : 1,
                'remaining' => 0,
                'unavailable' => $status === null,
                'items' => $status === null ? [] : [MenuItemImages::summary($item->fresh('section'))],
            ]);
        }

        return response()->json($images->fetchNext($shop));
    }

    /** "Choose theme": saved straight away, it's only the look. */
    public function updateTheme(Request $request, Shop $shop): RedirectResponse
    {
        return self::saveTheme($request, $shop);
    }

    public static function saveTheme(Request $request, Shop $shop): RedirectResponse
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
