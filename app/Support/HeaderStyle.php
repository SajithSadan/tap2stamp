<?php

namespace App\Support;

/**
 * How the shop name and reward read over the card page header (Theme → Banner & logo):
 * their colour, how dark the tint over the banner photo is, and an optional text shadow.
 * Stored in shops.header_style; null = these defaults.
 */
final class HeaderStyle
{
    public const DEFAULTS = [
        'text_color' => '#FFFFFF',
        'tint' => 30,
        'shadow' => false,
    ];

    public const MAX_TINT = 80;

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tint' => ['required', 'integer', 'between:0,'.self::MAX_TINT],
            'shadow' => ['required', 'boolean'],
        ];
    }

    /**
     * The stored style with defaults filled in, unknown keys dropped.
     *
     * @param  array<string, mixed>|null  $style
     * @return array{text_color: string, tint: int, shadow: bool}
     */
    public static function resolve(?array $style): array
    {
        $style = array_intersect_key($style ?? [], self::DEFAULTS) + self::DEFAULTS;

        return [
            'text_color' => strtoupper((string) $style['text_color']),
            'tint' => (int) $style['tint'],
            'shadow' => (bool) $style['shadow'],
        ];
    }
}
