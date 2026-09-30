// The card page header's text colour, banner tint and title shadow
// (App\Support\HeaderStyle - keep the defaults in sync).

export const HEADER_STYLE_DEFAULTS = { text_color: '#FFFFFF', tint: 30, shadow: false };

/** Inline style for the shop name / reward over the header. */
export function headerTextStyle(style = HEADER_STYLE_DEFAULTS) {
    return {
        color: style.text_color,
        textShadow: style.shadow ? '0 1px 2px rgb(0 0 0 / 0.55), 0 2px 12px rgb(0 0 0 / 0.45)' : undefined,
    };
}

/** Inline style for the solid black layer over the banner photo. */
export function headerTintStyle(style = HEADER_STYLE_DEFAULTS) {
    return { opacity: style.tint / 100 };
}
