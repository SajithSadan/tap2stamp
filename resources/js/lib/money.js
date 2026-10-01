const pounds = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' });
const wholePounds = new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 });

/** Pence → "£40" (whole pounds) or "£39.99". */
export function formatPence(pence) {
    return (pence % 100 === 0 ? wholePounds : pounds).format(pence / 100);
}

/** Pence → a short axis label: "£80", "£1.2k". */
export function formatPenceShort(pence) {
    const pounds = pence / 100;

    return pounds >= 1000 ? `£${(pounds / 1000).toFixed(pounds >= 10000 ? 0 : 1).replace(/\.0$/, '')}k` : wholePounds.format(pounds);
}

/** Pence → the plain "40.00" a price input expects. */
export function penceToInput(pence) {
    return (pence / 100).toFixed(2);
}

/** Most of one product a shop can order at once. Keep in sync with Order::MAX_QUANTITY. */
export const MAX_QUANTITY = 20;

/**
 * How `quantity` items are charged: each item costs the price for its
 * position - `price_pence` from the 1st, then each price break from its
 * `from` item on. Mirror of Product::priceBreakdown() - keep in sync (the
 * server always works out the real charge; this is for showing it).
 *
 * product: { price_pence, price_tiers: [{ from, price_pence }] | null }
 */
export function priceBreakdown(product, quantity) {
    const steps = [{ from: 1, unit_pence: product.price_pence }].concat(
        [...(product.price_tiers ?? [])].sort((a, b) => a.from - b.from).map((t) => ({ from: t.from, unit_pence: t.price_pence })),
    );

    return steps
        .map((step, i) => {
            const last = steps[i + 1] ? steps[i + 1].from - 1 : quantity;

            return { ...step, quantity: Math.max(0, Math.min(quantity, last) - step.from + 1) };
        })
        .filter((step) => step.quantity > 0);
}

export function priceFor(product, quantity) {
    return priceBreakdown(product, quantity).reduce((sum, step) => sum + step.quantity * step.unit_pence, 0);
}

const ordinal = (n) => `${n}${n % 100 >= 11 && n % 100 <= 13 ? 'th' : { 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] ?? 'th'}`;

/** "£40 each" or "£40, then £20 each from the 2nd". */
export function priceSummary(product) {
    const tiers = [...(product.price_tiers ?? [])].sort((a, b) => a.from - b.from);
    if (tiers.length === 0) return `${formatPence(product.price_pence)} each`;

    return [formatPence(product.price_pence), ...tiers.map((t) => `${formatPence(t.price_pence)} each from the ${ordinal(t.from)}`)].join(', then ');
}
