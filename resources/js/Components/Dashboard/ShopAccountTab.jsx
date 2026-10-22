import { useForm } from "@inertiajs/react";
import { LuCheck, LuCircleAlert, LuPlus } from "react-icons/lu";
import { FieldError, inputClass, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";
import { BriefMePanel, NotesPanel, TagsPanel, TasksPanel, TimelinePanel } from "@/Components/Dashboard/ShopCrmPanels";

/**
 * Admin → shop settings → Account (MONETISATION-PLAN.md phase 1): how the
 * shop is really doing (App\Services\ShopHealth - score, segment, the
 * activation ladder, reasons, the score's parts) and its place in the sales
 * pipeline (stage, trial end, why it was lost).
 */

const SEGMENT_TONE = {
    never_started: "bg-neutral-500/10 text-neutral-700",
    stalled: "bg-orange-500/10 text-orange-800",
    declining: "bg-red-500/10 text-red-700",
    trickling: "bg-amber-500/10 text-amber-800",
    healthy: "bg-emerald-500/10 text-emerald-700",
};

const PART_LABELS = {
    active_days: "Days stamping (last 14)",
    trend: "This week vs last",
    new_customers: "New customers (30 days)",
    return_rate: "Customers coming back",
    rewards: "Rewards given",
    staff: "Staff set up",
    display: "Display / QR in place",
    owner_login: "Owner signed in lately",
    extras: "Menu or reviews",
};

function HealthPanel({ health, model }) {
    const m = health.metrics;
    const figures = [
        ["Customers", m.customers],
        ["New in 30 days", m.new_customers_30d],
        ["Came back", m.return_rate === null ? "-" : `${m.returning} (${m.return_rate}%)`],
        ["Stamps this week", `${m.stamps_week} (last week ${m.stamps_prev_week})`],
        ["Rewards", `${m.rewards_total} (${m.rewards_30d} in 30 days)`],
        ["Staff / devices", `${m.staff} / ${m.devices}`],
    ];

    return (
        <Panel title="Health" description="Worked out from how the shop actually uses Tada Tap. Updates every time this page opens.">
            <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
                <div>
                    <div className="flex items-end gap-2">
                        <span className="font-heading text-5xl font-bold tabular-nums text-brand-text">{health.score}</span>
                        <span className="pb-1.5 text-sm text-brand-muted">/ 100</span>
                    </div>
                    <span className={`mt-2 inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${SEGMENT_TONE[health.segment]}`}>
                        {health.segment_label}
                    </span>
                    <ul className="mt-4 space-y-1.5 text-sm text-brand-text">
                        {health.reasons.map((reason) => (
                            <li key={reason} className="flex gap-2">
                                <LuCircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-brand-muted" />
                                {reason}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Activation ladder</h3>
                        <ol className="mt-2 space-y-1.5">
                            {Object.entries(model.ladder).map(([n, label]) => {
                                const done = Number(n) <= health.step;
                                const next = Number(n) === health.step + 1;
                                return (
                                    <li key={n} className={`flex items-center gap-2 text-sm ${done ? "text-brand-text" : next ? "font-semibold text-amber-800" : "text-brand-muted"}`}>
                                        <span
                                            className={`flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold ${
                                                done ? "bg-emerald-500 text-white" : next ? "bg-amber-500/15 text-amber-800 ring-1 ring-amber-500" : "bg-brand-bg text-brand-muted"
                                            }`}
                                        >
                                            {done ? <LuCheck className="h-3 w-3" /> : n}
                                        </span>
                                        {label}
                                        {next && <span className="text-xs font-normal">- next</span>}
                                    </li>
                                );
                            })}
                        </ol>
                    </div>

                    <div>
                        <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">Score</h3>
                        <ul className="mt-2 space-y-1.5">
                            {Object.entries(model.score).map(([part, max]) => {
                                const got = health.points[part] ?? 0;
                                return (
                                    <li key={part} className="text-sm">
                                        <div className="flex justify-between gap-2">
                                            <span className="text-brand-text">{PART_LABELS[part] ?? part}</span>
                                            <span className="tabular-nums text-brand-muted">
                                                {Math.round(got)} / {max}
                                            </span>
                                        </div>
                                        <span className="mt-0.5 block h-1 overflow-hidden rounded-full bg-brand-bg">
                                            <span className="block h-full rounded-full bg-brand-accent" style={{ width: `${(got / max) * 100}%` }} />
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </div>
            </div>

            <dl className="mt-6 grid grid-cols-2 gap-3 border-t border-brand-border pt-4 sm:grid-cols-3 lg:grid-cols-6">
                {figures.map(([label, value]) => (
                    <div key={label}>
                        <dt className="text-xs text-brand-muted">{label}</dt>
                        <dd className="text-sm font-semibold tabular-nums text-brand-text">{value}</dd>
                    </div>
                ))}
            </dl>
        </Panel>
    );
}

/** YYYY-MM-DD, `days` after `from` (or today). */
function addDays(from, days) {
    const date = from ? new Date(`${from}T12:00:00`) : new Date();
    date.setDate(date.getDate() + days);
    return date.toISOString().slice(0, 10);
}

const STATUS_TONE = {
    active: "bg-emerald-500/10 text-emerald-700",
    trial: "bg-sky-500/10 text-sky-700",
    expired: "bg-red-500/10 text-red-700",
};

/** "Paid until 3 Mar 2027" / "Trial · 12 days left" / "Trial ended 4 Oct 2026 · scanning paused". */
function statusText(sub) {
    if (sub.status === "active")
        return sub.trial_running ? `Paid · trial to ${sub.trial_ends_label}, then paid to ${sub.paid_until_label}` : `Paid until ${sub.paid_until_label}`;
    if (sub.status === "trial") {
        if (sub.trial_days_left === null) return "Free trial, no end date";
        return sub.trial_days_left === 0 ? "Free trial ends today" : `Free trial · ${sub.trial_days_left} days left`;
    }
    return `${sub.paid ? "Paid year" : "Free trial"} ended ${sub.ended_label}${sub.paused ? " · scanning paused" : ""}`;
}

function PipelinePanel({ shop, pipeline, stages, subscription, plans }) {
    const form = useForm({
        crm_stage: pipeline.crm_stage,
        trial_ends_at: pipeline.trial_ends_at ?? "",
        lost_reason: pipeline.lost_reason ?? "",
        plan_id: pipeline.plan_id ?? "",
        subscription_ends_at: pipeline.subscription_ends_at ?? "",
    });

    // Paid for a year: it starts when the free trial ends (paying during the trial keeps
    // the rest of it), or when the paid year still running ends (renewal), else today.
    // Mirrors Shop::paidYearStart().
    function markPaidYear() {
        const today = new Date().toISOString().slice(0, 10);
        const from = [today, form.data.trial_ends_at, form.data.subscription_ends_at].filter(Boolean).sort().at(-1);
        const date = new Date(`${from}T12:00:00`);
        date.setFullYear(date.getFullYear() + 1);
        form.setData((data) => ({ ...data, subscription_ends_at: date.toISOString().slice(0, 10), crm_stage: "paid" }));
    }

    return (
        <Panel title="Plan & pipeline" description="The shop's yearly plan, its trial and paid year, and where it is on the way to paying.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put(`/admin/shops/${shop.id}/account`, { preserveScroll: true });
                }}
                noValidate
                className="space-y-5"
            >
                <p className={`inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${STATUS_TONE[subscription.status]}`}>{statusText(subscription)}</p>

                <div>
                    <label htmlFor="plan_id" className="mb-1.5 block text-sm font-medium text-brand-text">
                        Plan
                    </label>
                    <select id="plan_id" value={form.data.plan_id} onChange={(e) => form.setData("plan_id", e.target.value)} className={inputClass}>
                        <option value="">No plan</option>
                        {plans.map((plan) => (
                            <option key={plan.id} value={plan.id}>
                                {plan.name}
                                {plan.is_default ? " (default)" : ""}
                                {plan.is_active ? "" : " - not offered"}
                            </option>
                        ))}
                    </select>
                    <FieldError message={form.errors.plan_id} />
                </div>

                <div>
                    <label htmlFor="subscription_ends_at" className="mb-1.5 block text-sm font-medium text-brand-text">
                        Paid until
                    </label>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="w-48">
                            <input
                                id="subscription_ends_at"
                                type="date"
                                value={form.data.subscription_ends_at}
                                onChange={(e) => form.setData("subscription_ends_at", e.target.value)}
                                className={inputClass}
                            />
                        </div>
                        <button type="button" onClick={markPaidYear} className={secondaryButton}>
                            <LuPlus className="h-4 w-4" /> Paid for a year
                        </button>
                    </div>
                    <p className="mt-1.5 text-xs text-brand-muted">Empty = hasn't paid yet. Paid during the free trial, the year starts when the trial ends. Payment is taken outside the app for now.</p>
                    <FieldError message={form.errors.subscription_ends_at} />
                </div>

                <div>
                    <span className="mb-1.5 block text-sm font-medium text-brand-text">Stage</span>
                    <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Stage">
                        {Object.entries(stages).map(([value, label]) => (
                            <button
                                key={value}
                                type="button"
                                role="radio"
                                aria-checked={form.data.crm_stage === value}
                                onClick={() => form.setData("crm_stage", value)}
                                className={`rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors ${
                                    form.data.crm_stage === value ? "bg-brand-deep text-white" : "bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border hover:text-brand-text"
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                    <p className="mt-1.5 text-xs text-brand-muted">A trial shop that has given its first stamp shows as "Activated" on the Shops page.</p>
                    <FieldError message={form.errors.crm_stage} />
                </div>

                <div>
                    <label htmlFor="trial_ends_at" className="mb-1.5 block text-sm font-medium text-brand-text">
                        Free trial ends
                    </label>
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="w-48">
                            <input
                                id="trial_ends_at"
                                type="date"
                                value={form.data.trial_ends_at}
                                onChange={(e) => form.setData("trial_ends_at", e.target.value)}
                                className={inputClass}
                            />
                        </div>
                        <button type="button" onClick={() => form.setData("trial_ends_at", addDays(form.data.trial_ends_at, 14))} className={secondaryButton}>
                            <LuPlus className="h-4 w-4" /> 14 days
                        </button>
                    </div>
                    <FieldError message={form.errors.trial_ends_at} />
                </div>

                {form.data.crm_stage === "lost" && (
                    <div>
                        <label htmlFor="lost_reason" className="mb-1.5 block text-sm font-medium text-brand-text">
                            Why was it lost?
                        </label>
                        <input
                            id="lost_reason"
                            type="text"
                            maxLength={255}
                            value={form.data.lost_reason}
                            onChange={(e) => form.setData("lost_reason", e.target.value)}
                            placeholder="e.g. Price, staff forgot to scan, closed down"
                            className={inputClass}
                        />
                        <FieldError message={form.errors.lost_reason} />
                    </div>
                )}

                <button type="submit" disabled={form.processing || !form.isDirty} className={primaryButton}>
                    {form.processing ? "Saving…" : "Save"}
                </button>
            </form>
        </Panel>
    );
}

export default function ShopAccountTab({ shop, health, healthModel, pipeline, crmStages, crm, subscription, plans }) {
    return (
        <div className="space-y-4">
            <HealthPanel health={health} model={healthModel} />
            <div className="grid items-start gap-4 lg:grid-cols-2">
                <div className="space-y-4">
                    {crm.ai && <BriefMePanel shop={shop} />}
                    <TasksPanel shop={shop} tasks={crm.tasks} />
                    <NotesPanel shop={shop} notes={crm.notes} />
                </div>
                <div className="space-y-4">
                    <PipelinePanel shop={shop} pipeline={pipeline} stages={crmStages} subscription={subscription} plans={plans} />
                    <TagsPanel shop={shop} tags={crm.tags} suggestions={crm.tag_suggestions} />
                </div>
            </div>
            <TimelinePanel timeline={crm.timeline} />
        </div>
    );
}
