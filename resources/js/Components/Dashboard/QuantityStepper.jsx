import { LuMinus, LuPlus } from 'react-icons/lu';
import { MAX_QUANTITY } from '@/lib/money';

/**
 * − [n] + for how many of a product to order. `tone="dark"` for the navy
 * order banner, default for normal panels.
 */
export default function QuantityStepper({ value, onChange, max = MAX_QUANTITY, tone = 'light', label = 'Quantity' }) {
    const clamp = (n) => Math.min(max, Math.max(1, Number.isFinite(n) ? Math.round(n) : 1));
    const step =
        tone === 'dark'
            ? 'flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 text-white transition-colors hover:bg-white/20 disabled:opacity-40'
            : 'flex h-10 w-10 items-center justify-center rounded-xl border border-brand-border bg-brand-card text-brand-text transition-colors hover:bg-brand-bg disabled:opacity-40';
    const input =
        tone === 'dark'
            ? 'h-10 w-14 rounded-xl border border-white/15 bg-white/5 text-center text-sm font-semibold tabular-nums text-white outline-none focus:border-brand-accent'
            : 'h-10 w-14 rounded-xl border border-brand-border bg-brand-card text-center text-sm font-semibold tabular-nums text-brand-text outline-none focus:border-brand-accent';

    return (
        <div className="flex items-center gap-1.5" role="group" aria-label={label}>
            <button type="button" aria-label="One fewer" onClick={() => onChange(clamp(value - 1))} disabled={value <= 1} className={step}>
                <LuMinus className="h-4 w-4" />
            </button>
            <input
                type="number"
                min={1}
                max={max}
                inputMode="numeric"
                aria-label={label}
                value={value}
                onChange={(e) => onChange(e.target.value === '' ? '' : clamp(Number(e.target.value)))}
                onBlur={() => onChange(clamp(Number(value)))}
                className={input}
            />
            <button type="button" aria-label="One more" onClick={() => onChange(clamp(value + 1))} disabled={value >= max} className={step}>
                <LuPlus className="h-4 w-4" />
            </button>
        </div>
    );
}
