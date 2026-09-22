<?php

namespace App\Models;

use App\Http\Controllers\Dev\ThemePreviewController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CustomTheme extends Model
{
    /**
     * Radius/button_radius are constrained to this preset list (not
     * freeform text) so the customizer can never save invalid CSS.
     */
    public const RADIUS_PRESETS = ['2px', '4px', '6px', '8px', '10px', '12px', '16px', '20px', '24px', '28px', '9999px'];

    protected $fillable = [
        'slug',
        'name',
        'base_theme_slug',
        'page_bg',
        'card_bg',
        'text',
        'muted',
        'accent',
        'accent_text',
        'stamp_filled',
        'stamp_empty',
        'border',
        'heading_font_name',
        'heading_font_fallback',
        'body_font_name',
        'body_font_fallback',
        'radius',
        'button_radius',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $theme): void {
            $theme->slug ??= static::uniqueSlugFor($theme->name);
        });
    }

    public static function uniqueSlugFor(string $name): string
    {
        $base = Str::slug($name) ?: 'custom-theme';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Shape this record exactly like the built-in theme arrays from
     * ThemePreviewController::themes(), so the same Show.jsx page renders
     * either with no special-casing.
     */
    public function toThemeArray(): array
    {
        $baseThemes = ThemePreviewController::themes();
        $baseName = $baseThemes[$this->base_theme_slug]['name'] ?? $this->base_theme_slug;

        $families = array_unique([$this->heading_font_name, $this->body_font_name]);
        $googleFonts = implode('|', array_map(
            fn ($family) => str_replace(' ', '+', $family),
            $families
        ));

        return [
            'name' => $this->name,
            'blurb' => "Your custom theme, based on {$baseName}.",
            'category' => 'custom',
            'mood' => $this->computeMood(),
            'google_fonts' => $googleFonts,
            'heading_font' => "'{$this->heading_font_name}', {$this->heading_font_fallback}",
            'body_font' => "'{$this->body_font_name}', {$this->body_font_fallback}",
            'page_bg' => $this->page_bg,
            'card_bg' => $this->card_bg,
            'text' => $this->text,
            'muted' => $this->muted,
            'accent' => $this->accent,
            'accent_text' => $this->accent_text,
            'stamp_filled' => $this->stamp_filled,
            'stamp_empty' => $this->stamp_empty,
            'border' => $this->border,
            'radius' => $this->radius,
            'button_radius' => $this->button_radius,
            'shadow' => '0 8px 20px -8px rgba(0,0,0,0.25)',
        ];
    }

    /**
     * Derived from page_bg's relative luminance rather than stored, so it
     * stays correct even after the color is edited.
     */
    private function computeMood(): string
    {
        $hex = ltrim($this->page_bg, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.5 ? 'light' : 'dark';
    }
}
