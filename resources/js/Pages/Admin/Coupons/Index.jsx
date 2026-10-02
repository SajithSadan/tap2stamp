import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import { LuPencil, LuPlus, LuShuffle, LuTicket } from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import {
    EmptyState,
    FieldError,
    inputClass,
    Panel,
    primaryButton,
    secondaryButton,
    Switch,
} from "@/Components/Dashboard/Ui";
import { penceToInput } from "@/lib/money";

const BLANK = {
    code: "",
    description: "",
    discount_type: "percent",
    discount_value: "",
    product_id: "",
    max_uses: "",
    once_per_shop: true,
    expires_on: "",
    is_active: true,
};

// No 0/O/1/I, like the QR sticker codes - easy to read out on the phone.
const ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
const randomCode = () =>
    Array.from(
        crypto.getRandomValues(new Uint32Array(8)),
        (n) => ALPHABET[n % ALPHABET.length],
    ).join("");

function Field({ label, error, hint, children }) {
    return (
        <label className="block min-w-0">
            <span className="mb-1.5 block text-sm font-medium text-brand-text">
                {label}
            </span>
            {children}
            {hint && !error && (
                <span className="mt-1 block text-xs text-brand-muted">
                    {hint}
                </span>
            )}
            <FieldError message={error} />
        </label>
    );
}

/** Add (coupon = null) or edit one coupon. */
function CouponForm({ coupon, products, onDone }) {
    const form = useForm(
        coupon
            ? {
                  code: coupon.code,
                  description: coupon.description ?? "",
                  discount_type: coupon.discount_type,
                  discount_value:
                      coupon.discount_type === "percent"
                          ? String(coupon.discount_value)
                          : penceToInput(coupon.discount_value),
                  product_id: coupon.product_id ?? "",
                  max_uses: coupon.max_uses ?? "",
                  once_per_shop: coupon.once_per_shop,
                  expires_on: coupon.expires_on ?? "",
                  is_active: coupon.is_active,
              }
            : BLANK,
    );
    const percent = form.data.discount_type === "percent";

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };

        if (coupon) form.put(`/admin/coupons/${coupon.id}`, options);
        else form.post("/admin/coupons", options);
    }

    return (
        <Panel
            className="mb-4"
            title={coupon ? `Edit ${coupon.code}` : "Add a coupon"}
            description="Owners type the code when they order. Only paid orders count as a use."
        >
            <form onSubmit={submit} noValidate className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Letters, numbers, - and _. Not case-sensitive."
                    >
                        <div className="flex gap-2">
                            <input
                                type="text"
                                autoCapitalize="characters"
                                spellCheck={false}
                                maxLength={32}
                                placeholder="WELCOME10"
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData(
                                        "code",
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                className={`${inputClass} uppercase`}
                            />
                            <button
                                type="button"
                                onClick={() => form.setData("code", randomCode())}
                                className={secondaryButton}
                                title="Make a random code"
                            >
                                <LuShuffle className="h-4 w-4" />
                                <span className="sr-only">Make a random code</span>
                            </button>
                        </div>
                    </Field>
                    <Field label="Discount" error={form.errors.discount_value ?? form.errors.discount_type}>
                        <div className="flex gap-2">
                            <div
                                className="flex shrink-0 rounded-xl border border-brand-border p-0.5"
                                role="group"
                                aria-label="Discount type"
                            >
                                {[
                                    ["percent", "%"],
                                    ["fixed", "£"],
                                ].map(([value, label]) => (
                                    <button
                                        key={value}
                                        type="button"
                                        aria-pressed={form.data.discount_type === value}
                                        onClick={() =>
                                            form.setData("discount_type", value)
                                        }
                                        className={`w-10 rounded-[10px] text-sm font-semibold transition-colors ${
                                            form.data.discount_type === value
                                                ? "bg-brand-accent text-brand-accent-text"
                                                : "text-brand-muted hover:text-brand-text"
                                        }`}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                            <input
                                type="text"
                                inputMode={percent ? "numeric" : "decimal"}
                                aria-label={percent ? "Percentage off" : "Pounds off"}
                                placeholder={percent ? "10" : "5.00"}
                                value={form.data.discount_value}
                                onChange={(e) =>
                                    form.setData("discount_value", e.target.value)
                                }
                                className={inputClass}
                            />
                        </div>
                        <span className="mt-1 block text-xs text-brand-muted">
                            {percent
                                ? "Percentage off the order total."
                                : "Pounds off the order total (never below £0)."}
                        </span>
                    </Field>
                    <Field label="Product" error={form.errors.product_id}>
                        <select
                            value={form.data.product_id}
                            onChange={(e) =>
                                form.setData("product_id", e.target.value)
                            }
                            className={inputClass}
                        >
                            <option value="">Any product</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Valid until"
                        error={form.errors.expires_on}
                        hint="Works up to the end of this day (UK time). Empty = no end date."
                    >
                        <input
                            type="date"
                            value={form.data.expires_on}
                            onChange={(e) =>
                                form.setData("expires_on", e.target.value)
                            }
                            className={inputClass}
                        />
                    </Field>
                    <Field
                        label="Total uses"
                        error={form.errors.max_uses}
                        hint="Across all shops. Empty = unlimited."
                    >
                        <input
                            type="number"
                            min={1}
                            inputMode="numeric"
                            placeholder="Unlimited"
                            value={form.data.max_uses}
                            onChange={(e) =>
                                form.setData("max_uses", e.target.value)
                            }
                            className={inputClass}
                        />
                    </Field>
                    <Field
                        label="Note"
                        error={form.errors.description}
                        hint="For you only, e.g. “Trade show, Oct 2026”."
                    >
                        <input
                            type="text"
                            maxLength={255}
                            value={form.data.description}
                            onChange={(e) =>
                                form.setData("description", e.target.value)
                            }
                            className={inputClass}
                        />
                    </Field>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5">
                        <Switch
                            checked={form.data.once_per_shop}
                            onChange={(value) =>
                                form.setData("once_per_shop", value)
                            }
                            label="Once per shop"
                            description="Each shop can use it on one paid order."
                        />
                    </div>
                    <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5">
                        <Switch
                            checked={form.data.is_active}
                            onChange={(value) => form.setData("is_active", value)}
                            label="Active"
                            description="Off: the code stops working straight away."
                        />
                    </div>
                </div>
                <div className="flex flex-wrap gap-3">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className={primaryButton}
                    >
                        {form.processing
                            ? "Saving…"
                            : coupon
                              ? "Save coupon"
                              : "Add coupon"}
                    </button>
                    <button
                        type="button"
                        onClick={onDone}
                        className={secondaryButton}
                    >
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

    return (
        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${className}`}>
            {label}
        </span>
    );
}

export default function Index({ coupons, products }) {
    // null = no form open, "new" = adding, else the coupon being edited.
    const [editing, setEditing] = useState(null);

    return (
        <AdminLayout
            title="Coupons"
            description="Discount codes owners can use when they order. Switch one off instead of deleting it - past orders keep the code."
            actions={
                <div className="flex flex-wrap gap-2">
                    <Link href="/admin/orders" className={secondaryButton}>
                        Back to orders
                    </Link>
                    <button
                        type="button"
                        onClick={() => setEditing("new")}
                        className={primaryButton}
                    >
                        <LuPlus className="h-4 w-4" /> Add coupon
                    </button>
                </div>
            }
        >
            {editing && (
                <CouponForm
                    key={editing === "new" ? "new" : editing.id}
                    coupon={editing === "new" ? null : editing}
                    products={products}
                    onDone={() => setEditing(null)}
                />
            )}

            <Panel bodyClassName="">
                {coupons.length === 0 ? (
                    <EmptyState icon={LuTicket} title="No coupons yet">
                        Add a code for a promotion, e.g. 10% off or £5 off a
                        counter display.
                    </EmptyState>
                ) : (
                    <ul className="divide-y divide-brand-border">
                        {coupons.map((coupon) => (
                            <li
                                key={coupon.id}
                                className="flex flex-wrap items-start gap-4 px-5 py-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-semibold text-brand-text">
                                        <span className="font-mono tracking-wide">
                                            {coupon.code}
                                        </span>
                                        <StatusBadge coupon={coupon} />
                                    </p>
                                    {coupon.description && (
                                        <p className="mt-1 max-w-2xl text-sm text-brand-muted">
                                            {coupon.description}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-brand-muted">
                                        {[
                                            coupon.product_name ?? "Any product",
                                            coupon.once_per_shop
                                                ? "Once per shop"
                                                : "Repeat use",
                                            coupon.expires_label
                                                ? `${coupon.is_expired ? "Ended" : "Until"} ${coupon.expires_label}`
                                                : "No end date",
                                        ].join(" · ")}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-lg font-semibold tabular-nums text-brand-text">
                                        {coupon.label}
                                    </p>
                                    <p className="text-xs text-brand-muted">
                                        {coupon.uses}
                                        {coupon.max_uses !== null &&
                                            ` / ${coupon.max_uses}`}{" "}
                                        {coupon.uses === 1 ? "use" : "uses"}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setEditing(coupon)}
                                    className={secondaryButton}
                                >
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
