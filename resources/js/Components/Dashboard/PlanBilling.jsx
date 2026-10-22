import { Link, router } from "@inertiajs/react";
import { useState } from "react";
import { LuX } from "react-icons/lu";
import CountrySelect from "@/Components/CountrySelect";
import { inputClass, Panel, primaryButton } from "@/Components/Dashboard/Ui";

/**
 * Plan billing on Admin → Landing page, beside the plans: which payment
 * window each country uses, and the latest plan payments. (Scanning is paused
 * per shop, by hand: shop settings → Account.)
 */

/** Ready / missing keys, for one payment window. */
function KeyStatus({ ready, env }) {
    return ready ? (
        <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-700">Keys set</span>
    ) : (
        <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-800" title={`Add ${env} to .env`}>
            Keys missing - WhatsApp instead
        </span>
    );
}

/**
 * Which payment window each country's owners pay in: the Stripe countries
 * (UK to start) get Stripe's card form, every other country Razorpay.
 * Adding a country here moves it to Stripe.
 */
export function PaymentsPanel({ payments }) {
    const [countries, setCountries] = useState(payments.stripe_countries);
    const [adding, setAdding] = useState("");
    const [saving, setSaving] = useState(false);
    const nameOf = (code) => payments.countries.find((c) => c.code === code)?.name ?? code;
    const changed = countries.join() !== payments.stripe_countries.join();

    function add(code) {
        if (code && !countries.includes(code)) setCountries([...countries, code]);
        setAdding("");
    }

    function save() {
        setSaving(true);
        router.put("/admin/plans/payments", { stripe_countries: countries }, { preserveScroll: true, onFinish: () => setSaving(false) });
    }

    return (
        <Panel title="Payments" description="Owners pay for a plan in their own currency. Each country uses one payment window.">
            <div className="grid gap-5 md:grid-cols-2">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-semibold text-brand-text">Stripe</span>
                        <span className="text-sm text-brand-muted">card form on our page</span>
                        <KeyStatus ready={payments.stripe_ready} env="STRIPE_SECRET and STRIPE_KEY" />
                    </div>
                    <ul className="mt-3 flex flex-wrap gap-1.5">
                        {countries.map((code) => (
                            <li key={code} className="inline-flex items-center gap-1 rounded-full bg-brand-bg py-1 pl-3 pr-1 text-sm text-brand-text ring-1 ring-inset ring-brand-border">
                                {nameOf(code)}
                                <button
                                    type="button"
                                    onClick={() => setCountries(countries.filter((c) => c !== code))}
                                    aria-label={`Move ${nameOf(code)} to Razorpay`}
                                    className="rounded-full p-1 text-brand-muted hover:bg-red-50 hover:text-red-600"
                                >
                                    <LuX className="h-3.5 w-3.5" />
                                </button>
                            </li>
                        ))}
                        {countries.length === 0 && <li className="text-sm text-brand-muted">No countries - everyone pays with Razorpay.</li>}
                    </ul>
                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        <div className="w-64 max-w-full">
                            <CountrySelect
                                id="stripe-country"
                                value={adding}
                                onChange={add}
                                countries={payments.countries.filter((c) => !countries.includes(c.code))}
                                className={inputClass}
                            />
                        </div>
                        <span className="text-xs text-brand-muted">Add a country to Stripe</span>
                    </div>
                </div>

                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm font-semibold text-brand-text">Razorpay</span>
                        <span className="text-sm text-brand-muted">Razorpay's payment window</span>
                        <KeyStatus ready={payments.razorpay_ready} env="RAZORPAY_KEY_ID and RAZORPAY_KEY_SECRET" />
                    </div>
                    <p className="mt-3 text-sm text-brand-text">Every other country.</p>
                    <p className="mt-1 text-xs text-brand-muted">
                        A plan needs a price in the owner's currency to be paid online; without one (or without keys) the owner gets a WhatsApp button instead.
                    </p>
                </div>
            </div>

            {changed && (
                <button type="button" onClick={save} disabled={saving} className={`${primaryButton} mt-5`}>
                    {saving ? "Saving…" : "Save payment countries"}
                </button>
            )}
        </Panel>
    );
}

/** The latest plan payments across every shop. */
export function RecentPayments({ payments }) {
    if (payments.length === 0) return null;

    return (
        <Panel title="Recent payments" bodyClassName="">
            <ul className="divide-y divide-brand-border">
                {payments.map((p) => (
                    <li key={p.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-sm">
                        <Link href={`/admin/shops/${p.shop_id}/settings#account`} className="min-w-40 flex-1 font-medium text-brand-text hover:underline">
                            {p.shop}
                        </Link>
                        <span className="text-brand-text">{p.plan}</span>
                        <span className="font-semibold tabular-nums text-brand-text">{p.amount}</span>
                        <span className="capitalize text-brand-muted">{p.provider}</span>
                        <span className="text-brand-muted">{p.period_label}</span>
                        <span className="text-xs text-brand-muted">paid {p.paid_label}</span>
                    </li>
                ))}
            </ul>
        </Panel>
    );
}
