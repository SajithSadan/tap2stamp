import { router } from "@inertiajs/react";
import { Elements, PaymentElement, useElements, useStripe } from "@stripe/react-stripe-js";
import { loadStripe } from "@stripe/stripe-js";
import { useEffect, useMemo, useState } from "react";
import { createPortal } from "react-dom";
import { LuCircleCheck, LuLock, LuX } from "react-icons/lu";
import { FieldError, primaryButton } from "@/Components/Dashboard/Ui";

/**
 * Paying for a year of a plan (Billing & plan). The server picks the window
 * by the shop's country (App\Support\PaymentProviders): Stripe's card form in
 * our own dialog, or Razorpay's payment window. Either way the browser only
 * reports back - the server checks with Stripe / Razorpay before the plan
 * starts (PlanPaymentController::confirm, plus the webhooks).
 *
 *   const { pay, busyId, dialogs } = usePlanCheckout();
 *   <button onClick={() => pay(plan)} />   ...   {dialogs}
 */

const RAZORPAY_SCRIPT = "https://checkout.razorpay.com/v1/checkout.js";

/** Razorpay's checkout.js, loaded once, on the first Pay. */
function loadRazorpay() {
    if (window.Razorpay) return Promise.resolve(window.Razorpay);

    return new Promise((resolve, reject) => {
        const script = document.createElement("script");
        script.src = RAZORPAY_SCRIPT;
        script.onload = () => resolve(window.Razorpay);
        script.onerror = () => reject(new Error("Couldn't load Razorpay."));
        document.body.appendChild(script);
    });
}

/** Brand accent for Razorpay's window, from the page's own colour. */
const accentColour = () => getComputedStyle(document.documentElement).getPropertyValue("--color-brand-accent").trim() || "#17C68B";

function Modal({ title, onClose, children }) {
    useEffect(() => {
        const onKey = (e) => e.key === "Escape" && onClose?.();
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [onClose]);

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 p-4 backdrop-blur-sm sm:items-center" onClick={onClose}>
            <div role="dialog" aria-modal="true" aria-label={title} onClick={(e) => e.stopPropagation()} className="max-h-[92dvh] w-full max-w-md overflow-y-auto rounded-2xl bg-brand-card p-5 shadow-xl">
                <div className="mb-4 flex items-start justify-between gap-3">
                    <h2 className="font-heading text-lg font-semibold text-brand-text">{title}</h2>
                    {onClose && (
                        <button type="button" onClick={onClose} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                            <LuX className="h-5 w-5" />
                        </button>
                    )}
                </div>
                {children}
            </div>
        </div>,
        document.body,
    );
}

/** The Stripe card form (Payment Element) for one PaymentIntent. */
function StripeForm({ session, onPaid }) {
    const stripe = useStripe();
    const elements = useElements();
    const [error, setError] = useState(null);
    const [paying, setPaying] = useState(false);

    async function submit(event) {
        event.preventDefault();
        if (!stripe || !elements) return;
        setPaying(true);
        setError(null);

        const { error: stripeError, paymentIntent } = await stripe.confirmPayment({
            elements,
            // Only methods that leave the page (some bank checks) come back here.
            confirmParams: { return_url: `${window.location.origin}/dashboard/plan?paid=${session.payment_id}` },
            redirect: "if_required",
        });

        if (stripeError) {
            setError(stripeError.message ?? "The payment didn't go through.");
            setPaying(false);
            return;
        }

        if (["succeeded", "processing"].includes(paymentIntent?.status)) {
            await onPaid(session.payment_id);
        }
        setPaying(false);
    }

    return (
        <form onSubmit={submit} noValidate>
            <PaymentElement options={{ layout: "tabs" }} />
            <FieldError message={error} />
            <button type="submit" disabled={!stripe || paying} className={`${primaryButton} mt-5 w-full`}>
                <LuLock className="h-4 w-4" /> {paying ? "Paying…" : `Pay ${session.amount_label}`}
            </button>
        </form>
    );
}

function StripeDialog({ session, onClose, onPaid }) {
    const stripePromise = useMemo(() => loadStripe(session.publishable_key), [session.publishable_key]);
    const options = {
        clientSecret: session.client_secret,
        appearance: { theme: "stripe", variables: { colorPrimary: accentColour(), borderRadius: "12px" } },
    };

    return (
        <Modal title={`Pay for ${session.plan}`} onClose={onClose}>
            <p className="-mt-2 mb-4 text-sm text-brand-muted">
                <strong className="text-brand-text">{session.amount_label}</strong> for one year · {session.period_label}
            </p>
            <Elements stripe={stripePromise} options={options}>
                <StripeForm session={session} onPaid={onPaid} />
            </Elements>
            <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-brand-muted">
                <LuLock className="h-3.5 w-3.5" /> Secure payment by Stripe
            </p>
        </Modal>
    );
}

export function usePlanCheckout({ shopName }) {
    const [busyId, setBusyId] = useState(null);
    const [stripeSession, setStripeSession] = useState(null);
    const [done, setDone] = useState(null); // {plan, paid_until_label} | {pending: message}
    const [error, setError] = useState(null);

    /** Ask the server whether payment {id} really went through (it checks with the provider). */
    async function confirm(paymentId, plan, extra = {}) {
        try {
            const { data } = await window.axios.post("/dashboard/plan/pay/confirm", { payment_id: paymentId, ...extra });
            setStripeSession(null);
            setDone(data.status === "paid" ? { plan, paid_until_label: data.paid_until_label } : { pending: data.message });
            router.reload({ only: ["shop", "plans"] });
        } catch {
            setError("We couldn't check the payment. If money left your account, refresh in a minute - it will show here.");
        }
    }

    async function pay(plan) {
        setBusyId(plan.id);
        setError(null);

        try {
            const { data } = await window.axios.post("/dashboard/plan/pay", { plan_id: plan.id });

            if (data.provider === "stripe") {
                setStripeSession(data);
            } else {
                const Razorpay = await loadRazorpay();
                new Razorpay({
                    key: data.key_id,
                    order_id: data.order_id,
                    amount: data.amount,
                    currency: data.currency,
                    name: "Tada Tap",
                    description: `${data.plan} plan · 1 year`,
                    image: `${window.location.origin}/images/favicon-512.png`,
                    prefill: data.prefill,
                    notes: { shop: shopName },
                    theme: { color: accentColour() },
                    handler: (response) => confirm(data.payment_id, data.plan, response),
                }).open();
            }
        } catch (err) {
            setError(err.response?.data?.message ?? "Payments aren't available right now. Please try again in a few minutes.");
        } finally {
            setBusyId(null);
        }
    }

    // Back from a payment method that left the page (Stripe return_url): check it once.
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const paid = params.get("paid");
        if (!paid) return;
        params.delete("paid");
        ["payment_intent", "payment_intent_client_secret", "redirect_status"].forEach((key) => params.delete(key));
        window.history.replaceState(window.history.state, "", `${window.location.pathname}${params.size ? `?${params}` : ""}`);
        confirm(Number(paid), null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const dialogs = (
        <>
            {stripeSession && (
                <StripeDialog
                    session={stripeSession}
                    onClose={() => setStripeSession(null)}
                    onPaid={(paymentId) => confirm(paymentId, stripeSession.plan)}
                />
            )}
            {done && (
                <Modal title={done.pending ? "Payment received?" : "Thank you!"} onClose={() => setDone(null)}>
                    {done.pending ? (
                        <p className="text-sm text-brand-muted">{done.pending}</p>
                    ) : (
                        <div className="text-center">
                            <LuCircleCheck className="mx-auto h-12 w-12 text-brand-accent" />
                            <p className="mt-3 font-heading text-lg font-semibold text-brand-text">
                                {done.plan ? `You're on ${done.plan}` : "Payment received"}
                            </p>
                            {done.paid_until_label && <p className="mt-1 text-sm text-brand-muted">Paid until {done.paid_until_label}.</p>}
                        </div>
                    )}
                    <button type="button" onClick={() => setDone(null)} className={`${primaryButton} mt-5 w-full`}>
                        Done
                    </button>
                </Modal>
            )}
        </>
    );

    return { pay, busyId, dialogs, error };
}
