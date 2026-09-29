// Client twin of App\Support\QrStyle - keep CAPTION_BAND, blockAspect() and
// qrFraction() in sync with it (the server re-checks fit and scan size with
// the same maths). Full styles always come from the server (QrStyle::DEFAULTS
// merged in), so there's no second copy of the defaults here.
//
// Geometry, as fractions of the block width W:
//   frame (border_width) -> padding (quiet zone) -> the QR square,
//   plus an optional caption band (caption_size x CAPTION_BAND) above/below.

export const CAPTION_BAND = 1.8;

export const FONT_STACKS = {
    sans: "Poppins, 'Helvetica Neue', Arial, sans-serif",
    serif: "Georgia, 'Times New Roman', serif",
    mono: "'Courier New', ui-monospace, monospace",
};

/** Custom box height / width limits (style.box_ratio; null = auto). */
export const MIN_BOX_RATIO = 0.3;
export const MAX_BOX_RATIO = 4;

/** Caption band height as a fraction of the block width (0 with no caption). */
export function captionBand(style) {
    return style.caption_position === 'none' ? 0 : style.caption_size * CAPTION_BAND;
}

/** Block height / width when it's left on auto: the square QR plus any caption. */
export function autoAspect(style) {
    return 1 + captionBand(style);
}

/** Block height / width: the custom box_ratio, or auto. */
export function blockAspect(style) {
    return style.box_ratio ?? autoAspect(style);
}

/** Side of the scannable QR square as a fraction of the block width: the largest square that fits inside. */
export function qrFraction(style) {
    const inset = 2 * (style.border_width + style.padding);
    return Math.max(0, Math.min(1 - inset, blockAspect(style) - inset - captionBand(style)));
}

/** A code's serial number as printed: its position in its batch, at least 3 digits - "001", "No. 045". */
export function formatSerial(serial, style) {
    return `${style.serial_prefix ?? ''}${String(serial ?? 1).padStart(3, '0')}`;
}

/** Anything over the middle needs the strongest error correction (the server enforces the same). */
export function effectiveEcc(style) {
    return style.center_type === 'none' ? style.ecc : 'H';
}

function luminance(hex) {
    const [r, g, b] = [1, 3, 5].map((i) => {
        const c = parseInt(hex.slice(i, i + 2), 16) / 255;
        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** WCAG contrast ratio between two #RRGGBB colours (1-21). */
export function contrast(a, b) {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
    return (hi + 0.05) / (lo + 0.05);
}

/**
 * Plain-English problems that could stop phones reading the QR, for the
 * editor to warn about (it still lets you save - you know your print).
 */
export function scanWarnings(style) {
    const warnings = [];
    const background = style.transparent ? null : style.bg;

    if (background && luminance(style.fg) > luminance(background)) {
        warnings.push('The dots are lighter than the background. Many phone cameras can’t read an inverted QR.');
    } else if (background && contrast(style.fg, background) < 4) {
        warnings.push('Low contrast between the dots and the background. Make one darker or lighter.');
    }
    if (style.padding < 0.04) {
        warnings.push(
            style.padding === 0
                ? 'No quiet zone: fine if the artwork right around the QR is plain and light (like a white panel), but busy artwork touching it can stop it scanning.'
                : 'Thin quiet zone: fine if the artwork right around the QR is plain and light, but busy artwork next to it can stop it scanning.',
        );
    }
    if (style.transparent) {
        warnings.push('With a see-through background, the QR only scans if your image behind it is plain and light.');
    }
    if (background && contrast(style.eye_color, background) < 4) {
        warnings.push('The corner squares don’t stand out from the background.');
    }
    return warnings;
}
