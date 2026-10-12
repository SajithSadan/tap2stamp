import { useForm } from "@inertiajs/react";
import { LuCheck, LuCircleAlert, LuPlus } from "react-icons/lu";
import { FieldError, inputClass, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";

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

function PipelinePanel({ shop, pipeline, stages }) {
    const form = useForm({
        crm_stage: pipeline.crm_stage,
        trial_ends_at: pipeline.trial_ends_at ?? "",
        lost_reason: pipeline.lost_reason ?? "",
    });

    return (
        <Panel title="Sales pipeline" description="Where this shop is on the way to a paid plan. Nothing is charged or switched off from here yet.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put(`/admin/shops/${shop.id}/account`, { preserveScroll: true });
                }}
                noValidate
                className="space-y-5"
            >
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

export default function ShopAccountTab({ shop, health, healthModel, pipeline, crmStages }) {
    return (
        <div className="space-y-4">
            <HealthPanel health={health} model={healthModel} />
            <PipelinePanel shop={shop} pipeline={pipeline} stages={crmStages} />
        </div>
    );
}
