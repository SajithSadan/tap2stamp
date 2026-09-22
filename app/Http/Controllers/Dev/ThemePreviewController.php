<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Temporary, dev-only: renders the same customer card UI in a few candidate
 * visual themes so a theme can be picked by eye before it's baked into the
 * real Stage 0 layout. Deleted once a theme is chosen.
 */
class ThemePreviewController extends Controller
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function themes(): array
    {
        return [
            'warm-artisan' => [
                'name' => 'Warm Artisan',
                'blurb' => 'Cafe & bakery feel — terracotta and cream, soft rounded cards.',
                'google_fonts' => 'Playfair+Display:wght@600;700|Nunito+Sans:wght@400;600;700',
                'heading_font' => "'Playfair Display', serif",
                'body_font' => "'Nunito Sans', sans-serif",
                'page_bg' => '#FBF3E7',
                'card_bg' => '#FFFDF9',
                'text' => '#3E2723',
                'muted' => '#8D7B68',
                'accent' => '#C1653B',
                'accent_text' => '#FFFFFF',
                'stamp_filled' => '#C1653B',
                'stamp_empty' => '#EFE0CE',
                'border' => '#EFE0CE',
                'radius' => '24px',
                'button_radius' => '9999px',
                'shadow' => '0 8px 24px -8px rgba(62,39,35,0.25)',
            ],
            'modern-minimal' => [
                'name' => 'Modern Minimal',
                'blurb' => 'Neutral and crisp — works for any shop type, low visual noise.',
                'google_fonts' => 'Inter:wght@400;500;600;700',
                'heading_font' => "'Inter', sans-serif",
                'body_font' => "'Inter', sans-serif",
                'page_bg' => '#FAFAF9',
                'card_bg' => '#FFFFFF',
                'text' => '#1C1917',
                'muted' => '#78716C',
                'accent' => '#10B981',
                'accent_text' => '#FFFFFF',
                'stamp_filled' => '#10B981',
                'stamp_empty' => '#E7E5E4',
                'border' => '#E7E5E4',
                'radius' => '10px',
                'button_radius' => '8px',
                'shadow' => '0 1px 3px 0 rgba(0,0,0,0.08)',
            ],
            'bold-local' => [
                'name' => 'Bold Local',
                'blurb' => 'High-contrast navy and amber — pops on a phone screen at arm\'s length.',
                'google_fonts' => 'Poppins:wght@500;600;700;800',
                'heading_font' => "'Poppins', sans-serif",
                'body_font' => "'Poppins', sans-serif",
                'page_bg' => '#0B1F3A',
                'card_bg' => '#122A4D',
                'text' => '#FFFFFF',
                'muted' => '#9FB3D1',
                'accent' => '#F5A623',
                'accent_text' => '#1B1200',
                'stamp_filled' => '#F5A623',
                'stamp_empty' => '#1E3A63',
                'border' => '#1E3A63',
                'radius' => '28px',
                'button_radius' => '9999px',
                'shadow' => '0 8px 24px -8px rgba(0,0,0,0.5)',
            ],
            'classic-british' => [
                'name' => 'Classic British',
                'blurb' => 'Pub & barber heritage — deep green with brass accents, traditional serif.',
                'google_fonts' => 'Libre+Baskerville:wght@400;700|Inter:wght@400;500;600',
                'heading_font' => "'Libre Baskerville', serif",
                'body_font' => "'Inter', sans-serif",
                'page_bg' => '#12261E',
                'card_bg' => '#173328',
                'text' => '#F3EFE6',
                'muted' => '#A9BDB1',
                'accent' => '#C9A227',
                'accent_text' => '#1B1502',
                'stamp_filled' => '#C9A227',
                'stamp_empty' => '#2A4A3B',
                'border' => '#2A4A3B',
                'radius' => '18px',
                'button_radius' => '6px',
                'shadow' => '0 8px 24px -8px rgba(0,0,0,0.45)',
            ],
        ];
    }

    public function index(): View
    {
        return view('dev.themes.index', ['themes' => self::themes()]);
    }

    public function show(string $slug): View
    {
        abort_unless(array_key_exists($slug, self::themes()), 404);

        return view('dev.themes.show', [
            'slug' => $slug,
            'theme' => self::themes()[$slug],
        ]);
    }
}
