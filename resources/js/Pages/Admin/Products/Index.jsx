import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";
import { LuPencil, LuPlus, LuStar, LuTag, LuTrash2 } from "react-icons/lu";
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
import {
    formatPence,
    MAX_QUANTITY,
    penceToInput,
    priceFor,
    priceSummary,
} from "@/lib/money";

const BLANK = {
    name: "",
    description: "",
    price: "",
    price_tiers: [],
    is_active: true,
    is_featured: false,
};

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

/** Pounds as typed → pence, or null when it isn't a price yet. */
const toPence = (value) =>
    /^\d+(\.\d{1,2})?$/.test(String(value).trim())
        ? Math.round(Number(value) * 100)
        : null;

/**
 * "From item N: £X each" rows. Each item is charged the price for its
 * position, so £40 + "from 2nd: £20" makes 1 = £40, 2 = £60, 3 = £80.
 */
function PriceBreaks({ form }) {
    const tiers = form.data.price_tiers;
    const set = (next) => form.setData("price_tiers", next);
    const update = (i, key, value) =>
        set(tiers.map((t, j) => (j === i ? { ...t, [key]: value } : t)));

    function add() {
        const next = Math.min(
            MAX_QUANTITY,
            Math.max(1, ...tiers.map((t) => Number(t.from) || 1)) + 1,
        );
        set([...tiers, { from: next, price: "" }]);
    }

    // Live example, only once every price is a real number.
    const base = toPence(form.data.price);
    const parsed = tiers.map((t) => ({
        from: Number(t.from),
        price_pence: toPence(t.price),
    }));
    const preview =
        base !== null && parsed.every((t) => t.price_pence !== null && t.from >= 2)
            ? [1, 2, 3, 5, 10].map(
                  (q) =>
                      `${q} → ${formatPence(priceFor({ price_pence: base, price_tiers: parsed }, q))}`,
              )
            : null;

    return (
        <div className="rounded-xl border border-brand-border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-sm font-medium text-brand-text">
                        Price breaks{" "}
                        <span className="font-normal text-brand-muted">
                            (optional)
                        </span>
                    </p>
                    <p className="mt-0.5 text-xs text-brand-muted">
                        Cheaper extra items, e.g. from the 2nd item: £20 each.
                    </p>
                </div>
                {tiers.length < 5 && (
                    <button
                        type="button"
                        onClick={add}
                        className={`${secondaryButton} !px-2.5 !py-1.5 text-xs`}
                    >
                        <LuPlus className="h-3.5 w-3.5" /> Add price break
                    </button>
                )}
            </div>

            {tiers.length > 0 && (
                <ul className="mt-3 space-y-2">
                    {tiers.map((tier, i) => (
                        <li key={i}>
                            <div className="flex flex-wrap items-center gap-2 text-sm text-brand-text">
                                <span>From item</span>
                                <input
                                    type="number"
                                    min={2}
                                    max={MAX_QUANTITY}
                                    aria-label="From item number"
                                    value={tier.from}
                                    onChange={(e) =>
                                        update(i, "from", e.target.value)
                                    }
                                    className={`${inputClass} !w-20 text-center`}
                                />
                                <span>on: £</span>
                                <input
                                    type="text"
                                    inputMode="decimal"
                                    aria-label="Price for each of these items"
                                    placeholder="20.00"
                                    value={tier.price}
                                    onChange={(e) =>
                                        update(i, "price", e.target.value)
                                    }
                                    className={`${inputClass} !w-28`}
                                />
                                <span>each</span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        set(tiers.filter((_, j) => j !== i))
                                    }
                                    aria-label="Remove this price break"
                                    className="rounded-lg p-2 text-brand-muted hover:bg-red-50 hover:text-red-600"
                                >
                                    <LuTrash2 className="h-4 w-4" />
                                </button>
                            </div>
                            <FieldError
                                message={
                                    form.errors[`price_tiers.${i}.from`] ??
                                    form.errors[`price_tiers.${i}.price`]
                                }
                            />
                        </li>
                    ))}
                </ul>
            )}
            <FieldError message={form.errors.price_tiers} />

            {preview && tiers.length > 0 && (
                <p className="mt-3 text-xs text-brand-muted">
                    Totals: {preview.join(" · ")}
                </p>
            )}
        </div>
    );
}

/** Add (product = null) or edit one product. */
function ProductForm({ product, onDone }) {
    const form = useForm(
        product
            ? {
                  name: product.name,
                  description: product.description ?? "",
                  price: penceToInput(product.price_pence),
                  price_tiers: (product.price_tiers ?? []).map((t) => ({
                      from: t.from,
                      price: penceToInput(t.price_pence),
                  })),
                  is_active: product.is_active,
                  is_featured: product.is_featured,
              }
            : BLANK,
    );

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };

        if (product) form.put(`/admin/products/${product.id}`, options);
        else form.post("/admin/products", options);
    }

    return (
        <Panel
            className="mb-4"
            title={product ? `Edit ${product.name}` : "Add a product"}
            description="Prices are what the shop pays, in pounds."
        >
            <form onSubmit={submit} noValidate className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-[2fr_1fr]">
                    <Field label="Name" error={form.errors.name}>
                        <input
                            type="text"
                            value={form.data.name}
                            onChange={(e) => form.setData("name", e.target.value)}
                            className={inputClass}
                        />
                    </Field>
                    <Field label="Price (£)" error={form.errors.price}>
                        <input
                            type="text"
                            inputMode="decimal"
                            placeholder="40.00"
                            value={form.data.price}
                            onChange={(e) => form.setData("price", e.target.value)}
                            className={inputClass}
                        />
                    </Field>
                </div>
                <PriceBreaks form={form} />
                <Field
                    label="Description"
                    error={form.errors.description}
                    hint="Shown to owners on the order banner - mention the free first year here."
                >
                    <textarea
                        rows={3}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData("description", e.target.value)
                        }
                        className={inputClass}
                    />
                </Field>
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5">
                        <Switch
                            checked={form.data.is_active}
                            onChange={(value) => form.setData("is_active", value)}
                            label="On sale"
                            description="Off: owners can't order it and it's hidden from the order forms."
                        />
                    </div>
                    <div className="rounded-xl border border-brand-border bg-brand-bg/60 p-3.5">
                        <Switch
                            checked={form.data.is_featured}
                            onChange={(value) =>
                                form.setData("is_featured", value)
                            }
                            label="Offer on owner dashboards"
                            description="The one product owners see under “What's next?” until they order. Only one at a time."
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
                            : product
                              ? "Save product"
                              : "Add product"}
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

export default function Index({ products }) {
    // null = no form open, "new" = adding, else the product being edited.
    const [editing, setEditing] = useState(null);

    return (
        <AdminLayout
            title="Products"
            description="What shops can order. Switch a product off instead of deleting it - past orders keep pointing at it."
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
                        <LuPlus className="h-4 w-4" /> Add product
                    </button>
                </div>
            }
        >
            {editing && (
                <ProductForm
                    key={editing === "new" ? "new" : editing.id}
                    product={editing === "new" ? null : editing}
                    onDone={() => setEditing(null)}
                />
            )}

            <Panel bodyClassName="">
                {products.length === 0 ? (
                    <EmptyState icon={LuTag} title="No products yet" />
                ) : (
                    <ul className="divide-y divide-brand-border">
                        {products.map((product) => (
                            <li
                                key={product.id}
                                className="flex flex-wrap items-start gap-4 px-5 py-4"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 font-semibold text-brand-text">
                                        {product.name}
                                        {product.is_featured && (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-brand-accent/10 px-2 py-0.5 text-xs font-medium text-brand-accent">
                                                <LuStar className="h-3 w-3" />{" "}
                                                Offered to owners
                                            </span>
                                        )}
                                        {!product.is_active && (
                                            <span className="rounded-full bg-brand-bg px-2 py-0.5 text-xs font-medium text-brand-muted ring-1 ring-inset ring-brand-border">
                                                Off sale
                                            </span>
                                        )}
                                    </p>
                                    {product.description && (
                                        <p className="mt-1 max-w-2xl text-sm text-brand-muted">
                                            {product.description}
                                        </p>
                                    )}
                                    <p className="mt-1 text-xs text-brand-muted">
                                        {product.paid_orders}{" "}
                                        {product.paid_orders === 1
                                            ? "order"
                                            : "orders"}
                                    </p>
                                </div>
                                <div className="text-right">
                                    <p className="text-lg font-semibold tabular-nums text-brand-text">
                                        {formatPence(product.price_pence)}
                                    </p>
                                    {product.price_tiers?.length > 0 && (
                                        <p className="max-w-56 text-xs text-brand-muted">
                                            {priceSummary(product)}
                                        </p>
                                    )}
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setEditing(product)}
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
