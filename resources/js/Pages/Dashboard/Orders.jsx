import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { LuCircleAlert, LuLandmark, LuMapPin, LuNfc, LuPackage } from 'react-icons/lu';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import OrderTracker from '@/Components/Dashboard/OrderTracker';
import QuantityStepper from '@/Components/Dashboard/QuantityStepper';
import { CopyButton, EmptyState, Panel, primaryButton } from '@/Components/Dashboard/Ui';
import { formatPence, priceFor, priceSummary } from '@/lib/money';

/** One product with a quantity and "Order" (→ Stripe Checkout). */
function ProductRow({ product, canOrder, maxQuantity }) {
    const [quantity, setQuantity] = useState(1);
    const [processing, setProcessing] = useState(false);
    const count = Number(quantity) || 1;

    function order() {
        router.post(
            '/dashboard/orders',
            { product_id: product.id, quantity: count },
            { preserveScroll: true, onStart: () => setProcessing(true), onFinish: () => setProcessing(false) },
        );
    }

    return (
        <li className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                <LuNfc className="h-5 w-5" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="font-semibold text-brand-text">
                    {product.name} <span className="font-normal text-brand-muted">· {priceSummary(product)}</span>
                </p>
                {product.description && <p className="mt-0.5 text-sm text-brand-muted">{product.description}</p>}
            </div>
            {canOrder && (
                <div className="flex flex-wrap items-center gap-3">
                    <QuantityStepper value={quantity} onChange={setQuantity} max={maxQuantity} label={`How many ${product.name}`} />
                    <button type="button" onClick={order} disabled={processing} className={primaryButton}>
                        {processing ? 'Opening checkout…' : `Order · ${formatPence(priceFor(product, count))}`}
                    </button>
                </div>
            )}
        </li>
    );
}

/** An order we arranged with them that's waiting for their payment: how much, where to, which reference. */
function PaymentDue({ order, bank }) {
    const row = (label, value) =>
        value && (
            <div className="flex items-center justify-between gap-3">
                <dt className="text-brand-muted">{label}</dt>
                <dd className="flex items-center gap-1 font-medium tabular-nums text-brand-text">
                    {value}
                    <CopyButton text={value} label={`Copy ${label.toLowerCase()}`} />
                </dd>
            </div>
        );

    return (
        <div className="mb-4 rounded-xl border border-orange-500/30 bg-orange-500/5 p-4">
            <p className="flex items-center gap-2 text-sm font-semibold text-brand-text">
                <LuLandmark className="h-4 w-4 text-orange-600" />
                {order.payment_method === 'cash'
                    ? `Awaiting your payment of ${formatPence(order.total_pence)} in cash`
                    : `Awaiting your bank transfer of ${formatPence(order.total_pence)}`}
            </p>
            {order.payment_method !== 'cash' && bank ? (
                <dl className="mt-3 space-y-1 text-sm">
                    {row('Account name', bank.account_name)}
                    {row('Bank', bank.bank_name)}
                    {row('Sort code', bank.sort_code)}
                    {row('Account number', bank.account_number)}
                    {row('Reference', order.reference)}
                </dl>
            ) : (
                <p className="mt-1 text-sm text-brand-muted">We'll be in touch about payment. Your reference is {order.reference}.</p>
            )}
            <p className="mt-3 text-xs text-brand-muted">We'll start on your order as soon as the payment arrives.</p>
        </div>
    );
}

function OrderCard({ order, bank }) {
    return (
        <li className="px-5 py-5">
            <div className="mb-4 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="font-semibold text-brand-text">
                        {order.quantity} × {order.product_name}
                    </p>
                    <p className="text-xs text-brand-muted">
                        Order #{order.id} · {order.created_label} ·{' '}
                        {order.total_pence === 0 && order.list_total_pence > 0 ? 'Free' : formatPence(order.total_pence)}
                        {order.list_total_pence > order.total_pence && order.total_pence > 0 && ` (${formatPence(order.list_total_pence - order.total_pence)} off)`}
                        {!order.awaiting_payment && ` · ${order.payment_label}`}
                    </p>
                </div>
            </div>
            {order.awaiting_payment && <PaymentDue order={order} bank={bank} />}
            <OrderTracker order={order} />
            {order.delivery_address && (
                <p className="mt-3 flex items-start gap-1.5 text-xs text-brand-muted">
                    <LuMapPin className="mt-px h-3.5 w-3.5 shrink-0" /> {order.delivery_address}
                </p>
            )}
        </li>
    );
}

export default function Orders({ shop, products, orders, canPayOnline, deliveryAddress, maxQuantity, bankDetails }) {
    const { errors } = usePage().props;
    const canOrder = canPayOnline && Boolean(deliveryAddress);

    return (
        <OwnerLayout shop={shop} title="Orders" description="Order counter displays and follow them to your door.">
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-5">
                <Panel className="xl:col-span-3" title="Your orders" bodyClassName="">
                    {orders.length === 0 ? (
                        <EmptyState icon={LuPackage} title="No orders yet">
                            Orders you place here - or arrange with us by phone - show up with their progress.
                        </EmptyState>
                    ) : (
                        <ul className="divide-y divide-brand-border">
                            {orders.map((order) => (
                                <OrderCard key={order.id} order={order} bank={bankDetails} />
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel className="xl:col-span-2" title="Order more" bodyClassName="">
                    {errors.order && (
                        <p role="alert" className="m-5 mb-0 flex items-start gap-2 rounded-xl bg-red-500/10 px-4 py-3 text-sm text-red-700">
                            <LuCircleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                            {errors.order}
                        </p>
                    )}
                    {products.length === 0 ? (
                        <EmptyState icon={LuNfc} title="Nothing to order right now" />
                    ) : (
                        <ul className="divide-y divide-brand-border">
                            {products.map((product) => (
                                <ProductRow key={product.id} product={product} canOrder={canOrder} maxQuantity={maxQuantity} />
                            ))}
                        </ul>
                    )}
                    <div className="border-t border-brand-border px-5 py-4 text-xs text-brand-muted">
                        {!canPayOnline ? (
                            "Online ordering opens soon - get in touch and we'll set it up for you."
                        ) : deliveryAddress ? (
                            <>
                                Posted to {deliveryAddress} ·{' '}
                                <Link href="/dashboard/settings#contact" className="font-semibold text-brand-accent">
                                    Change
                                </Link>{' '}
                                · Secure payment by Stripe
                            </>
                        ) : (
                            <>
                                <Link href="/dashboard/settings#contact" className="font-semibold text-brand-accent">
                                    Add your address
                                </Link>{' '}
                                so we know where to post it.
                            </>
                        )}
                    </div>
                </Panel>
            </div>
        </OwnerLayout>
    );
}
