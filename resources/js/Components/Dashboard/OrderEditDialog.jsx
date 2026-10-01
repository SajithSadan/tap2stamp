import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { LuLock, LuPencil, LuX } from 'react-icons/lu';
import QuantityStepper from '@/Components/Dashboard/QuantityStepper';
import { FieldError, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import { formatPence, penceToInput, priceFor } from '@/lib/money';

const METHODS = [
    ['bank_transfer', 'Bank transfer'],
    ['cash', 'Cash'],
    ['free', 'Free'],
];

const toPence = (value) => (/^\d+(\.\d{1,2})?$/.test(String(value).trim()) ? Math.round(Number(value) * 100) : null);

/**
 * Admin: "Edit" + dialog for one order. Orders we arranged can change product,
 * quantity, price and method; a Stripe order only its delivery address and
 * note (the amount is what Stripe charged). Payment status itself changes
 * through Confirm payment / Cancel, and progress through Update.
 */
export default function OrderEditDialog({ order, products, buttonClassName = secondaryButton }) {
    const [open, setOpen] = useState(false);
    const stripe = order.payment_method === 'stripe';
    const editable = order.status !== 'cancelled' && !(stripe && order.status !== 'paid');
    // An order's product may since have gone off sale - keep it selectable.
    const choices = products.some((p) => p.id === order.product_id)
        ? products
        : [{ id: order.product_id, name: order.product_name, price_pence: order.unit_price_pence, price_tiers: null }, ...products];

    const initial = () => ({
        product_id: order.product_id,
        quantity: order.quantity,
        payment_method: stripe ? 'stripe' : order.payment_method,
        amount: penceToInput(order.total_pence),
        delivery_address: order.delivery_address ?? '',
        note: order.note ?? '',
    });
    const form = useForm(initial());
    // Like New order: the price follows product × quantity until typed by hand.
    const [priceEdited, setPriceEdited] = useState(false);
    const wasDiscounted = order.list_total_pence > order.total_pence;

    if (!editable) return null;

    const product = choices.find((p) => String(p.id) === String(form.data.product_id));
    const normal = product ? priceFor(product, Number(form.data.quantity) || 1) : 0;
    const agreed = form.data.payment_method === 'free' ? 0 : toPence(form.data.amount);

    function show() {
        form.setData(initial());
        form.clearErrors();
        setPriceEdited(false);
        setOpen(true);
    }

    /** Product or quantity changed: move the price to the new normal price, unless typed. */
    function pick(changes) {
        form.setData((data) => {
            const next = { ...data, ...changes };
            const p = choices.find((x) => String(x.id) === String(next.product_id));

            return p && !priceEdited ? { ...next, amount: penceToInput(priceFor(p, Number(next.quantity) || 1)) } : next;
        });
    }

    function submit(e) {
        e.preventDefault();
        form.transform((data) => (stripe ? { delivery_address: data.delivery_address, note: data.note } : data));
        form.put(`/admin/orders/${order.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) });
    }

    const label = 'mb-1.5 block text-sm font-medium text-brand-text';

    return (
        <>
            <button type="button" onClick={show} aria-label={`Edit order #${order.id}`} title="Edit order" className={buttonClassName}>
                <LuPencil className="h-3.5 w-3.5" /> Edit
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={() => setOpen(false)}>
                    <form
                        onSubmit={submit}
                        noValidate
                        role="dialog"
                        aria-modal="true"
                        aria-label={`Edit order #${order.id}`}
                        onClick={(e) => e.stopPropagation()}
                        className="max-h-[92dvh] w-full space-y-4 overflow-y-auto rounded-t-2xl border border-brand-border bg-brand-card p-6 text-left sm:max-w-lg sm:rounded-2xl"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <h2 className="font-heading text-lg font-semibold text-brand-text">Edit order #{order.id}</h2>
                                {order.shop_name && <p className="mt-0.5 text-sm text-brand-muted">{order.shop_name}</p>}
                            </div>
                            <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="-m-1 rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg">
                                <LuX className="h-4 w-4" />
                            </button>
                        </div>

                        {stripe ? (
                            <p className="flex items-start gap-2 rounded-xl bg-brand-bg px-3.5 py-3 text-sm text-brand-muted">
                                <LuLock className="mt-0.5 h-4 w-4 shrink-0" />
                                Paid online: {order.quantity} × {order.product_name}, {formatPence(order.total_pence)}. That's what Stripe charged, so only the
                                delivery address and note can change.
                            </p>
                        ) : (
                            <>
                                <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                                    <label className="block min-w-0">
                                        <span className={label}>Product</span>
                                        <select value={form.data.product_id} onChange={(e) => pick({ product_id: e.target.value })} className={inputClass}>
                                            {choices.map((p) => (
                                                <option key={p.id} value={p.id}>
                                                    {p.name}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError message={form.errors.product_id} />
                                    </label>
                                    {/* Not a <label>: clicking its text would press "−". */}
                                    <div>
                                        <span className={label}>Quantity</span>
                                        <QuantityStepper value={form.data.quantity} onChange={(q) => pick({ quantity: q })} />
                                        <FieldError message={form.errors.quantity} />
                                    </div>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className="block">
                                        <span className={label}>Payment</span>
                                        <select value={form.data.payment_method} onChange={(e) => form.setData('payment_method', e.target.value)} className={inputClass}>
                                            {METHODS.map(([value, text]) => (
                                                <option key={value} value={value}>
                                                    {text}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError message={form.errors.payment_method} />
                                    </label>
                                    {form.data.payment_method !== 'free' && (
                                        <label className="block">
                                            <span className={label}>Price (£)</span>
                                            <input
                                                type="text"
                                                inputMode="decimal"
                                                value={form.data.amount}
                                                onChange={(e) => {
                                                    setPriceEdited(true);
                                                    form.setData('amount', e.target.value);
                                                }}
                                                className={inputClass}
                                            />
                                            <FieldError message={form.errors.amount} />
                                        </label>
                                    )}
                                </div>
                                <p className="-mt-2 text-xs text-brand-muted">
                                    Normal price {formatPence(normal)}
                                    {agreed !== null && agreed < normal && (
                                        <strong className="font-semibold text-emerald-700"> · {formatPence(normal - agreed)} off</strong>
                                    )}
                                    {agreed !== null && agreed !== normal && form.data.payment_method !== 'free' && (
                                        <>
                                            {' · '}
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    setPriceEdited(false);
                                                    form.setData('amount', penceToInput(normal));
                                                }}
                                                className="font-semibold text-brand-accent hover:underline"
                                            >
                                                Use normal price
                                            </button>
                                        </>
                                    )}
                                    {wasDiscounted &&
                                        !priceEdited &&
                                        (String(form.data.product_id) !== String(order.product_id) || Number(form.data.quantity) !== order.quantity) &&
                                        ` · It had ${formatPence(order.list_total_pence - order.total_pence)} off - type the price if the offer still applies.`}
                                    {order.status === 'paid' && ' · Already paid - this only corrects the record.'}
                                    {order.awaiting_payment && form.data.payment_method === 'free' && ' · Free means nothing is owed, so the order goes ahead.'}
                                </p>
                            </>
                        )}

                        <label className="block">
                            <span className={label}>Deliver to</span>
                            <textarea
                                rows={3}
                                value={form.data.delivery_address}
                                onChange={(e) => form.setData('delivery_address', e.target.value)}
                                className={`${inputClass} resize-y`}
                            />
                            <FieldError message={form.errors.delivery_address} />
                        </label>
                        <label className="block">
                            <span className={label}>Note</span>
                            <input type="text" value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} className={inputClass} />
                            <FieldError message={form.errors.note} />
                        </label>

                        <div className="flex flex-col-reverse gap-2 pt-1 sm:flex-row sm:justify-end">
                            <button type="button" onClick={() => setOpen(false)} className={secondaryButton}>
                                Cancel
                            </button>
                            <button type="submit" disabled={form.processing} className={primaryButton}>
                                {form.processing ? 'Saving…' : 'Save changes'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </>
    );
}
