<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\CustomTheme;
use App\Support\CuratedFonts;
use App\Support\ThemeCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dev-only internal tool: browse the built-in theme catalog and (via
 * CustomThemeController) save customized variants to the database. Monochrome
 * Barber was picked as the real site's default (see resources/css/app.css);
 * this tool stays around as ongoing tooling, not one-time scaffolding.
 *
 * The catalog itself lives in App\Support\ThemeCatalog, shared with the
 * owner's /dashboard/theme picker.
 */
class ThemePreviewController extends Controller
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function themes(): array
    {
        return ThemeCatalog::all();
    }

    public function index(): Response
    {
        return Inertia::render('Dev/Themes/Index', [
            'themes' => self::themes(),
            'customThemes' => CustomTheme::latest()->get()
                ->map(fn (CustomTheme $theme) => [
                    'slug' => $theme->slug,
                    'theme' => $theme->toThemeArray(),
                ]),
        ]);
    }

    public function show(string $slug): Response
    {
        abort_unless(array_key_exists($slug, self::themes()), 404);

        return Inertia::render('Dev/Themes/Show', [
            'slug' => $slug,
            'theme' => self::themes()[$slug],
            'isCustom' => false,
            'availableFonts' => CuratedFonts::all(),
            'radiusPresets' => CustomTheme::RADIUS_PRESETS,
        ]);
    }
}
