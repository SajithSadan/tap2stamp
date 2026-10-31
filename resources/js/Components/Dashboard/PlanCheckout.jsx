import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { LuCircleCheck, LuX } from "react-icons/lu";
import { primaryButton } from "@/Components/Dashboard/Ui";

/**
 * Paying for a year of a plan (Billing & plan). The server picks the window
 * by the shop's country (App\Support\PaymentProviders): Stripe's hosted
 * Checkout page (back here with ?session_id=…), or Razorpay's payment window.
 * Either way the browser only reports back - the server checks with Stripe /
 * Razorpay before the plan starts (PlanPaymentController::confirm, plus the
 * webhooks).
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

export function usePlanCheckout({ shopName }) {
    const [busyId, setBusyId] = useState(null);
    const [done, setDone] = useState(null); // {plan, paid_until_label} | {pending: message}
    const [error, setError] = useState(null);

    /** Ask the server whether the payment really went through (it checks with the provider). */
    async function confirm(body, plan) {
        try {
            const { data } = await window.axios.post("/dashboard/plan/pay/confirm", body);
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
                // Stripe's own page; it comes back with ?session_id=… (or without, if cancelled).
                // The button stays busy while the browser leaves.
                window.location.assign(data.url);
                return;
            }

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
                handler: (response) => confirm({ payment_id: data.payment_id, ...response }, data.plan),
            }).open();
            setBusyId(null);
        } catch (err) {
            setError(err.response?.data?.message ?? "Payments aren't available right now. Please try again in a few minutes.");
            setBusyId(null);
        }
    }

    // Back from Stripe's Checkout page: check that session once.
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const sessionId = params.get("session_id");
        if (!sessionId) return;
        params.delete("session_id");
        window.history.replaceState(window.history.state, "", `${window.location.pathname}${params.size ? `?${params}` : ""}`);
        confirm({ session_id: sessionId }, null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const dialogs = (
        <>
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
