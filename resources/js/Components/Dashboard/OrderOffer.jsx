import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { LuArrowRight, LuCircleAlert, LuMapPin, LuNfc } from 'react-icons/lu';
import CouponField from '@/Components/Dashboard/CouponField';
import QuantityStepper from '@/Components/Dashboard/QuantityStepper';
import { couponDiscount, formatPence, priceFor, priceSummary } from '@/lib/money';

/**
 * "What's next? Order your counter display" - the first thing on the owner's
 * Overview until the shop has ordered (online here, or recorded by the admin
 * after a bank transfer / cash payment). The server stops sending `offer`
 * once ordered, so it never comes back.
 */
export default function OrderOffer({ offer }) {
    const { errors } = usePage().props;
    const [processing, setProcessing] = useState(false);
    const [quantity, setQuantity] = useState(1);
    const [coupon, setCoupon] = useState(null);
    const list = priceFor(offer, Number(quantity) || 1);
    const total = list - couponDiscount(coupon, list);

    function order() {
        // The server answers with an Inertia::location to Stripe Checkout (or, when a coupon makes it free, back to Orders).
        router.post('/dashboard/orders', { product_id: offer.product_id, quantity: Number(quantity) || 1, coupon: coupon?.code ?? null }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <section className="mb-4 overflow-hidden rounded-2xl bg-brand-deep text-white shadow-sm" aria-labelledby="order-offer-title">
            <div className="flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center">
                <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-brand-accent text-brand-accent-text">
                    <LuNfc className="h-7 w-7" />
                </span>

                <div className="min-w-0 flex-1">
                    <p className="text-xs font-semibold uppercase tracking-wider text-brand-accent">What's next?</p>
                    <h2 id="order-offer-title" className="mt-1 font-heading text-xl font-semibold sm:text-2xl">
                        Order your counter display
                    </h2>
                    {/* The product's own description (Admin → Orders → Products), incl. the free-year offer. */}
                    {offer.description && <p className="mt-1 max-w-2xl text-sm text-white/75">{offer.description}</p>}
                    {offer.price_tiers?.length > 0 && <p className="mt-1 text-sm font-medium text-white">{priceSummary(offer)}</p>}
                    {offer.delivery_address && (
                        <p className="mt-3 flex items-start gap-1.5 text-xs text-white/60">
                            <LuMapPin className="mt-px h-3.5 w-3.5 shrink-0" />
                            <span>
                                Posted to {offer.delivery_address} ·{' '}
                                <Link href="/dashboard/settings#contact" className="font-semibold text-white underline underline-offset-2">
                                    Change
                                </Link>
                            </span>
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 flex-col gap-2 lg:items-end">
                    {!offer.can_pay_online ? (
                        <p className="max-w-xs text-sm text-white/75">Online ordering opens soon - get in touch and we'll set it up for you.</p>
                    ) : !offer.delivery_address ? (
                        <Link
                            href="/dashboard/settings#contact"
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-brand-deep transition hover:bg-white/90"
                        >
                            Add your address to order <LuArrowRight className="h-4 w-4" />
                        </Link>
                    ) : (
                        <>
                        <div className="flex items-center gap-3 lg:justify-end">
                            <span className="text-xs text-white/60">How many?</span>
                            <QuantityStepper value={quantity} onChange={setQuantity} tone="dark" label="How many displays" />
                        </div>
                        <button
                            type="button"
                            onClick={order}
                            disabled={processing}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-5 py-3 text-sm font-semibold text-brand-accent-text shadow-sm transition hover:brightness-95 disabled:opacity-60"
                        >
                            {processing ? 'Opening secure checkout…' : `Order now · ${total === 0 ? 'Free' : formatPence(total)}`}
                            {!processing && total < list && <s className="font-normal opacity-70">{formatPence(list)}</s>}
                            {!processing && <LuArrowRight className="h-4 w-4" />}
                        </button>
                        <CouponField productId={offer.product_id} quantity={Number(quantity) || 1} coupon={coupon} onChange={setCoupon} tone="dark" />
                        </>
                    )}
                    {offer.can_pay_online && offer.delivery_address && <p className="text-xs text-white/50">Secure payment by Stripe</p>}
                </div>
            </div>

            {errors.order && (
                <p role="alert" className="flex items-start gap-2 border-t border-white/10 bg-red-500/15 px-5 py-3 text-sm text-white sm:px-6">
                    <LuCircleAlert className="mt-0.5 h-4 w-4 shrink-0" />
                    {errors.order}
                </p>
            )}
        </section>
    );
}
