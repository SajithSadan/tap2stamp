import { LuCherry, LuGift, LuGrip, LuLoaderPinwheel, LuPackageOpen, LuPercent, LuRotateCcw, LuStamp, LuTicket } from 'react-icons/lu';

// Icon per game type - keys must match App\Support\RewardGames::TYPES.
export const GAME_TYPE_ICONS = {
    wheel: LuLoaderPinwheel,
    scratch: LuTicket,
    boxes: LuGift,
    slots: LuCherry,
    gift: LuPackageOpen,
    plinko: LuGrip,
};

export const NO_WIN = 'no_win';

export const BONUS_STAMPS = 'bonus_stamps';

/** Share of plays that win something (0-1), from the prizes' weights. */
export function winShare(prizes) {
    const weight = (p) => Math.max(0, Number(p.weight) || 0);
    const total = prizes.reduce((sum, p) => sum + weight(p), 0);
    if (total === 0) return 0;

    return prizes.filter((p) => p.kind !== NO_WIN).reduce((sum, p) => sum + weight(p), 0) / total;
}

/** "Every play wins", "About 1 in 4 plays wins", "Nobody wins yet". */
export function oddsLabel(prizes) {
    const share = winShare(prizes);
    if (share <= 0) return 'Nobody can win yet - add a prize.';
    if (share >= 0.995) return 'Every play wins a prize.';

    return `About 1 in ${Math.max(2, Math.round(1 / share))} plays wins a prize.`;
}

/** One prize's chance as a whole percentage ("12%"). */
export function prizeChance(prizes, prize) {
    const total = prizes.reduce((sum, p) => sum + Math.max(0, Number(p.weight) || 0), 0);
    if (total === 0) return '0%';
    const pct = ((Math.max(0, Number(prize.weight) || 0) / total) * 100);

    return pct > 0 && pct < 1 ? '<1%' : `${Math.round(pct)}%`;
}

// Icon per prize kind - keys match App\Support\RewardGames::PRIZE_KINDS.
export const PRIZE_KIND_ICONS = {
    item: LuGift,
    discount: LuPercent,
    bonus_stamps: LuStamp,
    no_win: LuRotateCcw,
};

/**
 * The builder's plain chance choices; each is a weight (chance relative to
 * the other prizes). A saved weight that isn't one of these shows as "Custom".
 */
export const CHANCES = [
    { key: 'rare', label: 'Rare', weight: 5 },
    { key: 'sometimes', label: 'Sometimes', weight: 15 },
    { key: 'often', label: 'Often', weight: 40 },
];

export const chanceOf = (weight) => CHANCES.find((c) => c.weight === Number(weight))?.key ?? 'custom';

/** One-tap prizes for the builder. */
export const PRIZE_IDEAS = [
    { title: 'Free coffee', kind: 'item', weight: 5 },
    { title: 'Free pastry', kind: 'item', weight: 5 },
    { title: '10% off', kind: 'discount', weight: 15 },
    { title: '+1 stamp', kind: BONUS_STAMPS, bonus_stamps: 1, weight: 15 },
    { title: 'Try again', kind: NO_WIN, weight: 40 },
];

/** "every 3rd stamp" */
export function everyNth(n) {
    const v = Number(n) || 1;
    if (v <= 1) return 'every stamp';
    const suffix = [11, 12, 13].includes(v % 100) ? 'th' : ({ 1: 'st', 2: 'nd', 3: 'rd' }[v % 10] ?? 'th');

    return `every ${v}${suffix} stamp`;
}

/** The builder's "How it works", in plain words. */
const PERIOD_WORDS = { day: 'a day', week: 'a week', month: 'a month', total: 'in total' };
const AFTER_WIN_WORDS = {
    day: 'After winning a prize, they can play again the next day.',
    game: 'After winning a prize, they can\'t play this game again.',
};

export function howItWorks({ trigger, trigger_value, voucher_days, max_plays_per_customer, plays_period, after_win }, typeLabel, prizes) {
    const max = Number(max_plays_per_customer);
    const limit = max > 0 ? `Each customer can play up to ${max} ${max === 1 ? 'time' : 'times'} ${PERIOD_WORDS[plays_period] ?? 'a day'}.` : null;

    const when = {
        every_n_stamps: `Customers play on ${everyNth(trigger_value)} they collect.`,
        card_full: 'Customers play when their stamp card is full, on top of their reward.',
        first_stamp: 'New customers play once, on their first stamp.',
    }[trigger];

    return [
        when,
        `They play ${typeLabel.toLowerCase()} on their card page.`,
        limit,
        oddsLabel(prizes),
        AFTER_WIN_WORDS[after_win],
        `Prizes are shown to staff when they scan the card, and last ${voucher_days || 30} days.`,
    ].filter(Boolean);
}

export const STATUS_STYLES = {
    draft: { label: 'Draft', className: 'bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border' },
    live: { label: 'Live', className: 'bg-emerald-500/10 text-emerald-700' },
    paused: { label: 'Paused', className: 'bg-amber-500/10 text-amber-800' },
};
