<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A shop's one menu (sections → items). The admin edits it as a whole and
 * saves it in one go, so saving replaces every section and item.
 */
class ShopMenu
{
    /**
     * The menu as the editor and the public page get it. `id` and the image
     * fields are for the editor's photo actions (ids change on every save).
     *
     * @return list<array{name: string, items: list<array<string, mixed>>}>
     */
    public function sections(Shop $shop): array
    {
        return $shop->menuSections()->with('items')->get()
            ->map(fn (MenuSection $section) => [
                'name' => $section->name,
                'items' => $section->items->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'tags' => $item->tags ?? [],
                    'image_path' => $item->image_path,
                    'image_url' => $item->imageUrl(),
                    'image_status' => $item->image_status,
                    'image_reason' => $item->image_reason,
                ])->all(),
            ])
            ->all();
    }

    /**
     * Saves the whole menu. Photos survive the delete + recreate: an item
     * keeps a photo only if the editor sent back a path this shop's items
     * already had (never a path made up in the browser); sending null for an
     * item that had one takes it off ("removed", so it isn't fetched again).
     * Items sent without an image_path at all keep the same-named item's photo.
     *
     * @param  list<array<string, mixed>>  $sections  already validated
     */
    public function replace(Shop $shop, array $sections): void
    {
        $before = $this->sections($shop);
        $oldItems = collect($before)->flatMap(fn ($section) => $section['items']);
        $byPath = $oldItems->filter(fn ($item) => $item['image_path'])->keyBy('image_path');
        $byName = $oldItems->keyBy(fn ($item) => mb_strtolower($item['name']));
        $confidence = MenuItem::whereIn('id', $oldItems->pluck('id'))->pluck('image_confidence', 'id');
        $used = [];

        DB::transaction(function () use ($shop, $sections, $byPath, $byName, $confidence, &$used) {
            // Items go with their sections (cascadeOnDelete).
            $shop->menuSections()->delete();

            foreach (array_values($sections) as $sectionPosition => $section) {
                $saved = $shop->menuSections()->create([
                    'name' => trim($section['name']),
                    'position' => $sectionPosition,
                ]);

                foreach (array_values($section['items'] ?? []) as $itemPosition => $item) {
                    $photo = $this->photoFor($item, $byPath, $byName, $confidence, $used);

                    $saved->items()->create([
                        'name' => trim($item['name']),
                        'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
                        'price' => filled($item['price'] ?? null) ? trim($item['price']) : null,
                        'tags' => MenuItem::tidyTags($item['tags'] ?? []) ?: null,
                        'position' => $itemPosition,
                        ...$photo,
                    ]);
                }
            }
        });

        // Photos no item uses any more (taken off, or their item deleted).
        $orphans = $byPath->keys()->diff(array_keys($used))->all();
        if ($orphans !== []) {
            Storage::disk('uploads')->delete($orphans);
        }

        $after = $this->sections($shop);
        if ($before !== $after) {
            ActivityLogger::record(
                $after === [] ? 'menu.cleared' : 'menu.saved',
                $after === [] ? 'Cleared the menu' : 'Saved the menu',
                $shop->id,
                $shop,
                self::diff($before, $after),
            );
        }
    }

    /**
     * The image columns for one saved item (see replace()).
     *
     * @param  array<string, true>  $used  paths already given to an item in this save
     */
    private function photoFor(array $item, Collection $byPath, Collection $byName, Collection $confidence, array &$used): array
    {
        $none = ['image_path' => null, 'image_status' => null, 'image_confidence' => null, 'image_reason' => null];
        $sameName = $byName->get(mb_strtolower(trim($item['name'])));

        // The editor didn't say: keep what the same-named item had.
        $path = array_key_exists('image_path', $item) ? $item['image_path'] : ($sameName['image_path'] ?? null);

        if ($path && ($old = $byPath->get($path)) && ! isset($used[$path])) {
            $used[$path] = true;

            return [
                'image_path' => $path,
                'image_status' => $old['image_status'] ?? 'found',
                'image_confidence' => $confidence->get($old['id']),
                'image_reason' => $old['image_reason'],
            ];
        }

        if (! $sameName) {
            return $none; // a new item - looked for after the save
        }

        // It had a photo and the editor took it off: don't fetch it back.
        if ($sameName['image_path']) {
            return [...$none, 'image_status' => 'removed', 'image_reason' => 'Photo taken off'];
        }

        // Already looked for (not found / rejected / removed): keep the verdict.
        return [
            ...$none,
            'image_status' => $sameName['image_status'],
            'image_confidence' => $confidence->get($sameName['id']),
            'image_reason' => $sameName['image_reason'],
        ];
    }

    /**
     * What a save changed, for the activity log: items added / removed by
     * name, and each kept item's changed price / description / tags / section.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $flatten = fn (array $sections) => collect($sections)->flatMap(
            fn ($section) => collect($section['items'])->mapWithKeys(fn ($item) => [$item['name'] => [...$item, 'section' => $section['name']]])
        );
        $old = $flatten($before);
        $new = $flatten($after);
        $changes = [];

        if ($added = $new->keys()->diff($old->keys())->values()->all()) {
            $changes['items added'] = [null, $added];
        }
        if ($removed = $old->keys()->diff($new->keys())->values()->all()) {
            $changes['items removed'] = [$removed, null];
        }
        foreach ($new->intersectByKeys($old) as $name => $item) {
            foreach (['price', 'description', 'tags', 'section'] as $field) {
                if ($old[$name][$field] !== $item[$field]) {
                    $changes["{$name} · {$field}"] = [$old[$name][$field], $item[$field]];
                }
            }
            if (($old[$name]['image_path'] ?? null) !== ($item['image_path'] ?? null)) {
                $changes["{$name} · photo"] = [$old[$name]['image_path'] ? 'photo' : null, ($item['image_path'] ?? null) ? 'photo' : null];
            }
        }
        $sectionNames = fn (array $sections) => array_column($sections, 'name');
        if ($sectionNames($before) !== $sectionNames($after)) {
            $changes['sections'] = [$sectionNames($before), $sectionNames($after)];
        }

        return $changes;
    }
}
