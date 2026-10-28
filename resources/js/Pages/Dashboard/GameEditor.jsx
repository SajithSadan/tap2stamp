import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    LuArrowLeft,
    LuArrowRight,
    LuCheck,
    LuChevronDown,
    LuImagePlus,
    LuMinus,
    LuPartyPopper,
    LuPause,
    LuPlay,
    LuPlus,
    LuRepeat,
    LuSparkles,
    LuTrash2,
} from 'react-icons/lu';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { FieldError, inputClass, Panel, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import GamePreview from '@/Components/Games/GamePreview';
import GameStatusChip from '@/Components/Games/GameStatusChip';
import {
    BONUS_STAMPS,
    CHANCES,
    chanceOf,
    GAME_TYPE_ICONS,
    howItWorks,
    NO_WIN,
    PRIZE_IDEAS,
    PRIZE_KIND_ICONS,
    prizeChance,
    winShare,
} from '@/lib/rewardGames';

/*
 * The reward game builder, as three steps: the game (and its own look), the
 * prizes, when customers play. The preview and a plain-words "How it works"
 * sit beside every step. Save works from any step.
 */

const STEPS = [
    { key: 'game', label: 'Choose a game', fields: ['name', 'headline', 'type', 'image'] },
    { key: 'prizes', label: 'Prizes', fields: ['prizes'] },
    { key: 'when', label: 'When to play', fields: ['trigger', 'trigger_value', 'starts_on', 'ends_on', 'max_plays_per_customer', 'plays_period', 'after_win', 'voucher_days'] },
];

const prize = (values) => ({ id: null, title: '', detail: '', kind: 'item', bonus_stamps: '', weight: 15, stock: '', daily_limit: '', ...values });

// A new game starts as a small, sensible wheel the owner edits.
const STARTER = {
    name: '',
    headline: '',
    type: 'wheel',
    trigger: 'every_n_stamps',
    trigger_value: 3,
    starts_on: '',
    ends_on: '',
    max_plays_per_customer: '',
    plays_period: 'day',
    after_win: 'continue',
    voucher_days: 30,
    image: null,
    remove_image: false,
    prizes: [
        prize({ title: 'Free coffee', weight: 5 }),
        prize({ title: '+1 stamp', kind: BONUS_STAMPS, bonus_stamps: 1, weight: 15 }),
        prize({ title: 'Try again', kind: NO_WIN, weight: 40 }),
    ],
};

/** Saved game -> form values (null numbers as empty inputs). */
function toForm(game) {
    if (!game) return STARTER;
    const v = (x) => x ?? '';

    return {
        name: game.name,
        headline: v(game.headline),
        type: game.type,
        trigger: game.trigger,
        trigger_value: v(game.trigger_value) || 3,
        starts_on: v(game.starts_on),
        ends_on: v(game.ends_on),
        max_plays_per_customer: v(game.max_plays_per_customer),
        // No period with a limit = an older game: its limit was in total.
        plays_period: game.plays_period ?? (game.max_plays_per_customer ? 'total' : 'day'),
        after_win: game.after_win ?? 'continue',
        voucher_days: game.voucher_days,
        // A new image to upload / "remove the saved one" - the saved one is game.image_url.
        image: null,
        remove_image: false,
        prizes: game.prizes.map((p) => ({ ...p, detail: v(p.detail), bonus_stamps: v(p.bonus_stamps), stock: v(p.stock), daily_limit: v(p.daily_limit) })),
    };
}

const hasError = (errors, fields) => Object.keys(errors).some((key) => fields.some((f) => key === f || key.startsWith(`${f}.`)));

/* ---------- Small pieces ---------- */

function Stepper({ step, onStep, errors }) {
    return (
        <ol className="flex gap-1 rounded-2xl border border-brand-border bg-brand-card p-1">
            {STEPS.map((s, i) => {
                const active = i === step;
                const bad = hasError(errors, s.fields);

                return (
                    <li key={s.key} className="min-w-0 flex-1">
                        <button
                            type="button"
                            onClick={() => onStep(i)}
                            aria-current={active ? 'step' : undefined}
                            className={`flex w-full items-center justify-center gap-2 rounded-xl px-2 py-2.5 text-sm font-medium transition-colors ${
                                active ? 'bg-brand-accent text-brand-accent-text' : 'text-brand-muted hover:bg-brand-bg hover:text-brand-text'
                            }`}
                        >
                            <span
                                className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                    active ? 'bg-white/25' : bad ? 'bg-red-600 text-white' : 'bg-brand-bg text-brand-text ring-1 ring-brand-border'
                                }`}
                            >
                                {i + 1}
                            </span>
                            <span className={`truncate ${active ? '' : 'hidden sm:inline'}`}>{s.label}</span>
                        </button>
                    </li>
                );
            })}
        </ol>
    );
}

function Field({ label, hint, error, children }) {
    return (
        <div>
            <p className="text-sm font-medium text-brand-text">{label}</p>
            {hint && <p className="mt-0.5 text-xs text-brand-muted">{hint}</p>}
            <div className="mt-2">{children}</div>
            <FieldError message={error} />
        </div>
    );
}

/** A − / + number box. */
function Stepper1({ value, onChange, min = 1, max = 99, label }) {
    const n = Number(value) || min;
    const set = (v) => onChange(Math.min(max, Math.max(min, v)));

    return (
        <div className="inline-flex items-center rounded-xl border border-brand-border bg-brand-card">
            <button type="button" onClick={() => set(n - 1)} disabled={n <= min} aria-label={`Fewer ${label}`} className="flex h-10 w-10 items-center justify-center text-brand-text disabled:opacity-30">
                <LuMinus className="h-4 w-4" />
            </button>
            <input
                type="number"
                min={min}
                max={max}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                aria-label={label}
                className="h-10 w-12 border-x border-brand-border bg-transparent text-center text-sm font-semibold tabular-nums text-brand-text outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
            />
            <button type="button" onClick={() => set(n + 1)} disabled={n >= max} aria-label={`More ${label}`} className="flex h-10 w-10 items-center justify-center text-brand-text disabled:opacity-30">
                <LuPlus className="h-4 w-4" />
            </button>
        </div>
    );
}

/** Collapsed "More options" block. */
function More({ label = 'More options', open, onToggle, children }) {
    return (
        <div>
            <button type="button" onClick={onToggle} aria-expanded={open} className="inline-flex items-center gap-1 text-sm font-medium text-brand-accent">
                <LuChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} /> {label}
            </button>
            {open && <div className="mt-3">{children}</div>}
        </div>
    );
}

/* ---------- Step 1: the game ---------- */

function GameStep({ data, setData, errors, options, imageUrl, game }) {
    const type = options.types.find((t) => t.key === data.type) ?? options.types[0];

    return (
        <div className="space-y-6">
            <Panel title="Pick a game" description="How customers play for their prize. You can change it any time.">
                <div className="grid gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Game">
                    {options.types.map((t) => {
                        const Icon = GAME_TYPE_ICONS[t.key];
                        const active = data.type === t.key;

                        return (
                            <button
                                key={t.key}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                onClick={() => setData('type', t.key)}
                                className={`relative flex flex-col items-center gap-2 rounded-2xl border px-3 py-5 text-center transition-colors ${
                                    active ? 'border-brand-accent bg-brand-accent/5 ring-2 ring-brand-accent' : 'border-brand-border hover:border-brand-accent/60'
                                }`}
                            >
                                {active && (
                                    <span className="absolute right-2.5 top-2.5 flex h-5 w-5 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text">
                                        <LuCheck className="h-3 w-3" />
                                    </span>
                                )}
                                <span className={`flex h-14 w-14 items-center justify-center rounded-2xl ${active ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-bg text-brand-muted'}`}>
                                    <Icon className="h-7 w-7" />
                                </span>
                                <span className="font-semibold text-brand-text">{t.label}</span>
                                <span className="text-xs leading-snug text-brand-muted">{t.description}</span>
                            </button>
                        );
                    })}
                </div>
                <FieldError message={errors.type} />
            </Panel>

            <Panel title={`Customise your ${type.label.toLowerCase()}`}>
                <div className="space-y-5">
                    <Field label="Headline" hint="What customers read above the game." error={errors.headline}>
                        <input
                            type="text"
                            value={data.headline}
                            onChange={(e) => setData('headline', e.target.value)}
                            maxLength={60}
                            placeholder={type.headline}
                            className={inputClass}
                        />
                    </Field>

                    {data.type === 'scratch' && (
                        <Field label="Card image" hint="What customers scratch off. Optional - plain silver without one." error={errors.image}>
                            <div className="flex flex-wrap items-center gap-3">
                                {imageUrl && <img src={imageUrl} alt="Scratch card image" className="h-16 w-24 shrink-0 rounded-lg border border-brand-border object-cover" />}
                                <label className={`${secondaryButton} cursor-pointer`}>
                                    <LuImagePlus className="h-4 w-4" /> {imageUrl ? 'Change image' : 'Choose image'}
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        className="sr-only"
                                        onChange={(e) => {
                                            const file = e.target.files?.[0];
                                            if (file) setData((d) => ({ ...d, image: file, remove_image: false }));
                                            e.target.value = '';
                                        }}
                                    />
                                </label>
                                {imageUrl && (
                                    <button
                                        type="button"
                                        onClick={() => setData((d) => ({ ...d, image: null, remove_image: Boolean(game?.image_url) }))}
                                        className={`${secondaryButton} hover:!text-red-600`}
                                    >
                                        <LuTrash2 className="h-4 w-4" /> Remove
                                    </button>
                                )}
                            </div>
                            <p className="mt-1.5 text-xs text-brand-muted">JPG, PNG or WebP, up to 4 MB. A wide 3:2 image (e.g. 900 × 600) fits best.</p>
                        </Field>
                    )}
                    {data.type === 'wheel' && <p className="text-sm text-brand-muted">Each prize gets its own slice, in your colours. Add 4-6 prizes for a full-looking wheel.</p>}
                    {data.type === 'boxes' && <p className="text-sm text-brand-muted">Customers see three gift boxes in your colours and tap one to open it.</p>}
                    {data.type === 'slots' && <p className="text-sm text-brand-muted">Each prize gets its own symbol. Three of a symbol in a row wins that prize; a mixed row is no win.</p>}
                    {data.type === 'gift' && <p className="text-sm text-brand-muted">Customers see one present in your colours and tap it to unwrap their prize.</p>}
                    {data.type === 'plinko' && <p className="text-sm text-brand-muted">Each prize gets a slot at the bottom of the board. Up to 7 prizes fit.</p>}

                    <Field label="Game name" hint="Only you see this, to tell your games apart." error={errors.name}>
                        <input
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            maxLength={60}
                            placeholder="e.g. Weekend wheel"
                            className={inputClass}
                        />
                    </Field>
                </div>
            </Panel>
        </div>
    );
}

/* ---------- Step 2: prizes ---------- */

function PrizeCard({ prize, index, prizes, options, errors, onChange, onRemove, canRemove }) {
    const [more, setMore] = useState(Boolean(prize.detail || prize.stock || prize.daily_limit));
    const e = (field) => errors[`prizes.${index}.${field}`];
    const KindIcon = PRIZE_KIND_ICONS[prize.kind] ?? PRIZE_KIND_ICONS.item;
    const noWin = prize.kind === NO_WIN;
    const chance = chanceOf(prize.weight);

    return (
        <div className="rounded-2xl border border-brand-border bg-brand-card p-4">
            <div className="flex items-start gap-3">
                <span className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${noWin ? 'bg-brand-bg text-brand-muted' : 'bg-brand-accent/10 text-brand-accent'}`}>
                    <KindIcon className="h-5 w-5" />
                </span>
                <div className="min-w-0 flex-1">
                    <input
                        type="text"
                        value={prize.title}
                        onChange={(ev) => onChange({ title: ev.target.value })}
                        maxLength={60}
                        placeholder={noWin ? 'Try again' : 'Prize name, e.g. Free cookie'}
                        aria-label="Prize name"
                        className={`${inputClass} font-semibold`}
                    />
                    <FieldError message={e('title')} />
                </div>
                <button
                    type="button"
                    onClick={onRemove}
                    disabled={!canRemove}
                    aria-label="Remove prize"
                    title={canRemove ? 'Remove prize' : 'This game needs at least this many prizes'}
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-brand-muted hover:bg-brand-bg hover:text-red-600 disabled:opacity-30"
                >
                    <LuTrash2 className="h-4 w-4" />
                </button>
            </div>

            {/* What it is */}
            <div className="mt-3 grid grid-cols-2 gap-1.5 sm:grid-cols-4" role="radiogroup" aria-label="Kind of prize">
                {options.kinds.map((k) => {
                    const Icon = PRIZE_KIND_ICONS[k.key];
                    const active = prize.kind === k.key;

                    return (
                        <button
                            key={k.key}
                            type="button"
                            role="radio"
                            aria-checked={active}
                            onClick={() => onChange({ kind: k.key, bonus_stamps: k.key === BONUS_STAMPS ? prize.bonus_stamps || 1 : '' })}
                            className={`flex items-center justify-center gap-1.5 rounded-lg border px-2 py-2 text-xs font-medium transition-colors ${
                                active ? 'border-brand-accent bg-brand-accent/5 text-brand-text' : 'border-brand-border text-brand-muted hover:text-brand-text'
                            }`}
                        >
                            <Icon className={`h-3.5 w-3.5 ${active ? 'text-brand-accent' : ''}`} /> {k.label}
                        </button>
                    );
                })}
            </div>

            {prize.kind === BONUS_STAMPS && (
                <div className="mt-3 flex items-center gap-3 text-sm text-brand-muted">
                    <Stepper1 value={prize.bonus_stamps} onChange={(v) => onChange({ bonus_stamps: v })} max={options.limits.bonus_stamps} label="bonus stamps" />
                    {Number(prize.bonus_stamps) === 1 ? 'stamp added to their card' : 'stamps added to their card'}
                </div>
            )}
            <FieldError message={e('bonus_stamps') ?? e('kind')} />

            {/* How often */}
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <span className="text-xs font-medium text-brand-muted">How often:</span>
                {CHANCES.map((c) => (
                    <button
                        key={c.key}
                        type="button"
                        onClick={() => onChange({ weight: c.weight })}
                        aria-pressed={chance === c.key}
                        className={`rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${
                            chance === c.key ? 'border-brand-accent bg-brand-accent text-brand-accent-text' : 'border-brand-border text-brand-text hover:bg-brand-bg'
                        }`}
                    >
                        {c.label}
                    </button>
                ))}
                {chance === 'custom' && <span className="rounded-full border border-brand-accent px-3 py-1 text-xs font-semibold text-brand-text">Custom</span>}
                <span className="ml-auto text-sm font-bold tabular-nums text-brand-text">{prizeChance(prizes, prize)}</span>
            </div>
            <FieldError message={e('weight')} />

            {!noWin && (
                <div className="mt-3 border-t border-brand-border pt-3">
                    <More open={more} onToggle={() => setMore((m) => !m)}>
                        <div className="grid gap-3 sm:grid-cols-3">
                            {prize.kind !== BONUS_STAMPS && (
                                <label className="sm:col-span-3">
                                    <span className="text-xs font-medium text-brand-muted">Details customers see</span>
                                    <input
                                        type="text"
                                        value={prize.detail}
                                        onChange={(ev) => onChange({ detail: ev.target.value })}
                                        maxLength={120}
                                        placeholder="e.g. Any regular size drink"
                                        className={`${inputClass} mt-1`}
                                    />
                                </label>
                            )}
                            <label>
                                <span className="text-xs font-medium text-brand-muted">How many to give away</span>
                                <input type="number" min={1} value={prize.stock} onChange={(ev) => onChange({ stock: ev.target.value })} placeholder="No limit" className={`${inputClass} mt-1`} />
                            </label>
                            <label>
                                <span className="text-xs font-medium text-brand-muted">Most per day</span>
                                <input type="number" min={1} value={prize.daily_limit} onChange={(ev) => onChange({ daily_limit: ev.target.value })} placeholder="No limit" className={`${inputClass} mt-1`} />
                            </label>
                            <label>
                                <span className="text-xs font-medium text-brand-muted">Exact chance (weight)</span>
                                <input
                                    type="number"
                                    min={1}
                                    max={options.limits.weight}
                                    value={prize.weight}
                                    onChange={(ev) => onChange({ weight: ev.target.value })}
                                    className={`${inputClass} mt-1`}
                                />
                            </label>
                        </div>
                        <FieldError message={e('detail') ?? e('stock') ?? e('daily_limit')} />
                    </More>
                </div>
            )}
            {prize.won_count > 0 && <p className="mt-2 text-xs text-brand-muted">Won {prize.won_count} times so far.</p>}
        </div>
    );
}

/** A bar split by each prize's share - winning prizes in the accent, "try again" in grey. */
function OddsBar({ prizes }) {
    const total = prizes.reduce((s, p) => s + Math.max(0, Number(p.weight) || 0), 0) || 1;

    return (
        <div className="flex h-3 overflow-hidden rounded-full bg-brand-bg" aria-hidden="true">
            {prizes.map((p, i) => (
                <span
                    key={i}
                    className={`h-full border-r-2 border-brand-card last:border-r-0 ${p.kind === NO_WIN ? 'bg-brand-border' : 'bg-brand-accent'}`}
                    style={{ width: `${(Math.max(0, Number(p.weight) || 0) / total) * 100}%`, opacity: p.kind === NO_WIN ? 1 : 1 - (i % 3) * 0.2 }}
                />
            ))}
        </div>
    );
}

function PrizesStep({ data, setData, errors, options, type }) {
    const setPrize = (i, patch) => setData('prizes', data.prizes.map((p, j) => (j === i ? { ...p, ...patch } : p)));
    const full = data.prizes.length >= type.max_prizes;
    const share = Math.round(winShare(data.prizes) * 100);

    return (
        <div className="space-y-4">
            <Panel>
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <p className="font-heading text-lg font-semibold text-brand-text">
                        {share}% of plays win a prize
                    </p>
                    <p className="text-sm text-brand-muted">
                        {data.prizes.length} of {type.max_prizes} prizes
                    </p>
                </div>
                <div className="mt-3">
                    <OddsBar prizes={data.prizes} />
                </div>
                <p className="mt-3 text-sm text-brand-muted">
                    Set how often each prize comes up. <strong className="font-semibold text-brand-text">Try again</strong> keeps prizes special - most games have it come up often.
                </p>
                <FieldError message={errors.prizes} />
            </Panel>

            {data.prizes.map((p, i) => (
                <PrizeCard
                    key={p.id ?? `new-${i}`}
                    prize={p}
                    index={i}
                    prizes={data.prizes}
                    options={options}
                    errors={errors}
                    onChange={(patch) => setPrize(i, patch)}
                    onRemove={() => setData('prizes', data.prizes.filter((_, j) => j !== i))}
                    canRemove={data.prizes.length > type.min_prizes}
                />
            ))}

            <div className="rounded-2xl border border-dashed border-brand-border p-4">
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setData('prizes', [...data.prizes, prize()])}
                        disabled={full}
                        className={secondaryButton}
                    >
                        <LuPlus className="h-4 w-4" /> Add a prize
                    </button>
                    <span className="flex items-center gap-1 text-xs font-medium text-brand-muted">
                        <LuSparkles className="h-3.5 w-3.5" /> or add an idea:
                    </span>
                    {PRIZE_IDEAS.map((idea) => (
                        <button
                            key={idea.title}
                            type="button"
                            disabled={full}
                            onClick={() => setData('prizes', [...data.prizes, prize(idea)])}
                            className="rounded-full bg-brand-bg px-3 py-1 text-xs font-medium text-brand-text ring-1 ring-inset ring-brand-border hover:ring-brand-accent disabled:opacity-40"
                        >
                            + {idea.title}
                        </button>
                    ))}
                </div>
                {full && <p className="mt-2 text-xs text-brand-muted">That's the most prizes this game takes.</p>}
            </div>
        </div>
    );
}

/* ---------- Step 3: when ---------- */

const TRIGGER_ICONS = { every_n_stamps: LuRepeat, card_full: LuPartyPopper, first_stamp: LuSparkles };
const TRIGGER_HINTS = {
    every_n_stamps: 'Keeps regulars coming back.',
    card_full: 'A bonus on top of the card\'s reward.',
    first_stamp: 'A welcome treat for new customers.',
};

/** A row of choice pills. */
function Pills({ value, onChange, choices, label }) {
    return (
        <div className="flex flex-wrap gap-1.5" role="radiogroup" aria-label={label}>
            {choices.map((c) => (
                <button
                    key={c.key}
                    type="button"
                    role="radio"
                    aria-checked={value === c.key}
                    onClick={() => onChange(c.key)}
                    className={`rounded-full border px-3.5 py-1.5 text-sm font-medium transition-colors ${
                        value === c.key ? 'border-brand-accent bg-brand-accent text-brand-accent-text' : 'border-brand-border text-brand-text hover:bg-brand-bg'
                    }`}
                >
                    {c.label}
                </button>
            ))}
        </div>
    );
}

/** "Up to 3 a day" and what happens after a win. */
function PlayLimits({ data, setData, errors, options }) {
    const limited = data.max_plays_per_customer !== '';

    return (
        <Panel title="How often can each customer play?" description="Stops one customer playing over and over.">
            <div className="space-y-5">
                <Field label="Plays per customer" error={errors.max_plays_per_customer ?? errors.plays_period}>
                    <Pills
                        label="Plays per customer"
                        value={limited ? 'limit' : 'none'}
                        onChange={(v) => setData((d) => ({ ...d, max_plays_per_customer: v === 'limit' ? d.max_plays_per_customer || 3 : '' }))}
                        choices={[
                            { key: 'none', label: 'No limit' },
                            { key: 'limit', label: 'Set a limit' },
                        ]}
                    />
                    {limited && (
                        <div className="mt-3 flex flex-wrap items-center gap-3 text-sm text-brand-text">
                            Up to
                            <Stepper1 value={data.max_plays_per_customer} onChange={(v) => setData('max_plays_per_customer', v)} max={1000} label="plays" />
                            {Number(data.max_plays_per_customer) === 1 ? 'play' : 'plays'}
                            <select value={data.plays_period} onChange={(e) => setData('plays_period', e.target.value)} aria-label="Per" className={`${inputClass} !w-auto`}>
                                {options.periods.map((p) => (
                                    <option key={p.key} value={p.key}>
                                        {p.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
                </Field>
                <Field label="After winning a prize" hint="&quot;Try again&quot; doesn't count as a win." error={errors.after_win}>
                    <Pills label="After winning a prize" value={data.after_win} onChange={(v) => setData('after_win', v)} choices={options.after_win} />
                </Field>
            </div>
        </Panel>
    );
}

function WhenStep({ data, setData, errors, options }) {
    const [more, setMore] = useState(Boolean(data.starts_on || data.ends_on || Number(data.voucher_days) !== 30));

    return (
        <div className="space-y-6">
            <Panel title="When do customers play?">
                <div className="space-y-2" role="radiogroup" aria-label="When customers play">
                    {options.triggers.map((t) => {
                        const Icon = TRIGGER_ICONS[t.key];
                        const active = data.trigger === t.key;

                        return (
                            <div
                                key={t.key}
                                className={`rounded-2xl border transition-colors ${active ? 'border-brand-accent bg-brand-accent/5 ring-1 ring-brand-accent' : 'border-brand-border'}`}
                            >
                                <button type="button" role="radio" aria-checked={active} onClick={() => setData('trigger', t.key)} className="flex w-full items-center gap-3 p-4 text-left">
                                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${active ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-bg text-brand-muted'}`}>
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold text-brand-text">{t.label}</span>
                                        <span className="block text-sm text-brand-muted">{TRIGGER_HINTS[t.key]}</span>
                                    </span>
                                    <span className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 ${active ? 'border-brand-accent bg-brand-accent' : 'border-brand-border'}`}>
                                        {active && <span className="h-2 w-2 rounded-full bg-brand-accent-text" />}
                                    </span>
                                </button>
                                {active && t.key === 'every_n_stamps' && (
                                    <div className="flex flex-wrap items-center gap-3 px-4 pb-4 text-sm text-brand-text sm:pl-[4.25rem]">
                                        Play after every
                                        <Stepper1 value={data.trigger_value} onChange={(v) => setData('trigger_value', v)} max={options.limits.trigger_value} label="stamps" />
                                        {Number(data.trigger_value) === 1 ? 'stamp' : 'stamps'}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
                <FieldError message={errors.trigger ?? errors.trigger_value} />
            </Panel>

            <PlayLimits data={data} setData={setData} errors={errors} options={options} />

            <Panel>
                <More label="Optional: dates and how long prizes last" open={more} onToggle={() => setMore((m) => !m)}>
                    <div className="space-y-5">
                        <Field label="Run between" hint="Leave empty to run until you pause it." error={errors.starts_on ?? errors.ends_on}>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-brand-muted">
                                <input type="date" value={data.starts_on} onChange={(e) => setData('starts_on', e.target.value)} aria-label="Starts" className={`${inputClass} !w-auto`} />
                                and
                                <input type="date" value={data.ends_on} onChange={(e) => setData('ends_on', e.target.value)} aria-label="Ends" className={`${inputClass} !w-auto`} />
                            </div>
                        </Field>
                        <Field label="Prizes can be collected for" error={errors.voucher_days}>
                            <div className="flex items-center gap-3 text-sm text-brand-muted">
                                <Stepper1 value={data.voucher_days} onChange={(v) => setData('voucher_days', v)} max={365} label="days" />
                                days after winning
                            </div>
                        </Field>
                    </div>
                </More>
            </Panel>
        </div>
    );
}

/* ---------- Page ---------- */

/** Build or edit one reward game. */
export default function GameEditor({ shop, game, options }) {
    const form = useForm(toForm(game));
    const { data, errors } = form;
    const [step, setStep] = useState(0);
    const type = options.types.find((t) => t.key === data.type) ?? options.types[0];

    // After a failed save, open the first step with a problem.
    useEffect(() => {
        if (hasError(errors, STEPS[step].fields)) return;
        const bad = STEPS.findIndex((s) => hasError(errors, s.fields));
        if (bad !== -1) setStep(bad);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [errors]);

    // The scratch card image as it will be: a picked file, else the saved one (unless removed).
    const pickedUrl = useMemo(() => (data.image ? URL.createObjectURL(data.image) : null), [data.image]);
    useEffect(() => () => pickedUrl && URL.revokeObjectURL(pickedUrl), [pickedUrl]);
    const imageUrl = pickedUrl ?? (data.remove_image ? null : (game?.image_url ?? null));

    function save(e) {
        e?.preventDefault();
        // The saved game comes back with its prizes' ids: the form follows it, so the next save updates them.
        const onSuccess = (page) => {
            const saved = toForm(page.props.game);
            form.setDefaults(saved);
            form.setData(saved);
        };
        // FormData for the image: a PUT can't carry files, so it's a POST with _method.
        if (game) {
            form.transform((d) => ({ ...d, _method: 'put' }));
            form.post(`/dashboard/games/${game.id}`, { preserveScroll: true, forceFormData: true, onSuccess });
        } else {
            form.post('/dashboard/games', { forceFormData: true, onSuccess });
        }
    }

    const setStatus = (status) => router.put(`/dashboard/games/${game.id}/status`, { status }, { preserveScroll: true });
    const stepProps = { data, setData: form.setData, errors, options, type };
    const last = step === STEPS.length - 1;

    return (
        <OwnerLayout
            shop={shop}
            title={game ? game.name : 'New game'}
            description="Three quick steps: pick a game, set the prizes, choose when customers play."
            actions={
                <Link href="/dashboard/games" className={secondaryButton}>
                    <LuArrowLeft className="h-4 w-4" /> All games
                </Link>
            }
        >
            <form onSubmit={save} className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
                <div className="min-w-0 space-y-5">
                    <Stepper step={step} onStep={setStep} errors={errors} />

                    {step === 0 && <GameStep {...stepProps} imageUrl={imageUrl} game={game} />}
                    {step === 1 && <PrizesStep {...stepProps} />}
                    {step === 2 && <WhenStep {...stepProps} />}

                    <div className="flex items-center justify-between gap-3">
                        <button type="button" onClick={() => setStep((s) => s - 1)} disabled={step === 0} className={`${secondaryButton} ${step === 0 ? 'invisible' : ''}`}>
                            <LuArrowLeft className="h-4 w-4" /> Back
                        </button>
                        {last ? (
                            <button type="submit" disabled={form.processing || (game && !form.isDirty)} className={primaryButton}>
                                <LuCheck className="h-4 w-4" /> {form.processing ? 'Saving…' : game ? 'Save changes' : 'Save game'}
                            </button>
                        ) : (
                            <button type="button" onClick={() => setStep((s) => s + 1)} className={primaryButton}>
                                Next: {STEPS[step + 1].label} <LuArrowRight className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Preview + how it works + actions, beside every step on desktop. */}
                <div className="space-y-4 lg:sticky lg:top-8 lg:self-start">
                    <Panel title="Preview" description={type.label} action={game && <GameStatusChip status={game.status} />}>
                        <GamePreview type={data.type} prizes={data.prizes} image={imageUrl} headline={data.headline.trim() || type.headline} />
                    </Panel>

                    <Panel title="How it works" bodyClassName="px-5 py-4">
                        <ul className="space-y-2">
                            {howItWorks(data, type.label, data.prizes).map((line) => (
                                <li key={line} className="flex gap-2 text-sm text-brand-text">
                                    <LuCheck className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" />
                                    {line}
                                </li>
                            ))}
                        </ul>
                    </Panel>

                    <div className="space-y-2">
                        <button type="button" onClick={save} disabled={form.processing || (game && !form.isDirty)} className={`${primaryButton} w-full`}>
                            {form.processing ? 'Saving…' : game ? (form.isDirty ? 'Save changes' : 'All changes saved') : 'Save as draft'}
                        </button>
                        {game &&
                            (game.status === 'live' ? (
                                <button type="button" onClick={() => setStatus('paused')} className={`${secondaryButton} w-full`}>
                                    <LuPause className="h-4 w-4" /> Pause game
                                </button>
                            ) : (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => setStatus('live')}
                                        disabled={form.isDirty}
                                        className={`${secondaryButton} w-full`}
                                    >
                                        <LuPlay className="h-4 w-4" /> Publish to customers
                                    </button>
                                    {form.isDirty && <p className="text-center text-xs text-brand-muted">Save your changes before publishing.</p>}
                                </>
                            ))}
                        {!game && <p className="text-center text-xs text-brand-muted">Saved games stay as drafts until you publish them.</p>}
                    </div>
                </div>
            </form>
        </OwnerLayout>
    );
}
