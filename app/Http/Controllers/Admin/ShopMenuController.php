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
use App\Support\Features;
use App\Support\MenuThemes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
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
        ]) + [
            // The admin can prepare a menu while it's off; the editor says so.
            'menuOff' => ! $shop->hasFeature(Features::MENU),
        ]);
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
            // Who checks catalog photos: 'ai' (Gemini) or 'manual' (the editor asks "Is this …?").
            'photoCheck' => app(MenuItemImages::class)->mode(),
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
        abort_unless($images->enabled() && $images->mode() === MenuItemImages::MODE_AI, 404);
        $validated = $request->validate([
            'item' => ['nullable', 'integer'],
            'query' => ['nullable', 'string', 'max:120'],
            // "Find photos" with nothing left to try: look again for items that got none
            // (not those whose photo was taken off by hand).
            'retry' => ['nullable', 'boolean'],
            // The editor's progress panel asks for one item at a time, so it knows which one is being checked.
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.MenuItemImages::BATCH],
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
            $outcome = $images->fetchFor($item, $validated['query'] ?? null);

            return response()->json([
                'done' => $outcome === null ? 0 : 1,
                'remaining' => 0,
                'unavailable' => $outcome === null,
                'items' => $outcome === null ? [] : [MenuItemImages::summary($item->fresh('section'), $outcome)],
            ]);
        }

        return response()->json($images->fetchNext($shop, $validated['limit'] ?? MenuItemImages::BATCH));
    }

    public function reviewCandidates(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        return self::photoCandidates($request, $shop, $images);
    }

    public function reviewImage(Shop $shop, string $token, MenuItemImages $images): HttpResponse
    {
        return self::heldPhoto($shop, $token, $images);
    }

    public function reviewConfirm(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        return self::confirmPhoto($request, $shop, $images);
    }

    /**
     * Manual photo checks, step 1 ({item, query?}): the item's catalog photos
     * for the editor to ask "Is this …?" about. None = marked not found, and
     * the item's summary comes back like an AI search's.
     */
    public static function photoCandidates(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        abort_unless($images->enabled() && $images->mode() === MenuItemImages::MODE_MANUAL, 404);
        $validated = $request->validate([
            'item' => ['required', 'integer'],
            'query' => ['nullable', 'string', 'max:120'],
        ]);
        $item = self::shopItem($shop, $validated['item']);
        @set_time_limit(120); // up to max_candidates catalog downloads

        $candidates = $images->forReview($item, $validated['query'] ?? null);
        $base = request()->routeIs('admin.*') ? "/admin/shops/{$shop->id}/menu" : '/dashboard/menu';

        return response()->json([
            'candidates' => collect($candidates)->map(fn (array $c) => [
                'token' => $c['token'],
                'label' => $c['label'],
                'url' => "{$base}/images/review/{$c['token']}",
            ])->all(),
            'items' => $candidates === [] ? [MenuItemImages::summary($item->fresh('section'), ['status' => 'not_found', 'reason' => 'Not in the product catalog'])] : [],
        ]);
    }

    /** A held candidate photo, only to the shop it was fetched for. */
    public static function heldPhoto(Shop $shop, string $token, MenuItemImages $images): HttpResponse
    {
        [$bytes, $mime] = $images->reviewImage($shop, $token) ?? abort(404);

        return response($bytes, 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=600']);
    }

    /** Manual photo checks, step 2 ({item, token}): "yes" to that photo; token null = "no" to all of them. */
    public static function confirmPhoto(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        abort_unless($images->enabled() && $images->mode() === MenuItemImages::MODE_MANUAL, 404);
        $validated = $request->validate([
            'item' => ['required', 'integer'],
            'token' => ['nullable', 'string', 'size:40'],
        ]);
        $item = self::shopItem($shop, $validated['item']);

        $outcome = $images->confirm($item, $validated['token'] ?? null, $request->user()->name);
        abort_if($outcome === null, 422, 'That photo is no longer available - look for photos again.');

        return response()->json(['items' => [MenuItemImages::summary($item->fresh('section'), $outcome)]]);
    }

    public function uploadPhoto(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        return self::storeUpload($request, $shop, $images);
    }

    /**
     * The shop's own photo for an item ({item, photo}), cropped + compressed
     * in the editor first. Works in any photo-check mode, even with no catalog.
     */
    public static function storeUpload(Request $request, Shop $shop, MenuItemImages $images): JsonResponse
    {
        $validated = $request->validate([
            'item' => ['required', 'integer'],
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:min_width=200,min_height=200'],
        ], [
            'photo.mimes' => 'Choose a JPG, PNG or WebP photo.',
            'photo.max' => 'That photo is too big - choose one under 4 MB.',
            'photo.dimensions' => 'That photo is too small - choose one at least 200 x 200.',
        ]);
        $item = self::shopItem($shop, $validated['item']);

        $outcome = $images->upload($item, $request->file('photo')->get(), $request->user()->name);
        abort_if($outcome === null, 422, "That photo couldn't be read - try another one.");

        return response()->json(['items' => [['id' => $item->id] + MenuItemImages::summary($item->fresh('section'), $outcome)]]);
    }

    /** One item of this shop's menu (404 for anyone else's). */
    private static function shopItem(Shop $shop, int $id): MenuItem
    {
        return $shop->menuItems()->where('menu_items.id', $id)->select('menu_items.*')->with('section')->firstOrFail();
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
