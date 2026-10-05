/**
 * Menu item tags are free text - each shop's menu has its own labels.
 * Limits mirror App\Models\MenuItem (MAX_TAGS, TAG_LENGTH).
 */
export const MAX_TAGS = 6;
export const TAG_LENGTH = 24;

/** Offered while a menu has no tags of its own yet. */
const STARTER_TAGS = ["Vegetarian", "Vegan", "Gluten free", "Spicy", "New", "Bestseller"];

/** "  gluten   free " -> "gluten free" (same tidy-up as MenuItem::tidyTags). */
export function tidyTag(tag) {
    return tag.replace(/\s+/g, " ").trim().slice(0, TAG_LENGTH);
}

export function hasTag(tags, tag) {
    return tags.some((t) => t.toLowerCase() === tag.toLowerCase());
}

/** Tags already used on this menu (most used first), then starters it doesn't have yet. */
export function tagSuggestions(sections) {
    const counts = new Map();
    for (const section of sections) {
        for (const item of section.items) {
            for (const tag of item.tags) {
                const key = tag.toLowerCase();
                const seen = counts.get(key);
                counts.set(key, { tag: seen?.tag ?? tag, n: (seen?.n ?? 0) + 1 });
            }
        }
    }

    const used = [...counts.values()].sort((a, b) => b.n - a.n).map((t) => t.tag);

    return [...used, ...STARTER_TAGS.filter((t) => !hasTag(used, t))];
}
