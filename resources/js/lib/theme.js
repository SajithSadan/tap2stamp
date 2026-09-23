import { useEffect } from 'react';

/**
 * A ThemeCatalog theme as the CSS variables every brand-* utility reads
 * (see @theme in resources/css/app.css). Set them on any element to theme
 * everything inside it.
 */
export function themeVars(theme) {
    return {
        '--color-brand-bg': theme.page_bg,
        '--color-brand-card': theme.card_bg,
        '--color-brand-text': theme.text,
        '--color-brand-muted': theme.muted,
        '--color-brand-accent': theme.accent,
        '--color-brand-accent-text': theme.accent_text,
        '--color-brand-border': theme.border,
        '--radius-brand': theme.radius,
        '--font-heading': theme.heading_font,
        '--font-sans': theme.body_font,
    };
}

/** Colours an owner can customise - mirrors ThemeCatalog::CUSTOM_COLORS. */
export const CUSTOM_COLOR_FIELDS = [
    ['page_bg', 'Page background'],
    ['card_bg', 'Card background'],
    ['text', 'Text'],
    ['muted', 'Muted text'],
    ['accent', 'Accent (buttons, stamps)'],
    ['accent_text', 'Text on accent'],
    ['border', 'Borders'],
];

/** "'Playfair Display', serif" -> "Playfair Display" */
export function fontNameOf(cssFontValue) {
    return cssFontValue?.match(/'([^']+)'/)?.[1] ?? null;
}

/**
 * A theme with an owner's customisation layered on top - the client-side
 * twin of ThemeCatalog::applyCustom(), used for the live preview.
 */
export function withCustomisation(base, fields, fonts) {
    const fallback = (name) => fonts.find((f) => f.name === name)?.fallback ?? 'sans-serif';
    const colors = Object.fromEntries(CUSTOM_COLOR_FIELDS.map(([key]) => [key, fields[key]]));

    return {
        ...base,
        ...colors,
        heading_font: `'${fields.heading_font_name}', ${fallback(fields.heading_font_name)}`,
        body_font: `'${fields.body_font_name}', ${fallback(fields.body_font_name)}`,
        google_fonts: [...new Set([fields.heading_font_name, fields.body_font_name])].map((f) => f.replace(/ /g, '+')).join('|'),
        radius: fields.radius,
    };
}

/** Google Fonts stylesheet URL for a theme (`google_fonts` is "Family:spec|Family:spec"). */
export function themeFontsHref(theme) {
    if (!theme?.google_fonts) return null;

    const families = theme.google_fonts.split('|').map((f) => `family=${f}`);

    return `https://fonts.googleapis.com/css2?${families.join('&')}&display=swap`;
}

/** Loads a theme's Google Fonts while mounted. */
export function useThemeFonts(theme) {
    const href = themeFontsHref(theme);

    useEffect(() => {
        if (!href || document.querySelector(`link[data-theme-fonts="${href}"]`)) return;

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.themeFonts = href;
        document.head.appendChild(link);
    }, [href]);
}

/**
 * Applies a theme to the whole document (so the page background, overscroll
 * and fixed overlays pick it up too) while the page is mounted, then puts the
 * site default back when navigating away.
 */
export function useDocumentTheme(theme) {
    useThemeFonts(theme);

    useEffect(() => {
        if (!theme) return;

        const root = document.documentElement;
        const vars = themeVars(theme);

        Object.entries(vars).forEach(([name, value]) => root.style.setProperty(name, value));

        return () => Object.keys(vars).forEach((name) => root.style.removeProperty(name));
    }, [theme]);
}
