import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { LuCircleAlert } from 'react-icons/lu';
import { ShopPicker } from '@/Components/Dashboard/QrDestinationField';
import QuantityStepper from '@/Components/Dashboard/QuantityStepper';
import { FieldError, inputClass, primaryButton, secondaryButton } from '@/Components/Dashboard/Ui';
import { formatPence, penceToInput, priceFor } from '@/lib/money';

/** How the order is paid → payment_method + whether the money is already in. */
const PAYMENT = [
    { key: 'awaiting', label: 'Awaiting bank transfer', hint: 'Confirm it when the money arrives.', method: 'bank_transfer', paid: false },
    { key: 'bank', label: 'Paid by bank transfer', hint: 'Already in the account.', method: 'bank_transfer', paid: true },
    { key: 'cash', label: 'Paid in cash', hint: 'Taken in person.', method: 'cash', paid: true },
    { key: 'free', label: 'Free', hint: 'Promotion - nothing to pay.', method: 'free', paid: true },
];

const toPence = (value) => (/^\d+(\.\d{1,2})?$/.test(String(value).trim()) ? Math.round(Number(value) * 100) : null);

/**
 * Admin: an order arranged with a shop (phone, visit, promotion). Agreed
 * price defaults to the normal price; lower it for an offer, or pick Free.
 * `shop` fixes the shop (its settings page); otherwise `shops` gives a picker.
 */
export default function ManualOrderForm({ shop = null, shops = [], products, deliveryAddress = null, bankDetailsSet = true, onDone }) {
    const first = products[0];
    const form = useForm({
        shop_id: shop?.id ?? '',
        product_id: first?.id ?? '',
        quantity: 1,
        payment: 'awaiting',
        amount: first ? penceToInput(priceFor(first, 1)) : '',
        note: '',
    });

    const product = products.find((p) => String(p.id) === String(form.data.product_id));
    const quantity = Number(form.data.quantity) || 1;
    const normal = product ? priceFor(product, quantity) : 0;
    const payment = PAYMENT.find((p) => p.key === form.data.payment);
    const agreed = payment.key === 'free' ? 0 : toPence(form.data.amount);
    const discount = agreed !== null && agreed < normal ? normal - agreed : 0;

    // The agreed price follows product × quantity until the admin types their own.
    const [priceEdited, setPriceEdited] = useState(false);

    function pick(changes) {
        form.setData((data) => {
            const next = { ...data, ...changes };
            const p = products.find((x) => String(x.id) === String(next.product_id));

            return p && !priceEdited ? { ...next, amount: penceToInput(priceFor(p, Number(next.quantity) || 1)) } : next;
        });
    }

    function submit(e) {
        e.preventDefault();
        form.transform((data) => ({
            product_id: data.product_id,
            quantity: Number(data.quantity) || 1,
            payment_method: payment.method,
            paid: payment.paid,
            amount: payment.key === 'free' ? null : data.amount,
            note: data.note,
        }));
        form.post(`/admin/shops/${form.data.shop_id}/orders`, {
            preserveScroll: true,
            onSuccess: () => onDone?.(),
        });
    }

    const label = 'mb-1.5 block text-sm font-medium text-brand-text';

    return (
        <form onSubmit={submit} noValidate className="space-y-4">
            {!shop && (
                <div>
                    <span className={label}>Shop</span>
                    <ShopPicker
                        id="order-shop"
                        shops={shops}
                        value={form.data.shop_id}
                        onChange={(id) => form.setData('shop_id', id)}
                        label="Choose a shop"
                        emptyLabel="Choose a shop"
                    />
                </div>
            )}

            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                <label className="block min-w-0">
                    <span className={label}>Product</span>
                    <select value={form.data.product_id} onChange={(e) => pick({ product_id: e.target.value })} className={inputClass}>
                        {products.map((p) => (
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

            <fieldset>
                <legend className={label}>Payment</legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {PAYMENT.map((p) => (
                        <label
                            key={p.key}
                            className={`flex cursor-pointer items-start gap-2.5 rounded-xl border px-3.5 py-2.5 transition-colors ${
                                form.data.payment === p.key ? 'border-brand-accent bg-brand-accent/5' : 'border-brand-border hover:bg-brand-bg'
                            }`}
                        >
                            <input
                                type="radio"
                                name="payment"
                                checked={form.data.payment === p.key}
                                onChange={() => form.setData('payment', p.key)}
                                className="mt-1 accent-[var(--color-brand-accent)]"
                            />
                            <span>
                                <span className="block text-sm font-medium text-brand-text">{p.label}</span>
                                <span className="block text-xs text-brand-muted">{p.hint}</span>
                            </span>
                        </label>
                    ))}
                </div>
                <FieldError message={form.errors.payment_method} />
                {form.data.payment === 'awaiting' && !bankDetailsSet && (
                    <p className="mt-2 flex items-start gap-1.5 text-xs text-amber-700">
                        <LuCircleAlert className="mt-px h-3.5 w-3.5 shrink-0" />
                        Add your bank details in Admin → Settings, so the shop sees where to pay.
                    </p>
                )}
            </fieldset>

            {form.data.payment !== 'free' && (
                <label className="block">
                    <span className={label}>Agreed price (£)</span>
                    <input
                        type="text"
                        inputMode="decimal"
                        value={form.data.amount}
                        onChange={(e) => {
                            setPriceEdited(true);
                            form.setData('amount', e.target.value);
                        }}
                        className={`${inputClass} sm:max-w-48`}
                    />
                    <FieldError message={form.errors.amount} />
                    {!form.errors.amount && (
                        <span className="mt-1 block text-xs text-brand-muted">
                            Normal price {formatPence(normal)}
                            {discount > 0 && <strong className="font-semibold text-emerald-700"> · {formatPence(discount)} off</strong>}
                        </span>
                    )}
                </label>
            )}

            <label className="block">
                <span className={label}>Note (optional)</span>
                <input
                    type="text"
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                    placeholder="e.g. Spring offer, agreed by phone"
                    className={inputClass}
                />
                <FieldError message={form.errors.note} />
            </label>

            {deliveryAddress !== undefined && shop && (
                <p className="text-xs text-brand-muted">{deliveryAddress ? `Will be posted to: ${deliveryAddress}` : 'No address on file yet - add one under Business contact & location.'}</p>
            )}

            <div className="flex flex-wrap gap-3">
                <button type="submit" disabled={form.processing || !form.data.shop_id} className={primaryButton}>
                    {form.processing ? 'Saving…' : form.data.payment === 'awaiting' ? 'Create order' : 'Create paid order'}
                </button>
                {onDone && (
                    <button type="button" onClick={onDone} className={secondaryButton}>
                        Cancel
                    </button>
                )}
            </div>
        </form>
    );
}
