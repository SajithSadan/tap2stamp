import PlanFeature from "@/Components/PlanFeature";

/** The button's words, from the plan's `action` (Plan::buttonAction()); "locked" = already paid, Renew disabled. */
export const actionLabel = (plan) =>
    ({ pay: "Pay now", renew: "Renew", locked: "Renew", upgrade: "Upgrade" })[plan.action] ?? `Switch to ${plan.name}`;

/** Why a plan's button is greyed out (null = it isn't). */
export const actionBlocked = (plan, renewFrom) =>
    plan.action === "locked" ? `Already paid. You can renew from ${renewFrom}.` : plan.payable ? null : "Online payment isn't available for this plan yet";

export const upgradeButtonClass =
    "flex w-full items-center justify-center gap-2 rounded-xl bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50";

/**
 * The yearly plans as owners see them (Dashboard/Plan.jsx), also drawn by
 * the admin's preview (Admin/Plans/Preview.jsx). Each plan: {id, name,
 * description, features, period, price (one currency's label, null = ask us),
 * current, payable, action}. With `onUpgrade`, each card gets a button (Upgrade
 * / Pay now / Renew / Switch to …, by `action`) that opens the payment window
 * (greyed out while the plan can't be paid online, or is already paid up);
 * without it (the preview), no button.
 */
export default function PlanCards({ plans, onUpgrade = null, busyId = null, renewFrom = null }) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {plans.map((plan) => (
                <section
                    key={plan.id}
                    className={`flex min-w-0 flex-col rounded-2xl border bg-brand-card p-5 ${plan.current ? "border-brand-accent ring-1 ring-brand-accent" : "border-brand-border"}`}
                >
                    <div className="flex items-start justify-between gap-2">
                        <h2 className="font-heading text-lg font-semibold text-brand-text">{plan.name}</h2>
                        {plan.current && (
                            <span className="shrink-0 rounded-full bg-brand-accent/10 px-2 py-0.5 text-xs font-medium text-brand-accent">Your plan</span>
                        )}
                    </div>
                    {plan.description && <p className="mt-1 text-sm text-brand-muted">{plan.description}</p>}
                    <p className="mt-3">
                        {plan.price ? (
                            <>
                                <span className="font-heading text-2xl font-bold tabular-nums text-brand-text">{plan.price}</span>
                                <span className="text-sm text-brand-muted"> {plan.period}</span>
                            </>
                        ) : (
                            <span className="text-sm text-brand-muted">Ask us for the price</span>
                        )}
                    </p>

                    {plan.features.length > 0 && (
                        <ul className="mt-3 space-y-1.5 text-sm text-brand-text">
                            {plan.features.map((feature) => (
                                <PlanFeature key={feature} text={feature} compact />
                            ))}
                        </ul>
                    )}

                    {onUpgrade && (
                        <div className="mt-auto pt-4">
                            <button
                                type="button"
                                onClick={() => onUpgrade(plan)}
                                disabled={actionBlocked(plan, renewFrom) !== null || busyId === plan.id}
                                title={actionBlocked(plan, renewFrom) ?? undefined}
                                className={upgradeButtonClass}
                            >
                                {busyId === plan.id ? "Opening…" : actionLabel(plan)}
                            </button>
                        </div>
                    )}
                </section>
            ))}
        </div>
    );
}
