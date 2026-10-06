<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * A shop's one menu (sections → items). The admin edits it as a whole and
 * saves it in one go, so saving replaces every section and item.
 */
class ShopMenu
{
    /** @return list<array{name: string, items: list<array<string, mixed>>}> */
    public function sections(Shop $shop): array
    {
        return $shop->menuSections()->with('items')->get()
            ->map(fn (MenuSection $section) => [
                'name' => $section->name,
                'items' => $section->items->map(fn (MenuItem $item) => [
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => $item->price,
                    'tags' => $item->tags ?? [],
                ])->all(),
            ])
            ->all();
    }

    /** @param  list<array<string, mixed>>  $sections  already validated */
    public function replace(Shop $shop, array $sections): void
    {
        $before = $this->sections($shop);

        DB::transaction(function () use ($shop, $sections) {
            // Items go with their sections (cascadeOnDelete).
            $shop->menuSections()->delete();

            foreach (array_values($sections) as $sectionPosition => $section) {
                $saved = $shop->menuSections()->create([
                    'name' => trim($section['name']),
                    'position' => $sectionPosition,
                ]);

                foreach (array_values($section['items'] ?? []) as $itemPosition => $item) {
                    $saved->items()->create([
                        'name' => trim($item['name']),
                        'description' => filled($item['description'] ?? null) ? trim($item['description']) : null,
                        'price' => filled($item['price'] ?? null) ? trim($item['price']) : null,
                        'tags' => MenuItem::tidyTags($item['tags'] ?? []) ?: null,
                        'position' => $itemPosition,
                    ]);
                }
            }
        });

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
        }
        $sectionNames = fn (array $sections) => array_column($sections, 'name');
        if ($sectionNames($before) !== $sectionNames($after)) {
            $changes['sections'] = [$sectionNames($before), $sectionNames($after)];
        }

        return $changes;
    }
}
