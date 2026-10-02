import { useState } from 'react';
import { LuTicket, LuX } from 'react-icons/lu';

/**
 * "Have a coupon code?" on the order forms. Checks the code with the server
 * (POST /dashboard/orders/coupon) and hands the coupon to `onChange` so the
 * form can show the new price; the checkout checks it again for real.
 * `tone="dark"` for the navy order banner, like QuantityStepper.
 */
export default function CouponField({ productId, quantity, coupon, onChange, tone = 'light' }) {
    const [open, setOpen] = useState(false);
    const [code, setCode] = useState('');
    const [checking, setChecking] = useState(false);
    const [error, setError] = useState(null);
    const dark = tone === 'dark';

    async function apply(event) {
        event.preventDefault();
        if (!code.trim()) return;

        setChecking(true);
        setError(null);
        try {
            const { data } = await window.axios.post('/dashboard/orders/coupon', { code: code.trim(), product_id: productId, quantity });
            onChange(data.coupon);
            setCode('');
        } catch (e) {
            const status = e.response?.status;
            setError(
                status === 422
                    ? (e.response.data.errors?.code?.[0] ?? e.response.data.message)
                    : status === 429
                      ? 'Too many tries - please wait a minute.'
                      : "We couldn't check the code. Please try again.",
            );
        } finally {
            setChecking(false);
        }
    }

    if (coupon) {
        return (
            <p className={`flex items-center gap-2 text-sm ${dark ? 'text-white' : 'text-brand-text'}`}>
                <LuTicket className={`h-4 w-4 shrink-0 ${dark ? 'text-brand-accent' : 'text-emerald-700'}`} />
                <span>
                    <span className="font-semibold">{coupon.code}</span> · {coupon.label}
                </span>
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    aria-label="Remove coupon"
                    className={`rounded-lg p-1 transition-colors ${dark ? 'text-white/60 hover:bg-white/10 hover:text-white' : 'text-brand-muted hover:bg-brand-bg hover:text-brand-text'}`}
                >
                    <LuX className="h-4 w-4" />
                </button>
            </p>
        );
    }

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className={`text-sm font-medium underline-offset-2 hover:underline ${dark ? 'text-white/70 hover:text-white' : 'text-brand-accent'}`}
            >
                Have a coupon code?
            </button>
        );
    }

    return (
        <form onSubmit={apply} noValidate>
            <div className="flex items-center gap-2">
                <input
                    type="text"
                    autoFocus
                    autoCapitalize="characters"
                    autoComplete="off"
                    spellCheck={false}
                    maxLength={32}
                    aria-label="Coupon code"
                    placeholder="Coupon code"
                    value={code}
                    onChange={(e) => setCode(e.target.value.toUpperCase())}
                    className={
                        dark
                            ? 'h-10 w-40 min-w-0 rounded-xl border border-white/15 bg-white/5 px-3 text-sm uppercase text-white outline-none placeholder:normal-case placeholder:text-white/40 focus:border-brand-accent'
                            : 'h-10 w-40 min-w-0 rounded-xl border border-brand-border bg-brand-card px-3 text-sm uppercase text-brand-text outline-none placeholder:normal-case placeholder:text-brand-muted focus:border-brand-accent'
                    }
                />
                <button
                    type="submit"
                    disabled={checking || !code.trim()}
                    className={
                        dark
                            ? 'h-10 rounded-xl bg-white/10 px-3.5 text-sm font-semibold text-white transition-colors hover:bg-white/20 disabled:opacity-50'
                            : 'h-10 rounded-xl border border-brand-border bg-brand-card px-3.5 text-sm font-medium text-brand-text transition-colors hover:bg-brand-bg disabled:opacity-50'
                    }
                >
                    {checking ? 'Checking…' : 'Apply'}
                </button>
            </div>
            {error && (
                <p role="alert" className={`mt-1.5 text-xs ${dark ? 'text-red-200' : 'text-red-700'}`}>
                    {error}
                </p>
            )}
        </form>
    );
}
