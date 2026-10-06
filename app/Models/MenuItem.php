<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MenuItem extends Model
{
    /**
     * Tags are free text - each shop's menu has its own ("Vegan", "Halal",
     * "New", "Bestseller", "Contains alcohol"…). Limits mirrored in
     * resources/js/lib/menuTags.js.
     */
    public const MAX_TAGS = 6;

    public const TAG_LENGTH = 24;

    /**
     * Trimmed, single-spaced, empty and same-word duplicates (any case)
     * dropped, capped at MAX_TAGS. Keeps the first spelling of each.
     *
     * @return list<string>
     */
    public static function tidyTags(mixed $tags): array
    {
        return collect(is_array($tags) ? $tags : [])
            ->filter(fn ($tag) => is_string($tag))
            ->map(fn (string $tag) => mb_substr(trim(preg_replace('/\s+/u', ' ', $tag)), 0, self::TAG_LENGTH))
            ->filter()
            ->unique(fn (string $tag) => mb_strtolower($tag))
            ->take(self::MAX_TAGS)
            ->values()
            ->all();
    }

    protected $fillable = [
        'menu_section_id', 'name', 'description', 'price', 'tags', 'position',
        'image_path', 'image_status', 'image_confidence', 'image_reason',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array', 'image_confidence' => 'float'];
    }

    /** The item's photo (fetched + AI-checked, see MenuItemImages), or null. */
    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('uploads')->url($this->image_path) : null;
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
    }
}
