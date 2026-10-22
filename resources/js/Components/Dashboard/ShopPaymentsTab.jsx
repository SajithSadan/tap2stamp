import { useForm } from "@inertiajs/react";
import { useState } from "react";
import { LuCreditCard, LuUndo2, LuX } from "react-icons/lu";
import { EmptyState, FieldError, inputClass, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";

/**
 * Admin → shop settings → Payments: every payment of the shop, newest first -
 * plan payments (Razorpay / Stripe) and paid counter display orders (Stripe,
 * bank transfer, cash, free), also while its free trial runs. A payment made
 * online can be refunded here (App\Services\ShopPayments): all or part, through
 * the provider. Bank transfer / cash are refunded outside the app.
 */
export default function ShopPaymentsTab({ shop, payments }) {
    const [refunding, setRefunding] = useState(null);

    return (
        <Panel title="Payments" description="Plan payments and counter display orders. Online payments can be refunded here." bodyClassName="">
            {payments.length === 0 ? (
                <div className="p-5">
                    <EmptyState icon={LuCreditCard} title="No payments yet" />
                </div>
            ) : (
                <ul className="divide-y divide-brand-border">
                    {payments.map((p) => {
                        const refunded = p.refund_label?.startsWith("Refunded");

                        return (
                            <li key={p.key} className="grid gap-x-4 gap-y-1 px-5 py-3 text-sm sm:grid-cols-[7rem_minmax(0,1fr)_auto_7rem] sm:items-center">
                                <span className="text-brand-muted">{p.paid_label}</span>
                                <span className="min-w-0">
                                    <span className="mr-2 rounded-full bg-brand-bg px-2 py-0.5 text-xs font-medium text-brand-muted ring-1 ring-inset ring-brand-border">
                                        {p.kind === "plan" ? "Plan" : "Order"}
                                    </span>
                                    <span className="font-medium text-brand-text">{p.what}</span>
                                    {p.period_label && <span className="text-brand-muted"> · covers {p.period_label}</span>}
                                    <span className="block truncate text-xs text-brand-muted">
                                        {p.method}
                                        {p.reference && ` · ${p.reference}`}
                                        {p.refund_label && <span className="font-medium text-amber-800"> · {p.refund_label}</span>}
                                        {p.disputed && <span className="font-medium text-red-700"> · Disputed</span>}
                                    </span>
                                </span>
                                <span className={`font-semibold tabular-nums sm:text-right ${refunded ? "text-brand-muted line-through" : "text-brand-text"}`}>{p.amount}</span>
                                <span className="sm:text-right">
                                    {p.can_refund && (
                                        <button type="button" onClick={() => setRefunding(p)} className={`${secondaryButton} !px-2.5 !py-1.5 text-xs`}>
                                            <LuUndo2 className="h-3.5 w-3.5" /> Refund
                                        </button>
                                    )}
                                </span>
                            </li>
                        );
                    })}
                </ul>
            )}
            {refunding && <RefundDialog shop={shop} payment={refunding} onClose={() => setRefunding(null)} />}
        </Panel>
    );
}

/** All or part of one online payment, with an optional reason; typed REFUND to confirm (it can't be undone). */
function RefundDialog({ shop, payment, onClose }) {
    const form = useForm({ amount: payment.refundable, reason: "", confirm: "" });
    const label = "mb-1.5 block text-sm font-medium text-brand-text";

    function submit(e) {
        e.preventDefault();
        form.transform(({ confirm, ...data }) => data);
        form.post(`/admin/shops/${shop.id}/payments/${payment.kind}/${payment.id}/refund`, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={onClose}>
            <form
                onSubmit={submit}
                noValidate
                role="dialog"
                aria-modal="true"
                aria-label="Refund payment"
                onClick={(e) => e.stopPropagation()}
                className="max-h-[92dvh] w-full space-y-4 overflow-y-auto rounded-t-2xl border border-brand-border bg-brand-card p-6 text-left sm:max-w-md sm:rounded-2xl"
            >
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h2 className="font-heading text-lg font-semibold text-brand-text">Refund {payment.what}</h2>
                        <p className="mt-0.5 text-sm text-brand-muted">
                            {payment.method} · {payment.paid_label} · up to {payment.refundable_label}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Close" className="-m-1 rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg">
                        <LuX className="h-4 w-4" />
                    </button>
                </div>

                <label className="block">
                    <span className={label}>Amount ({payment.currency})</span>
                    <input type="text" inputMode="decimal" value={form.data.amount} onChange={(e) => form.setData("amount", e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.amount} />
                </label>
                <label className="block">
                    <span className={label}>Reason (optional)</span>
                    <input type="text" maxLength={200} value={form.data.reason} onChange={(e) => form.setData("reason", e.target.value)} className={inputClass} />
                    <FieldError message={form.errors.reason} />
                </label>

                <p className="rounded-xl bg-amber-50 px-3.5 py-3 text-sm text-amber-950 ring-1 ring-inset ring-amber-300">
                    The money goes back to the shop's card / account and this can't be undone.
                    {payment.kind === "plan" && " Refunding all of it takes the paid year off the shop."}
                </p>
                <label className="block">
                    <span className={label}>Type REFUND to confirm</span>
                    <input type="text" value={form.data.confirm} onChange={(e) => form.setData("confirm", e.target.value)} autoComplete="off" className={inputClass} />
                </label>

                <div className="flex flex-wrap gap-3">
                    <button type="submit" disabled={form.processing || form.data.confirm !== "REFUND"} className={`${primaryButton} !bg-red-600 !text-white`}>
                        {form.processing ? "Refunding…" : "Refund"}
                    </button>
                    <button type="button" onClick={onClose} className={secondaryButton}>
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    );
}
