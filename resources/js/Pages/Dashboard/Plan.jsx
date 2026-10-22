import { usePage } from "@inertiajs/react";
import { LuClock } from "react-icons/lu";
import OwnerLayout from "@/Components/Dashboard/OwnerLayout";
import { usePlanCheckout } from "@/Components/Dashboard/PlanCheckout";
import PlanCards, { actionBlocked, actionLabel, upgradeButtonClass } from "@/Components/PlanCards";
import PlanFeature from "@/Components/PlanFeature";

/** Highlighted from this many days left (and red from a week, or once ended). */
const SOON_DAYS = 30;

/**
 * How long the current plan has left: the paid year, else the free trial.
 * {days (null = no end), title, detail, tone: ok | soon | urgent}.
 */
function remaining(sub) {
    if (sub.status === "expired") {
        return { days: 0, title: "Expired", detail: `Your ${sub.paid ? "yearly plan" : "free trial"} ended on ${sub.ended_label}.`, tone: "urgent" };
    }

    const paid = sub.status === "active";
    const days = paid ? sub.paid_days_left : sub.trial_days_left;
    if (days === null) return { days: null, title: "Free trial", detail: "No end date.", tone: "ok" };

    // Paid during the trial: the trial runs out first, then the paid year starts.
    const detail = paid
        ? sub.trial_running
            ? `Free trial until ${sub.trial_ends_label}, then your paid year runs to ${sub.paid_until_label}.`
            : `Paid until ${sub.paid_until_label}.`
        : `Free trial ends ${sub.trial_ends_label}.`;

    return {
        days,
        title: days === 0 ? "Ends today" : `${days} ${days === 1 ? "day" : "days"} left`,
        detail,
        tone: days <= 7 ? "urgent" : days <= SOON_DAYS ? "soon" : "ok",
    };
}

const TONE = {
    ok: "bg-brand-bg text-brand-text",
    soon: "bg-amber-50 text-amber-950 ring-1 ring-inset ring-amber-300",
    urgent: "bg-red-50 text-red-950 ring-1 ring-inset ring-red-300",
};

/**
 * Billing & plan: the shop's current plan at the top (highlighted, with how
 * long it has left - amber from 30 days, red from 7 or once ended), then the
 * other plans, priced in the shop's own currency. A free trial that ended
 * without paying has no plan to show: every plan is listed under "Choose a
 * plan". Each button's words come from the plan's `action` (Upgrade / Pay now
 * / Renew / Switch to …); a paid plan's own Renew opens 30 days before it
 * ends. Buttons open the payment window (PlanCheckout: Stripe / Razorpay by
 * country). No buttons for an admin viewing as the owner - only the owner pays.
 */
/** The shop's paid plan payments, newest first. Nothing at all until the first one. */
function PaymentHistory({ payments }) {
    if (payments.length === 0) return null;

    return (
        <>
            <h2 className="mb-3 mt-8 font-heading text-lg font-semibold text-brand-text">Payment history</h2>
            <ul className="divide-y divide-brand-border rounded-2xl border border-brand-border bg-brand-card">
                {payments.map((p) => (
                    <li key={p.id} className="grid gap-x-4 gap-y-0.5 px-5 py-3 text-sm sm:grid-cols-[7rem_minmax(0,1fr)_auto] sm:items-center">
                        <span className="text-brand-muted">{p.paid_label}</span>
                        <span className="min-w-0">
                            <span className="font-medium text-brand-text">{p.plan}</span>
                            {p.period_label && <span className="text-brand-muted"> · {p.period_label}</span>}
                            <span className="block truncate text-xs text-brand-muted">
                                {p.method}
                                {p.reference && ` · ${p.reference}`}
                            </span>
                            {(p.refund_label || p.disputed) && (
                                <span className="block text-xs font-medium text-amber-800">{[p.refund_label, p.disputed && "Disputed"].filter(Boolean).join(" · ")}</span>
                            )}
                        </span>
                        <span className={`font-semibold tabular-nums sm:text-right ${p.refund_label?.startsWith("Refunded") ? "text-brand-muted line-through" : "text-brand-text"}`}>{p.amount}</span>
                    </li>
                ))}
            </ul>
        </>
    );
}

export default function Plan({ shop, plans, renewFrom = null, payments = [] }) {
    const { viewAs } = usePage().props;
    const sub = shop.subscription;
    const left = remaining(sub);
    // Trial over, never paid: no current plan - pick one.
    const choosing = !sub.paid && sub.status === "expired";
    const current = choosing ? null : plans.find((p) => p.current) ?? null;
    const others = current ? plans.filter((p) => !p.current) : plans;

    const { pay, busyId, dialogs, error } = usePlanCheckout({ shopName: shop.name });
    const blocked = current ? actionBlocked(current, renewFrom) : null;

    if (choosing) {
        return (
            <OwnerLayout shop={shop} title="Billing & plan" description="Every plan is billed once a year.">
                {error && (
                    <p role="alert" className="mb-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
                        {error}
                    </p>
                )}
                <div role="status" className={`flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl p-4 ${TONE.urgent}`}>
                    <LuClock className="h-4 w-4 shrink-0" />
                    <p className="font-semibold">{left.detail}</p>
                    <p className="text-sm opacity-80">Choose a plan to keep going.{sub.paused && " Scanning is paused."}</p>
                </div>
                <h2 className="mb-3 mt-6 font-heading text-lg font-semibold text-brand-text">Choose a plan</h2>
                <PlanCards plans={others} onUpgrade={viewAs ? null : pay} busyId={busyId} renewFrom={renewFrom} />
                {dialogs}
            </OwnerLayout>
        );
    }

    return (
        <OwnerLayout shop={shop} title="Billing & plan" description="Every plan is billed once a year.">
            {error && (
                <p role="alert" className="mb-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {error}
                </p>
            )}

            {/* Current plan */}
            <section className="rounded-2xl border border-brand-accent bg-brand-card p-5 ring-1 ring-brand-accent sm:p-6">
                <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                    <div className="min-w-0">
                        <span className="rounded-full bg-brand-accent/10 px-2 py-0.5 text-xs font-medium text-brand-accent">Your plan</span>
                        <div className="mt-2 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <h2 className="font-heading text-2xl font-semibold text-brand-text">{current?.name ?? sub.plan ?? "No plan"}</h2>
                            {current?.price && (
                                <p>
                                    <span className="font-heading text-xl font-bold tabular-nums text-brand-text">{current.price}</span>
                                    <span className="text-sm text-brand-muted"> {current.period}</span>
                                </p>
                            )}
                        </div>
                        {current?.description && <p className="mt-1 text-sm text-brand-muted">{current.description}</p>}
                        {current?.features.length > 0 && (
                            <ul className="mt-3 grid gap-x-6 gap-y-1.5 text-sm text-brand-text sm:grid-cols-2">
                                {current.features.map((feature) => (
                                    <PlanFeature key={feature} text={feature} compact />
                                ))}
                            </ul>
                        )}
                    </div>

                    <div className={`flex flex-col justify-center rounded-xl p-4 ${TONE[left.tone]}`} role={left.tone === "ok" ? undefined : "status"}>
                        <p className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide opacity-70">
                            <LuClock className="h-3.5 w-3.5" /> {sub.status === "active" ? "Plan expires" : "Free trial"}
                        </p>
                        <p className="mt-1 font-heading text-2xl font-bold tabular-nums">{left.title}</p>
                        <p className="text-sm opacity-80">
                            {left.detail}
                            {sub.paused && " Scanning is paused."}
                        </p>
                        {!viewAs && current && (
                            <>
                                <button
                                    type="button"
                                    onClick={() => pay(current)}
                                    disabled={blocked !== null || busyId === current.id}
                                    title={blocked ?? undefined}
                                    className={`${upgradeButtonClass} mt-3`}
                                >
                                    {busyId === current.id ? "Opening…" : actionLabel(current)}
                                </button>
                                {current.action === "locked" && <p className="mt-1.5 text-center text-xs opacity-70">You can renew from {renewFrom}.</p>}
                            </>
                        )}
                    </div>
                </div>
            </section>

            {/* Other plans */}
            {others.length > 0 && (
                <>
                    <h2 className="mb-3 mt-8 font-heading text-lg font-semibold text-brand-text">{current ? "Other plans" : "Plans"}</h2>
                    <PlanCards plans={others} onUpgrade={viewAs ? null : pay} busyId={busyId} renewFrom={renewFrom} />
                </>
            )}

            <PaymentHistory payments={payments} />
            {dialogs}
        </OwnerLayout>
    );
}
