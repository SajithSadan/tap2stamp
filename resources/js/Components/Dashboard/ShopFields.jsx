import { LuCheck, LuGift, LuMinus, LuPlus } from 'react-icons/lu';

// Shop setup pieces shared by the admin "Add shop" form and the owner's
// own shop setup after sign-up (Onboarding/Shop).

export const MIN_STAMPS = 3;
export const MAX_STAMPS = 20;

const clamp = (n) => Math.min(MAX_STAMPS, Math.max(MIN_STAMPS, n));

export function slugify(value) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
}

// The card sizes most shops pick, offered as one-tap choices beside the stepper.
const STAMP_PRESETS = [6, 8, 10, 12];

/** One joined − / number / + control, plus one-tap common sizes. */
export function StampStepper({ id, value, onChange }) {
    const stepClass =
        'flex w-10 items-center justify-center text-brand-text transition-colors hover:bg-brand-bg disabled:pointer-events-none disabled:opacity-30';

    return (
        <div className="flex flex-wrap items-center gap-3">
            <div className="flex h-[42px] items-stretch overflow-hidden rounded-xl border border-brand-border bg-brand-card focus-within:border-brand-accent focus-within:ring-4 focus-within:ring-brand-accent/10">
                <button type="button" aria-label="Fewer stamps" onClick={() => onChange(clamp(value - 1))} disabled={value <= MIN_STAMPS} className={stepClass}>
                    <LuMinus className="h-4 w-4" />
                </button>
                <input
                    id={id}
                    type="number"
                    min={MIN_STAMPS}
                    max={MAX_STAMPS}
                    value={value}
                    onChange={(e) => onChange(Number(e.target.value))}
                    onBlur={() => onChange(clamp(value || MIN_STAMPS))}
                    className="w-12 border-x border-brand-border bg-transparent text-center text-sm font-semibold tabular-nums text-brand-text outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                />
                <button type="button" aria-label="More stamps" onClick={() => onChange(clamp(value + 1))} disabled={value >= MAX_STAMPS} className={stepClass}>
                    <LuPlus className="h-4 w-4" />
                </button>
            </div>

            <div className="flex gap-1.5" role="group" aria-label="Common card sizes">
                {STAMP_PRESETS.map((n) => (
                    <button
                        key={n}
                        type="button"
                        onClick={() => onChange(n)}
                        aria-pressed={value === n}
                        className={`h-8 min-w-8 rounded-full px-2.5 text-xs font-semibold tabular-nums transition-colors ${
                            value === n ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-bg text-brand-text hover:bg-brand-border'
                        }`}
                    >
                        {n}
                    </button>
                ))}
            </div>
        </div>
    );
}

/** "/s/" prefix + slug input, one bordered control. */
export function SlugInput({ id, value, onChange, placeholder = 'your-shop' }) {
    return (
        <div className="flex min-w-0 items-stretch overflow-hidden rounded-xl border border-brand-border bg-brand-bg focus-within:border-brand-accent focus-within:ring-4 focus-within:ring-brand-accent/10">
            <span className="flex items-center border-r border-brand-border px-3 font-mono text-sm text-brand-muted">/s/</span>
            <input
                id={id}
                value={value}
                onChange={(e) => onChange(slugify(e.target.value))}
                placeholder={placeholder}
                className="w-full min-w-0 bg-brand-card px-3 py-2.5 font-mono text-sm text-brand-text outline-none"
            />
        </div>
    );
}

/** Rough sketch of the customer's card in the default look - just enough to sanity-check the setup. */
export function CardPreview({ name, slug, maxStamps, reward, logoUrl = null }) {
    const stamps = clamp(maxStamps || MIN_STAMPS);
    const filled = Math.min(2, stamps);

    return (
        <div className="overflow-hidden rounded-2xl border border-brand-border bg-brand-card">
            <div className="flex items-center gap-3 bg-gradient-to-br from-brand-deep to-brand-deep/85 px-5 py-6 text-white">
                {/* The logo, as square as on the real card page. */}
                {logoUrl && <img src={logoUrl} alt="" className="h-12 w-12 shrink-0 rounded-xl bg-white object-cover" />}
                <div className="min-w-0">
                    <p className="text-xs uppercase tracking-widest text-white/60">Loyalty card</p>
                    <p className="mt-1 truncate font-heading text-xl font-semibold">{name || 'Your shop name'}</p>
                </div>
            </div>
            <div className="p-5">
                {/* 4 per row; a part-filled last row is centred rather than hanging left. */}
                <div className="flex flex-wrap justify-center gap-2.5">
                    {Array.from({ length: stamps }, (_, i) => (
                        <span
                            key={i}
                            className={`flex aspect-square w-[calc((100%-1.875rem)/4)] items-center justify-center rounded-full border-2 ${
                                i < filled ? 'border-brand-accent bg-brand-accent text-brand-accent-text' : 'border-dashed border-brand-border'
                            }`}
                        >
                            {i < filled && <LuCheck className="h-4 w-4" />}
                        </span>
                    ))}
                </div>
                <div className="mt-4 flex items-start gap-2 rounded-xl bg-brand-bg px-3 py-2.5">
                    <LuGift className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" />
                    <p className="text-sm text-brand-text">{reward || 'Your reward'}</p>
                </div>
                {/* Admin "Add shop" shows the link; owner sign-up doesn't (slug undefined). */}
                {slug !== undefined && (
                    <p className="mt-3 truncate text-center font-mono text-xs text-brand-muted">
                        {window.location.host}/s/{slug || 'your-shop'}
                    </p>
                )}
            </div>
        </div>
    );
}
