<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * How a QR "block" looks on a sticker design: colours, dot/corner shapes,
 * frame, centre text/logo and caption. Stored as qr_designs.style (JSON);
 * rendered in the browser by resources/js/lib/qrRender.js.
 *
 * Keep DEFAULTS, blockAspect() and qrFraction() in sync with
 * resources/js/lib/qrStyle.js - the server uses them to check the block
 * fits on the image and that the QR itself prints big enough to scan.
 *
 * Geometry (all fractions of the block's width W):
 *   frame (border) -> padding (quiet zone) -> the QR square
 *   + an optional caption band above/below the QR.
 * The block (box) is W wide and W x blockAspect() tall: by default just
 * tall enough for the square QR (+ caption), or a custom box_ratio. The QR
 * always stays square - in a wider/taller box it's centred at the largest
 * size that fits.
 */
class QrStyle
{
    public const MODULES = ['square', 'rounded', 'dots'];

    public const EYES = ['square', 'rounded', 'circle'];

    public const ECC = ['M', 'Q', 'H'];

    public const CENTER = ['none', 'text', 'logo'];

    public const CAPTION_POSITIONS = ['none', 'below', 'above'];

    public const FONTS = ['sans', 'serif', 'mono'];

    public const DEFAULTS = [
        'fg' => '#000000',
        'bg' => '#FFFFFF',
        'transparent' => false,
        'eye_color' => '#000000',
        'modules' => 'square',
        'eyes' => 'square',
        'ecc' => 'M',
        'padding' => 0.08,
        'border_width' => 0,
        'border_color' => '#000000',
        'radius' => 0,
        'center_type' => 'none',
        'center_text' => '',
        'center_color' => '#000000',
        'center_bg' => '#FFFFFF',
        'center_size' => 0.22,
        'caption_text' => '',
        'caption_position' => 'none',
        'caption_color' => '#000000',
        'caption_size' => 0.09,
        'caption_bold' => true,
        'font' => 'sans',
        // Box height / width; null = auto (square QR + caption band).
        'box_ratio' => null,
        // Serial number (the code's position in its batch, e.g. 001), bottom centre of the artwork.
        'serial_enabled' => false,
        'serial_prefix' => '',
        'serial_color' => '#000000',
        'serial_bold' => false,
        // Text height as a fraction of the artwork width; distance from the bottom as a fraction of its height.
        'serial_size' => 0.045,
        'serial_offset' => 0.04,
    ];

    public const MIN_BOX_RATIO = 0.3;

    public const MAX_BOX_RATIO = 4;

    /** Caption band height = caption_size x this (text plus breathing room). */
    public const CAPTION_BAND = 1.8;

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'style' => ['required', 'array'],
            'style.fg' => $hex,
            'style.bg' => $hex,
            'style.transparent' => ['required', 'boolean'],
            'style.eye_color' => $hex,
            'style.modules' => ['required', Rule::in(self::MODULES)],
            'style.eyes' => ['required', Rule::in(self::EYES)],
            'style.ecc' => ['required', Rule::in(self::ECC)],
            // 0 is allowed: artwork with its own plain area round the QR already gives it a quiet zone.
            'style.padding' => ['required', 'numeric', 'between:0,0.2'],
            'style.border_width' => ['required', 'numeric', 'between:0,0.08'],
            'style.border_color' => $hex,
            'style.radius' => ['required', 'numeric', 'between:0,0.25'],
            'style.center_type' => ['required', Rule::in(self::CENTER)],
            'style.center_text' => ['nullable', 'string', 'max:12', 'required_if:style.center_type,text'],
            'style.center_color' => $hex,
            'style.center_bg' => $hex,
            'style.center_size' => ['required', 'numeric', 'between:0.1,0.3'],
            'style.caption_text' => ['nullable', 'string', 'max:40', 'required_unless:style.caption_position,none'],
            'style.caption_position' => ['required', Rule::in(self::CAPTION_POSITIONS)],
            'style.caption_color' => $hex,
            'style.caption_size' => ['required', 'numeric', 'between:0.05,0.16'],
            'style.caption_bold' => ['required', 'boolean'],
            'style.font' => ['required', Rule::in(self::FONTS)],
            'style.box_ratio' => ['nullable', 'numeric', 'between:'.self::MIN_BOX_RATIO.','.self::MAX_BOX_RATIO],
            'style.serial_enabled' => ['required', 'boolean'],
            'style.serial_prefix' => ['nullable', 'string', 'max:8'],
            'style.serial_color' => $hex,
            'style.serial_bold' => ['required', 'boolean'],
            'style.serial_size' => ['required', 'numeric', 'between:0.02,0.12'],
            'style.serial_offset' => ['required', 'numeric', 'between:0,0.5'],
        ];
    }

    /** Saved style (or null for designs made before styling) -> a full style with every key. */
    public static function normalise(?array $style): array
    {
        $style = array_intersect_key(array_merge(self::DEFAULTS, $style ?? []), self::DEFAULTS);

        foreach (['transparent', 'caption_bold', 'serial_enabled', 'serial_bold'] as $flag) {
            $style[$flag] = filter_var($style[$flag], FILTER_VALIDATE_BOOL);
        }
        foreach (['padding', 'border_width', 'radius', 'center_size', 'caption_size', 'serial_size', 'serial_offset'] as $number) {
            $style[$number] = (float) $style[$number];
        }
        $style['serial_prefix'] = (string) $style['serial_prefix'];
        foreach (['fg', 'bg', 'eye_color', 'border_color', 'center_color', 'center_bg', 'caption_color', 'serial_color'] as $colour) {
            $style[$colour] = strtoupper($style[$colour]);
        }
        $style['box_ratio'] = $style['box_ratio'] === null || $style['box_ratio'] === '' ? null : (float) $style['box_ratio'];
        $style['center_text'] = (string) $style['center_text'];
        $style['caption_text'] = (string) $style['caption_text'];

        // Something covering the middle needs the strongest error correction to still scan.
        if ($style['center_type'] !== 'none') {
            $style['ecc'] = 'H';
        }

        return $style;
    }

    /** Caption band height as a fraction of the block width (0 with no caption). */
    public static function captionBand(array $style): float
    {
        return $style['caption_position'] === 'none' ? 0 : $style['caption_size'] * self::CAPTION_BAND;
    }

    /** Block height / width: the custom box_ratio, or auto - 1 for a bare QR, taller with a caption. */
    public static function blockAspect(array $style): float
    {
        return $style['box_ratio'] ?? 1 + self::captionBand($style);
    }

    /** Side of the scannable QR square as a fraction of the block width: the largest square that fits inside. */
    public static function qrFraction(array $style): float
    {
        $inset = 2 * ($style['border_width'] + $style['padding']);

        return max(0, min(1 - $inset, self::blockAspect($style) - $inset - self::captionBand($style)));
    }
}
