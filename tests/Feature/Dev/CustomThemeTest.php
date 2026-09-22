<?php

use App\Models\CustomTheme;

function validCustomThemePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'My Custom Barber Theme',
        'base_theme_slug' => 'monochrome-barber',
        'page_bg' => '#F5F5F5',
        'card_bg' => '#FFFFFF',
        'text' => '#111111',
        'muted' => '#6B6B6B',
        'accent' => '#C1272D',
        'accent_text' => '#FFFFFF',
        'stamp_filled' => '#111111',
        'stamp_empty' => '#E0E0E0',
        'border' => '#E0E0E0',
        'heading_font_name' => 'Oswald',
        'body_font_name' => 'Inter',
        'radius' => '4px',
        'button_radius' => '4px',
    ], $overrides);
}

test('a custom theme can be created from valid data', function () {
    $response = $this->post('/dev/custom-themes', validCustomThemePayload());

    $theme = CustomTheme::first();

    expect($theme)->not->toBeNull();
    expect($theme->name)->toBe('My Custom Barber Theme');
    expect($theme->slug)->toBe('my-custom-barber-theme');
    expect($theme->heading_font_fallback)->toBe('sans-serif');

    $response->assertRedirect("/dev/custom-themes/{$theme->slug}");
});

test('an invalid hex color is rejected', function () {
    $response = $this->post('/dev/custom-themes', validCustomThemePayload(['accent' => 'not-a-color']));

    $response->assertSessionHasErrors('accent');
    expect(CustomTheme::count())->toBe(0);
});

test('a font outside the curated list is rejected', function () {
    $response = $this->post('/dev/custom-themes', validCustomThemePayload(['heading_font_name' => 'Comic Sans MS']));

    $response->assertSessionHasErrors('heading_font_name');
    expect(CustomTheme::count())->toBe(0);
});

test('two themes with the same name get distinct slugs', function () {
    $this->post('/dev/custom-themes', validCustomThemePayload());
    $this->post('/dev/custom-themes', validCustomThemePayload());

    expect(CustomTheme::pluck('slug')->all())->toBe([
        'my-custom-barber-theme',
        'my-custom-barber-theme-2',
    ]);
});

test('a custom theme show page renders with the right props', function () {
    $this->post('/dev/custom-themes', validCustomThemePayload());
    $theme = CustomTheme::first();

    $response = $this->get("/dev/custom-themes/{$theme->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dev/Themes/Show')
        ->where('isCustom', true)
        ->where('theme.name', 'My Custom Barber Theme')
        ->where('theme.category', 'custom')
        ->has('customThemeData')
        ->has('availableFonts')
    );
});

test('a custom theme can be updated', function () {
    $this->post('/dev/custom-themes', validCustomThemePayload());
    $theme = CustomTheme::first();

    $response = $this->put(
        "/dev/custom-themes/{$theme->slug}",
        validCustomThemePayload(['name' => 'Renamed Theme', 'accent' => '#00FF00'])
    );

    $response->assertRedirect("/dev/custom-themes/{$theme->slug}");
    expect($theme->fresh()->name)->toBe('Renamed Theme');
    expect($theme->fresh()->accent)->toBe('#00FF00');
});

test('a custom theme can be deleted', function () {
    $this->post('/dev/custom-themes', validCustomThemePayload());
    $theme = CustomTheme::first();

    $response = $this->delete("/dev/custom-themes/{$theme->slug}");

    $response->assertRedirect('/dev/themes');
    expect(CustomTheme::count())->toBe(0);
});

test('the theme picker index lists saved custom themes', function () {
    $this->post('/dev/custom-themes', validCustomThemePayload());

    $response = $this->get('/dev/themes');

    $response->assertInertia(fn ($page) => $page
        ->component('Dev/Themes/Index')
        ->has('customThemes', 1)
        ->where('customThemes.0.slug', 'my-custom-barber-theme')
    );
});
