<?php

namespace App\Support;

/**
 * A fixed list of Google Fonts known to work (verified either by direct use
 * across ThemePreviewController's 50 built-in themes, or as long-standing
 * standard Google Fonts). The theme customizer only lets people pick from
 * this list — never freeform text — so it can never request a font that
 * doesn't exist or fails to load.
 */
class CuratedFonts
{
    /**
     * @return array<int, array{name: string, fallback: string}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'Inter', 'fallback' => 'sans-serif'],
            ['name' => 'Poppins', 'fallback' => 'sans-serif'],
            ['name' => 'Playfair Display', 'fallback' => 'serif'],
            ['name' => 'Oswald', 'fallback' => 'sans-serif'],
            ['name' => 'Roboto', 'fallback' => 'sans-serif'],
            ['name' => 'Open Sans', 'fallback' => 'sans-serif'],
            ['name' => 'Montserrat', 'fallback' => 'sans-serif'],
            ['name' => 'Raleway', 'fallback' => 'sans-serif'],
            ['name' => 'Lora', 'fallback' => 'serif'],
            ['name' => 'Merriweather', 'fallback' => 'serif'],
            ['name' => 'Libre Baskerville', 'fallback' => 'serif'],
            ['name' => 'Nunito', 'fallback' => 'sans-serif'],
            ['name' => 'Work Sans', 'fallback' => 'sans-serif'],
            ['name' => 'Bebas Neue', 'fallback' => 'sans-serif'],
            ['name' => 'Anton', 'fallback' => 'sans-serif'],
            ['name' => 'Cormorant', 'fallback' => 'serif'],
            ['name' => 'Fraunces', 'fallback' => 'serif'],
            ['name' => 'Space Grotesk', 'fallback' => 'sans-serif'],
            ['name' => 'Manrope', 'fallback' => 'sans-serif'],
            ['name' => 'Josefin Sans', 'fallback' => 'sans-serif'],
            ['name' => 'Caveat', 'fallback' => 'cursive'],
            ['name' => 'Bitter', 'fallback' => 'serif'],
            ['name' => 'DM Serif Display', 'fallback' => 'serif'],
            ['name' => 'Source Sans 3', 'fallback' => 'sans-serif'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_column(self::all(), 'name');
    }

    public static function fallbackFor(string $name): ?string
    {
        foreach (self::all() as $font) {
            if ($font['name'] === $name) {
                return $font['fallback'];
            }
        }

        return null;
    }
}
