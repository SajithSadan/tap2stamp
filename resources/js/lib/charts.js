// Shared helpers for the dashboard charts (Components/Dashboard/Charts).

/**
 * Chart colours for the admin/owner dashboards (always the default light
 * look). Validated with the dataviz palette checker on white: both pass
 * lightness, chroma, colour-blind separation and >= 3:1 contrast. The mint
 * is a deeper step of the brand accent - the brand #17C68B itself is only
 * 2.2:1 on white, too faint for chart marks.
 */
export const SERIES = {
    primary: '#12A877', // mint - the one series in single-series charts
    secondary: '#2A78D6', // blue - second series only
};

/** One-hue mint ramp, light -> dark, for the heatmap. Index 0 = no activity. */
export const MINT_RAMP = ['#EEF2F5', '#CDEFE2', '#8FDCC0', '#3FC196', '#12A877', '#0B7A56'];

/**
 * One-hue blue ramp (same hue as SERIES.secondary) for heatmaps of rewards.
 * Index 0 = none. Steps validated as evenly spaced in lightness; the
 * lightest step deliberately recedes towards the surface (it means "almost none").
 */
export const BLUE_RAMP = ['#EEF2F5', '#CDE2FB', '#86B6EF', '#3987E5', '#1C5CAB', '#0D366B'];

export const GRID = '#E6EBF0'; // hairline gridlines
export const BASELINE = '#C9D3DC'; // axis baseline

/** Rounds up to a tidy axis top that splits into two equal gridline steps. */
export function niceMax(value) {
    if (value <= 4) return 4;

    const step = 10 ** Math.floor(Math.log10(value));
    const top = Math.ceil(value / (step / 2)) * (step / 2);

    return top % 2 === 0 ? top : top + step / 2;
}

const compact = new Intl.NumberFormat('en-GB', { notation: 'compact', maximumFractionDigits: 1 });
const full = new Intl.NumberFormat('en-GB');

/** 1,284 / 12.9K - auto-compact from 10,000 up. */
export function formatNumber(value) {
    if (value === null || value === undefined) return '–';

    return Math.abs(value) >= 10000 ? compact.format(value) : full.format(value);
}

/** Whole % change from previous to current, or null when there's no baseline to compare to. */
export function percentChange(current, previous) {
    if (!previous) return null;

    return Math.round(((current - previous) / previous) * 100);
}

/** Show every nth x-axis label so ~7 fit whatever the range (7, 30, 90 days). */
export function labelEvery(count) {
    return Math.max(1, Math.ceil(count / 7));
}
