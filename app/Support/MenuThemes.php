<?php

namespace App\Support;

/**
 * Looks for the public menu page (/menu/{shop}), picked by the admin with
 * "Choose theme" on the menu editor. Separate from the loyalty card themes:
 * each one is a whole menu design - colours, fonts AND a layout:
 *
 *  - list:    clean rows in panels, price on the right
 *  - classic: printed-menu style, centred headings, dotted leaders to the price
 *  - cards:   every item its own card, price in an accent pill
 *
 * Colour keys match ThemeCatalog, so the frontend's themeVars() applies them.
 * The layouts are drawn by resources/js/Components/MenuView.jsx.
 */
class MenuThemes
{
    public const DEFAULT = 'fresh';

    public const LAYOUTS = ['list', 'classic', 'cards'];

    /** @return array<string, array<string, string>> */
    public static function all(): array
    {
        return [
            'fresh' => [
                'name' => 'Fresh',
                'layout' => 'list',
                'google_fonts' => 'Poppins:wght@400;500;600;700',
                'heading_font' => "'Poppins', sans-serif",
                'body_font' => "'Poppins', sans-serif",
                'page_bg' => '#F5F8FA', 'card_bg' => '#FFFFFF', 'deep' => '#0F2A46',
                'text' => '#0A1A2F', 'muted' => '#5E6E7F', 'border' => '#E3EAF0',
                'accent' => '#17C68B', 'accent_text' => '#FFFFFF', 'radius' => '16px',
            ],
            'bistro' => [
                'name' => 'Bistro',
                'layout' => 'classic',
                'google_fonts' => 'Playfair+Display:wght@500;700|Lora:ital,wght@0,400;0,600;1,400',
                'heading_font' => "'Playfair Display', serif",
                'body_font' => "'Lora', serif",
                'page_bg' => '#FBF7F0', 'card_bg' => '#FFFDF8', 'deep' => '#2B2118',
                'text' => '#2B2118', 'muted' => '#7A6A58', 'border' => '#E8DFD1',
                'accent' => '#A4472E', 'accent_text' => '#FFFFFF', 'radius' => '6px',
            ],
            'chalkboard' => [
                'name' => 'Chalkboard',
                'layout' => 'classic',
                'google_fonts' => 'Patrick+Hand|Nunito:wght@400;600;700',
                'heading_font' => "'Patrick Hand', cursive",
                'body_font' => "'Nunito', sans-serif",
                'page_bg' => '#1E2421', 'card_bg' => '#262D29', 'deep' => '#141816',
                'text' => '#F1F0EA', 'muted' => '#A9B0A9', 'border' => '#39413C',
                'accent' => '#F2C14E', 'accent_text' => '#1E2421', 'radius' => '10px',
            ],
            'espresso' => [
                'name' => 'Espresso',
                'layout' => 'cards',
                'google_fonts' => 'DM+Serif+Display|Inter:wght@400;500;600',
                'heading_font' => "'DM Serif Display', serif",
                'body_font' => "'Inter', sans-serif",
                'page_bg' => '#1A1411', 'card_bg' => '#241B17', 'deep' => '#120D0B',
                'text' => '#F5EDE6', 'muted' => '#B8A79A', 'border' => '#3A2C25',
                'accent' => '#D4A373', 'accent_text' => '#1A1411', 'radius' => '14px',
            ],
            'sugar' => [
                'name' => 'Sugar',
                'layout' => 'cards',
                'google_fonts' => 'Fraunces:wght@500;700|Nunito:wght@400;600;700',
                'heading_font' => "'Fraunces', serif",
                'body_font' => "'Nunito', sans-serif",
                'page_bg' => '#FFF5F7', 'card_bg' => '#FFFFFF', 'deep' => '#5A2E40',
                'text' => '#3D2330', 'muted' => '#8C6B79', 'border' => '#F5D9E1',
                'accent' => '#D94F70', 'accent_text' => '#FFFFFF', 'radius' => '20px',
            ],
            'tavern' => [
                'name' => 'Tavern',
                'layout' => 'classic',
                'google_fonts' => 'Libre+Baskerville:wght@400;700|Source+Sans+3:wght@400;600',
                'heading_font' => "'Libre Baskerville', serif",
                'body_font' => "'Source Sans 3', sans-serif",
                'page_bg' => '#F3EFE6', 'card_bg' => '#FBF8F2', 'deep' => '#1F2A24',
                'text' => '#1F2A24', 'muted' => '#66705F', 'border' => '#DDD5C4',
                'accent' => '#1F5135', 'accent_text' => '#FFFFFF', 'radius' => '4px',
            ],
            'mono' => [
                'name' => 'Mono',
                'layout' => 'list',
                'google_fonts' => 'Inter+Tight:wght@500;700|Inter:wght@400;500;600',
                'heading_font' => "'Inter Tight', sans-serif",
                'body_font' => "'Inter', sans-serif",
                'page_bg' => '#FFFFFF', 'card_bg' => '#FFFFFF', 'deep' => '#111111',
                'text' => '#111111', 'muted' => '#6B6B6B', 'border' => '#EAEAEA',
                'accent' => '#111111', 'accent_text' => '#FFFFFF', 'radius' => '0px',
            ],
            'citrus' => [
                'name' => 'Citrus',
                'layout' => 'cards',
                'google_fonts' => 'Outfit:wght@400;500;600;700',
                'heading_font' => "'Outfit', sans-serif",
                'body_font' => "'Outfit', sans-serif",
                'page_bg' => '#FFFBEB', 'card_bg' => '#FFFFFF', 'deep' => '#7C2D12',
                'text' => '#1C1917', 'muted' => '#78716C', 'border' => '#FDE7B0',
                'accent' => '#F59E0B', 'accent_text' => '#1C1917', 'radius' => '18px',
            ],
            'midnight' => [
                'name' => 'Midnight',
                'layout' => 'list',
                'google_fonts' => 'Space+Grotesk:wght@500;700|Inter:wght@400;500',
                'heading_font' => "'Space Grotesk', sans-serif",
                'body_font' => "'Inter', sans-serif",
                'page_bg' => '#0B1220', 'card_bg' => '#111A2E', 'deep' => '#060B16',
                'text' => '#E6ECF5', 'muted' => '#93A1B8', 'border' => '#1F2A44',
                'accent' => '#5EEAD4', 'accent_text' => '#0B1220', 'radius' => '12px',
            ],
            'garden' => [
                'name' => 'Garden',
                'layout' => 'classic',
                'google_fonts' => 'Cormorant+Garamond:wght@500;700|Karla:wght@400;500;700',
                'heading_font' => "'Cormorant Garamond', serif",
                'body_font' => "'Karla', sans-serif",
                'page_bg' => '#F2F5EF', 'card_bg' => '#FFFFFF', 'deep' => '#2E3B2C',
                'text' => '#1F2B1F', 'muted' => '#667366', 'border' => '#DCE4D6',
                'accent' => '#4F7942', 'accent_text' => '#FFFFFF', 'radius' => '8px',
            ],
            'bakehouse' => [
                'name' => 'Bakehouse',
                'layout' => 'cards',
                'google_fonts' => 'Fraunces:wght@600;700|Nunito+Sans:wght@400;600;700',
                'heading_font' => "'Fraunces', serif",
                'body_font' => "'Nunito Sans', sans-serif",
                'page_bg' => '#FAF3E8', 'card_bg' => '#FFFCF6', 'deep' => '#6B3F1D',
                'text' => '#3B2412', 'muted' => '#8A6E55', 'border' => '#EEDFC8',
                'accent' => '#C9772B', 'accent_text' => '#FFFFFF', 'radius' => '22px',
            ],
            'trattoria' => [
                'name' => 'Trattoria',
                'layout' => 'classic',
                'google_fonts' => 'Abril+Fatface|Libre+Franklin:wght@400;500;600',
                'heading_font' => "'Abril Fatface', serif",
                'body_font' => "'Libre Franklin', sans-serif",
                'page_bg' => '#FFFAF2', 'card_bg' => '#FFFFFF', 'deep' => '#1E4D2B',
                'text' => '#231A14', 'muted' => '#76675A', 'border' => '#EFE3D2',
                'accent' => '#C0392B', 'accent_text' => '#FFFFFF', 'radius' => '6px',
            ],
            'spice' => [
                'name' => 'Spice',
                'layout' => 'list',
                'google_fonts' => 'Yeseva+One|Mulish:wght@400;600;700',
                'heading_font' => "'Yeseva One', serif",
                'body_font' => "'Mulish', sans-serif",
                'page_bg' => '#FFF7EE', 'card_bg' => '#FFFFFF', 'deep' => '#5B1A1A',
                'text' => '#2E1410', 'muted' => '#86675C', 'border' => '#F3DCC8',
                'accent' => '#D9480F', 'accent_text' => '#FFFFFF', 'radius' => '12px',
            ],
            'sakura' => [
                'name' => 'Sakura',
                'layout' => 'list',
                'google_fonts' => 'Shippori+Mincho:wght@500;700|Noto+Sans:wght@400;500;600',
                'heading_font' => "'Shippori Mincho', serif",
                'body_font' => "'Noto Sans', sans-serif",
                'page_bg' => '#FBF8F6', 'card_bg' => '#FFFFFF', 'deep' => '#1C1C1E',
                'text' => '#1C1C1E', 'muted' => '#76716E', 'border' => '#ECE4E0',
                'accent' => '#C8354B', 'accent_text' => '#FFFFFF', 'radius' => '4px',
            ],
            'brunch' => [
                'name' => 'Brunch',
                'layout' => 'cards',
                'google_fonts' => 'Bricolage+Grotesque:wght@500;700|DM+Sans:wght@400;500;600',
                'heading_font' => "'Bricolage Grotesque', sans-serif",
                'body_font' => "'DM Sans', sans-serif",
                'page_bg' => '#F4F7EC', 'card_bg' => '#FFFFFF', 'deep' => '#2F4A2A',
                'text' => '#1E2A1A', 'muted' => '#6A7562', 'border' => '#E1E8D3',
                'accent' => '#F08A5D', 'accent_text' => '#FFFFFF', 'radius' => '20px',
            ],
            'speakeasy' => [
                'name' => 'Speakeasy',
                'layout' => 'classic',
                'google_fonts' => 'Bodoni+Moda:wght@500;700|Josefin+Sans:wght@400;600',
                'heading_font' => "'Bodoni Moda', serif",
                'body_font' => "'Josefin Sans', sans-serif",
                'page_bg' => '#101418', 'card_bg' => '#171C22', 'deep' => '#0A0D10',
                'text' => '#ECE6D8', 'muted' => '#A39C8C', 'border' => '#2A3038',
                'accent' => '#C9A86A', 'accent_text' => '#101418', 'radius' => '2px',
            ],
            'gelato' => [
                'name' => 'Gelato',
                'layout' => 'cards',
                'google_fonts' => 'Baloo+2:wght@500;600;700',
                'heading_font' => "'Baloo 2', sans-serif",
                'body_font' => "'Baloo 2', sans-serif",
                'page_bg' => '#F0FAF8', 'card_bg' => '#FFFFFF', 'deep' => '#3B3F8F',
                'text' => '#24264F', 'muted' => '#6E7194', 'border' => '#D6EFEA',
                'accent' => '#FF6F91', 'accent_text' => '#FFFFFF', 'radius' => '24px',
            ],
            'harbour' => [
                'name' => 'Harbour',
                'layout' => 'list',
                'google_fonts' => 'Archivo:wght@400;500;600;700',
                'heading_font' => "'Archivo', sans-serif",
                'body_font' => "'Archivo', sans-serif",
                'page_bg' => '#F2F6F9', 'card_bg' => '#FFFFFF', 'deep' => '#12324A',
                'text' => '#0E2233', 'muted' => '#5C7184', 'border' => '#DCE5EC',
                'accent' => '#1F7A99', 'accent_text' => '#FFFFFF', 'radius' => '10px',
            ],
            'diner' => [
                'name' => 'Diner',
                'layout' => 'cards',
                'google_fonts' => 'Bebas+Neue|Rubik:wght@400;500;600',
                'heading_font' => "'Bebas Neue', sans-serif",
                'body_font' => "'Rubik', sans-serif",
                'page_bg' => '#FFF8EC', 'card_bg' => '#FFFFFF', 'deep' => '#B91C1C',
                'text' => '#1F1A17', 'muted' => '#7A6E66', 'border' => '#F2E2C8',
                'accent' => '#E63946', 'accent_text' => '#FFFFFF', 'radius' => '14px',
            ],
            'slate' => [
                'name' => 'Slate',
                'layout' => 'classic',
                'google_fonts' => 'Marcellus|Lato:wght@400;700',
                'heading_font' => "'Marcellus', serif",
                'body_font' => "'Lato', sans-serif",
                'page_bg' => '#26292E', 'card_bg' => '#2E3238', 'deep' => '#1A1C20',
                'text' => '#EEF0F2', 'muted' => '#A3A9B1', 'border' => '#3E434B',
                'accent' => '#E07A5F', 'accent_text' => '#FFFFFF', 'radius' => '8px',
            ],
        ];
    }

    public static function exists(?string $key): bool
    {
        return $key !== null && array_key_exists($key, self::all());
    }

    /** A menu theme with its key; unknown / null keys get the default. */
    public static function resolve(?string $key): array
    {
        $key = self::exists($key) ? $key : self::DEFAULT;

        return ['key' => $key, ...self::all()[$key]];
    }
}
