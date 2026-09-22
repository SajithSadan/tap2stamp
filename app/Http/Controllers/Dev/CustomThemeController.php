<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomThemeRequest;
use App\Http\Requests\UpdateCustomThemeRequest;
use App\Models\CustomTheme;
use App\Support\CuratedFonts;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomThemeController extends Controller
{
    public function store(StoreCustomThemeRequest $request): RedirectResponse
    {
        $customTheme = CustomTheme::create($this->withFontFallbacks($request->validated()));

        return redirect()->route('dev.custom-themes.show', $customTheme);
    }

    public function show(CustomTheme $customTheme): Response
    {
        return Inertia::render('Dev/Themes/Show', [
            'slug' => $customTheme->slug,
            'theme' => $customTheme->toThemeArray(),
            'isCustom' => true,
            // Raw stored fields (not the reconstructed CSS/query strings in
            // toThemeArray()) so the customizer form can pre-fill exactly
            // what was saved, without re-parsing anything.
            'customThemeData' => [
                'base_theme_slug' => $customTheme->base_theme_slug,
                'heading_font_name' => $customTheme->heading_font_name,
                'heading_font_fallback' => $customTheme->heading_font_fallback,
                'body_font_name' => $customTheme->body_font_name,
                'body_font_fallback' => $customTheme->body_font_fallback,
                'radius' => $customTheme->radius,
                'button_radius' => $customTheme->button_radius,
            ],
            'availableFonts' => CuratedFonts::all(),
            'radiusPresets' => CustomTheme::RADIUS_PRESETS,
        ]);
    }

    public function update(UpdateCustomThemeRequest $request, CustomTheme $customTheme): RedirectResponse
    {
        $customTheme->update($this->withFontFallbacks($request->validated()));

        return redirect()->route('dev.custom-themes.show', $customTheme);
    }

    public function destroy(CustomTheme $customTheme): RedirectResponse
    {
        $customTheme->delete();

        return redirect()->route('dev.themes.index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withFontFallbacks(array $data): array
    {
        $data['heading_font_fallback'] = CuratedFonts::fallbackFor($data['heading_font_name']) ?? 'sans-serif';
        $data['body_font_fallback'] = CuratedFonts::fallbackFor($data['body_font_name']) ?? 'sans-serif';

        return $data;
    }
}
