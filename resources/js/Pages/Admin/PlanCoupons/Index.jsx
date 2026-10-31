import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import { LuPencil, LuPlus, LuShuffle, LuTicket, LuX } from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import { CopyButton, EmptyState, FieldError, inputClass, Panel, primaryButton, secondaryButton, Switch } from "@/Components/Dashboard/Ui";

/**
 * Admin → Landing & plans → Plan coupons: discount codes owners can use when
 * paying for a plan (separate from the counter display's coupons). Each
 * coupon lists the countries that can use it, each with its own discount -
 * a percentage, or an amount in that country's currency.
 */

const BLANK = {
    code: "",
    description: "",
    discount_type: "percent",
    discounts: [{ country: "GB", value: "" }],
    plan_id: "",
    max_uses: "",
    once_per_shop: true,
    expires_on: "",
    is_active: true,
};

// No 0/O/1/I, like the QR sticker codes - easy to read out on the phone.
const ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
const randomCode = () => Array.from(crypto.getRandomValues(new Uint32Array(8)), (n) => ALPHABET[n % ALPHABET.length]).join("");

function Field({ label, error, hint, children }) {
    return (
        <label className="block min-w-0">
            <span className="mb-1.5 block text-sm font-medium text-brand-text">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-brand-muted">{hint}</span>}
            <FieldError message={error} />
        </label>
    );
}

/** The countries that can use the coupon, each with its discount. */
function CountryDiscounts({ form, countries }) {
    const percent = form.data.discount_type === "percent";
    const byCode = Object.fromEntries(countries.map((c) => [c.code, c]));
    const rows = form.data.discounts;
    const used = new Set(rows.map((r) => r.country));

    const update = (index, changes) => form.setData("discounts", rows.map((row, i) => (i === index ? { ...row, ...changes } : row)));
    const remove = (index) => form.setData("discounts", rows.filter((_, i) => i !== index));
    const add = () => {
        const next = ["GB", "IN"].find((code) => !used.has(code)) ?? countries.find((c) => !used.has(c.code))?.code ?? "";
        form.setData("discounts", [...rows, { country: next, value: "" }]);
    };

    return (
        <div>
            <span className="mb-1.5 block text-sm font-medium text-brand-text">Countries & discount</span>
            <ul className="space-y-2">
                {rows.map((row, index) => (
                    <li key={index}>
                        <div className="flex gap-2">
                            <select
                                aria-label="Country"
                                value={row.country}
                                onChange={(e) => update(index, { country: e.target.value })}
                                className={`${inputClass} min-w-0 flex-1`}
                            >
                                {countries.map((c) => (
                                    <option key={c.code} value={c.code} disabled={c.code !== row.country && used.has(c.code)}>
                                        {c.name} ({c.currency})
                                    </option>
                                ))}
                            </select>
                            <div className="flex w-36 shrink-0 items-center rounded-xl border border-brand-border bg-brand-card focus-within:border-brand-accent">
                                {!percent && <span className="pl-3 text-sm text-brand-muted">{byCode[row.country]?.symbol}</span>}
                                <input
                                    type="text"
                                    inputMode={percent ? "numeric" : "decimal"}
                                    aria-label={percent ? "Percentage off" : "Amount off"}
                                    placeholder={percent ? "10" : "500"}
                                    value={row.value}
                                    onChange={(e) => update(index, { value: e.target.value })}
                                    className="h-10 min-w-0 flex-1 bg-transparent px-2 text-sm text-brand-text outline-none"
                                />
                                {percent && <span className="pr-3 text-sm text-brand-muted">%</span>}
                            </div>
                            <button
                                type="button"
                                onClick={() => remove(index)}
                                disabled={rows.length === 1}
                                aria-label="Remove country"
                                className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg hover:text-brand-text disabled:opacity-30"
                            >
                                <LuX className="h-4 w-4" />
                            </button>
                        </div>
                        <FieldError message={form.errors[`discounts.${index}.country`] ?? form.errors[`discounts.${index}.value`]} />
                    </li>
                ))}
            </ul>
            <FieldError message={form.errors.discounts} />
            <button type="button" onClick={add} className="mt-2 text-sm font-medium text-brand-accent underline-offset-2 hover:underline">
                + Add country
            </button>
            <span className="mt-1 block text-xs text-brand-muted">
                {percent ? "Percentage off the plan's price." : "Amount off, in the currency that country's shops pay in."} Countries not listed can't use the code.
            </span>
        </div>
    );
}

/** Add (coupon = null) or edit one plan coupon. */
function CouponForm({ coupon, plans, countries, onDone }) {
    const form = useForm(
        coupon
            ? {
                  code: coupon.code,
                  description: coupon.description ?? "",
                  discount_type: coupon.discount_type,
                  discounts: coupon.discounts.map(({ country, value }) => ({ country, value })),
                  plan_id: coupon.plan_id ?? "",
                  max_uses: coupon.max_uses ?? "",
                  once_per_shop: coupon.once_per_shop,
                  expires_on: coupon.expires_on ?? "",
                  is_active: coupon.is_active,
              }
            : BLANK,
    );

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };

        if (coupon) form.put(`/admin/plans/coupons/${coupon.id}`, options);
        else form.post("/admin/plans/coupons", options);
    }

    return (
        <Panel className="mb-4" title={coupon ? `Edit ${coupon.code}` : "Add a plan coupon"} description="Owners type the code before paying for a plan. Only paid payments count as a use.">
            <form onSubmit={submit} noValidate className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Code" error={form.errors.code} hint="Letters, numbers, - and _. Not case-sensitive.">
                        <div className="flex gap-2">
                            <input
                                type="text"
                                autoCapitalize="characters"
                                spellCheck={false}
                                maxLength={32}
                                placeholder="LAUNCH20"
                                value={form.data.code}
                                onChange={(e) => form.setData("code", e.target.value.toUpperCase())}
                                className={`${inputClass} uppercase`}
                            />
                            <button type="button" onClick={() => form.setData("code", randomCode())} className={secondaryButton} title="Make a random code">
                                <LuShuffle className="h-4 w-4" />
                                <span className="sr-only">Make a random code</span>
                            </button>
                        </div>
                    </Field>
                    <Field label="Discount type" error={form.errors.discount_type}>
                        <div className="flex w-fit rounded-xl border border-brand-border p-0.5" role="group" aria-label="Discount type">
                            {[
                                ["percent", "Percentage"],
                                ["fixed", "Fixed amount"],
                            ].map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    aria-pressed={form.data.discount_type === value}
                                    onClick={() => form.setData("discount_type", value)}
                                    className={`h-9 rounded-[10px] px-3 text-sm font-semibold transition-colors ${
                                        form.data.discount_type === value ? "bg-brand-accent text-brand-accent-text" : "text-brand-muted hover:text-brand-text"
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </Field>
                </div>

                <CountryDiscounts form={form} countries={countries} />

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Plan" error={form.errors.plan_id}>
                        <select value={form.data.plan_id} onChange={(e) => form.setData("plan_id", e.target.value)} className={inputClass}>
                            <option value="">Any plan</option>
                            {plans.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Valid until" error={form.errors.expires_on} hint="Works up to the end of this day (UK time). Empty = no end date.">
                        <input type="date" value={form.data.expires_on} onChange={(e) => form.setData("expires_on", e.target.value)} className={inputClass} />
                    </Field>
                    <Field label="Total uses" error={form.errors.max_uses} hint="Across all shops. Empty = unlimited.">
                        <input
                            type="number"
                            min={1}
                            inputMode="numeric"
                            placeholder="Unlimited"
                            value={form.data.max_uses}
                            onChange={(e) => form.setData("max_uses", e.target.value)}
                            className={inputClass}
                        />
                    </Field>
                    <Field label="Note" error={form.errors.description} hint="For you only, e.g. “Launch offer, Nov 2026”.">
                        <input type="text" maxLength={255} value={form.data.description} onChange={(e) => form.setData("description", e.target.value)} className={inputClass} />
                    </Field>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5">
                        <Switch checked={form.data.once_per_shop} onChange={(value) => form.setData("once_per_shop", value)} label="Once per shop" description="Each shop can use it on one paid plan payment." />
                    </div>
                    <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5">
                        <Switch checked={form.data.is_active} onChange={(value) => form.setData("is_active", value)} label="Active" description="Off: the code stops working straight away." />
                    </div>
                </div>
                <div className="flex flex-wrap gap-3">
                    <button type="submit" disabled={form.processing} className={primaryButton}>
                        {form.processing ? "Saving…" : coupon ? "Save coupon" : "Add coupon"}
                    </button>
                    <button type="button" onClick={onDone} className={secondaryButton}>
                        Cancel
                    </button>
                </div>
            </form>
        </Panel>
    );
}

function StatusBadge({ coupon }) {
    const [label, className] = !coupon.is_active
        ? ["Off", "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border"]
        : coupon.is_expired
          ? ["Expired", "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border"]
          : coupon.max_uses !== null && coupon.uses >= coupon.max_uses
            ? ["Used up", "bg-orange-500/10 text-orange-700"]
            : ["Active", "bg-emerald-500/10 text-emerald-700"];

    return <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${className}`}>{label}</span>;
}

export default function Index({ coupons, plans, countries }) {
    // null = no form open, "new" = adding, else the coupon being edited.
    const [editing, setEditing] = useState(null);
    const countryName = Object.fromEntries(countries.map((c) => [c.code, c.name]));

    return (
        <AdminLayout
            title="Plan coupons"
            description="Discount codes owners can use when they pay for a plan. Switch one off instead of deleting it - past payments keep the code."
            actions={
                <div className="flex flex-wrap gap-2">
                    <Link href="/admin/landing-page" className={secondaryButton}>
                        Back to plans
                    </Link>
                    <button type="button" onClick={() => setEditing("new")} className={primaryButton}>
                        <LuPlus className="h-4 w-4" /> Add coupon
                    </button>
                </div>
            }
        >
            {editing && (
                <CouponForm
                    key={editing === "new" ? "new" : editing.id}
                    coupon={editing === "new" ? null : editing}
                    plans={plans}
                    countries={countries}
                    onDone={() => setEditing(null)}
                />
            )}

            <Panel bodyClassName="">
                {coupons.length === 0 ? (
                    <EmptyState icon={LuTicket} title="No plan coupons yet">
                        Add a code for a promotion, e.g. 20% off in the UK and ₹1,000 off in India.
                    </EmptyState>
                ) : (
                    <ul className="divide-y divide-brand-border">
                        {coupons.map((coupon) => (
                            <li key={coupon.id} className="flex flex-wrap items-start gap-4 px-5 py-4">
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-semibold text-brand-text">
                                        <span className="flex items-center gap-0.5">
                                            <span className="font-mono tracking-wide">{coupon.code}</span>
                                            <CopyButton text={coupon.code} label={`Copy ${coupon.code}`} />
                                        </span>
                                        <StatusBadge coupon={coupon} />
                                    </p>
                                    {coupon.description && <p className="mt-1 max-w-2xl text-sm text-brand-muted">{coupon.description}</p>}
                                    <p className="mt-1.5 flex flex-wrap gap-1.5">
                                        {coupon.discounts.map((d) => (
                                            <span key={d.country} className="rounded-full bg-brand-bg px-2 py-0.5 text-xs text-brand-text ring-1 ring-inset ring-brand-border">
                                                {countryName[d.country] ?? d.country}: <strong>{d.label}</strong>
                                            </span>
                                        ))}
                                    </p>
                                    <p className="mt-1.5 text-xs text-brand-muted">
                                        {[
                                            coupon.plan_name ?? "Any plan",
                                            coupon.once_per_shop ? "Once per shop" : "Repeat use",
                                            coupon.expires_label ? `${coupon.is_expired ? "Ended" : "Until"} ${coupon.expires_label}` : "No end date",
                                        ].join(" · ")}
                                    </p>
                                </div>
                                <p className="text-right text-xs text-brand-muted">
                                    {coupon.uses}
                                    {coupon.max_uses !== null && ` / ${coupon.max_uses}`} {coupon.uses === 1 ? "use" : "uses"}
                                </p>
                                <button type="button" onClick={() => setEditing(coupon)} className={secondaryButton}>
                                    <LuPencil className="h-4 w-4" /> Edit
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </AdminLayout>
    );
}
