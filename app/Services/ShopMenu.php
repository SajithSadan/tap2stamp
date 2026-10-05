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
    }
}
