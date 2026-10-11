import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { LuActivity, LuCircleAlert, LuCoins, LuGauge, LuSparkles, LuStore } from "react-icons/lu";
import { BarList } from "@/Components/Dashboard/Charts";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import { EmptyState, Panel, StatTile } from "@/Components/Dashboard/Ui";
import { formatNumber } from "@/lib/charts";

const ROLE = {
    staff: "bg-sky-500/10 text-sky-700",
    owner: "bg-emerald-500/10 text-emerald-700",
    admin: "bg-violet-500/10 text-violet-700",
};
const STATUS = {
    ok: "text-emerald-700",
    busy: "text-amber-700",
    failed: "text-red-600",
    unreachable: "text-red-600",
};

function Role({ type }) {
    if (!type) return <span className="text-xs text-brand-muted">-</span>;

    return <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold capitalize ${ROLE[type] ?? ""}`}>{type}</span>;
}

const th = "px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-brand-muted";
const td = "px-4 py-2.5 text-sm text-brand-text";
const num = `${td} whitespace-nowrap text-right tabular-nums`;

/** used / limit as a bar: green, amber from 50 %, red from 80 % (where Google starts refusing). */
function Meter({ label, used, limit, peak, peakLabel }) {
    const pct = limit ? Math.min(100, (used / limit) * 100) : 0;
    const peakPct = limit ? Math.min(100, (peak / limit) * 100) : 0;
    const tone = pct >= 80 ? "bg-red-500" : pct >= 50 ? "bg-amber-500" : "bg-emerald-500";

    return (
        <div>
            <div className="flex items-baseline justify-between gap-2">
                <span className="text-xs font-medium text-brand-muted">{label}</span>
                <span className="text-sm tabular-nums text-brand-text">
                    <strong>{formatNumber(used)}</strong> <span className="text-brand-muted">/ {formatNumber(limit)}</span>
                </span>
            </div>
            <div
                className="relative mt-1.5 h-2.5 overflow-hidden rounded-full bg-brand-bg ring-1 ring-inset ring-brand-border"
                role="meter"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={limit}
                aria-valuenow={used}
            >
                <div className={`h-full rounded-full transition-all duration-500 ${tone}`} style={{ width: `${Math.max(pct, used > 0 ? 1.5 : 0)}%` }} />
                {/* The busiest moment of the period, as a tick. */}
                {peak > 0 && <span className="absolute inset-y-0 w-0.5 bg-brand-text/40" style={{ left: `${Math.max(peakPct, 0.5)}%` }} title={`Peak ${formatNumber(peak)}`} />}
            </div>
            <p className="mt-1 flex justify-between text-[11px] text-brand-muted tabular-nums">
                <span>{formatNumber(Math.max(limit - used, 0))} left</span>
                <span>
                    Peak {formatNumber(peak)} {peakLabel}
                </span>
            </p>
        </div>
    );
}

/** Our Gemini plan's limits per model, now vs the limit (refreshed every 30 s). */
function LimitsPanel({ limits }) {
    const [refreshing, setRefreshing] = useState(false);

    useEffect(() => {
        const timer = setInterval(() => {
            if (document.visibilityState !== "visible") return;
            router.reload({ only: ["limits"], onStart: () => setRefreshing(true), onFinish: () => setRefreshing(false) });
        }, 30000);
        return () => clearInterval(timer);
    }, []);

    const peakLabel = `in ${limits.peakDays} days`;

    return (
        <Panel
            title={
                <span className="flex items-center gap-2">
                    <LuGauge className="h-5 w-5 text-brand-accent" /> Gemini plan limits
                    <span className="rounded-full bg-violet-500/10 px-2 py-0.5 text-xs font-semibold text-violet-700">{limits.tier}</span>
                    {refreshing && <span className="h-3 w-3 animate-spin rounded-full border-2 border-brand-border border-t-brand-accent" aria-label="Updating" />}
                </span>
            }
            description={`Used vs left, per model - updates every 30 s. Requests per day reset at midnight Pacific time (${limits.resets} here).`}
        >
            {limits.models.length === 0 ? (
                <p className="text-sm text-brand-muted">No Gemini model configured.</p>
            ) : (
                <div className="divide-y divide-brand-border">
                    {limits.models.map((m) => (
                        <div key={m.model} className="grid gap-4 py-4 first:pt-0 last:pb-0 lg:grid-cols-[12rem_1fr_1fr_1fr]">
                            <p className="self-center font-mono text-sm font-semibold text-brand-text">{m.model}</p>
                            <Meter label="Requests / minute" used={m.rpm} limit={limits.rpm} peak={m.peak_rpm} peakLabel={peakLabel} />
                            <Meter label="Input tokens / minute" used={m.tpm} limit={limits.tpm} peak={m.peak_tpm} peakLabel={peakLabel} />
                            <Meter label="Requests today" used={m.rpd} limit={limits.rpd} peak={m.peak_rpd} peakLabel={peakLabel} />
                        </div>
                    ))}
                </div>
            )}
        </Panel>
    );
}

/** Who uses the AI and how much (App\Http\Controllers\Admin\AiUsageController). */
export default function AiUsage({ limits, days, periods, features, totals, byFeature, byShop, byPerson, recent }) {
    const [busy, setBusy] = useState(false);

    function pick(value) {
        router.get("/admin/ai-usage", { days: value }, { preserveScroll: true, preserveState: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    }

    const empty = totals.requests === 0;

    return (
        <AdminLayout
            title="AI usage"
            description="Every Gemini request: menu photo import, photo checks and stock-out commands. Tokens are what Gemini reported - the basis of the bill."
            actions={
                <div className="flex items-center gap-2">
                    {busy && <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-brand-border border-t-brand-accent" aria-label="Updating" />}
                    <div className="flex rounded-lg bg-brand-bg p-0.5 ring-1 ring-brand-border" role="group" aria-label="Period">
                        {periods.map((p) => (
                            <button
                                key={p}
                                type="button"
                                onClick={() => p !== days && pick(p)}
                                aria-pressed={days === p}
                                className={`rounded-md px-2.5 py-1 text-xs font-semibold tabular-nums ${days === p ? "bg-brand-card text-brand-text shadow-sm" : "text-brand-muted hover:text-brand-text"}`}
                            >
                                {p === 0 ? "All" : `${p}D`}
                            </button>
                        ))}
                    </div>
                </div>
            }
        >
            <LimitsPanel limits={limits} />

            <div className="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatTile compact icon={LuSparkles} label="Requests" value={formatNumber(totals.requests)} />
                <StatTile compact icon={LuCoins} label="Tokens" value={formatNumber(totals.tokens)} />
                <StatTile compact icon={LuStore} label="Shops using AI" value={formatNumber(totals.shops)} />
                <StatTile compact icon={LuCircleAlert} label="Failed / busy" value={formatNumber(totals.failed)} />
            </div>

            {empty ? (
                <Panel className="mt-4">
                    <EmptyState icon={LuSparkles} title="No AI requests in this period">
                        They're recorded from now on: menu photo import, menu photo checks and stock-out commands.
                    </EmptyState>
                </Panel>
            ) : (
                <div className="mt-4 grid gap-4 lg:grid-cols-3">
                    <Panel title="By feature" description="Requests per AI feature.">
                        <BarList items={byFeature.map((f) => ({ label: features[f.feature] ?? f.feature, value: f.requests }))} of={totals.requests} />
                        <ul className="mt-3 space-y-1 text-xs text-brand-muted">
                            {byFeature.map((f) => (
                                <li key={f.feature} className="flex justify-between gap-3">
                                    <span>{features[f.feature] ?? f.feature}</span>
                                    <span className="tabular-nums">{formatNumber(f.tokens)} tokens</span>
                                </li>
                            ))}
                        </ul>
                    </Panel>

                    <Panel className="lg:col-span-2" title="Who uses it most" description="Each staff member, owner and admin." bodyClassName="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-brand-border">
                                <tr>
                                    <th className={th}>Person</th>
                                    <th className={th}>Shop</th>
                                    <th className={`${th} text-right`}>Requests</th>
                                    <th className={`${th} text-right`}>Tokens</th>
                                    <th className={`${th} text-right`}>Last used</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-brand-border">
                                {byPerson.map((p, i) => (
                                    <tr key={`${p.actor_type}-${p.user_id}-${p.staff_member_id}-${i}`}>
                                        <td className={td}>
                                            <span className="flex items-center gap-2">
                                                <Role type={p.actor_type} />
                                                <span className="truncate">{p.actor_name ?? "Not signed in"}</span>
                                            </span>
                                        </td>
                                        <td className={`${td} text-brand-muted`}>{p.actor_type === "admin" ? "-" : (p.shop_name ?? "-")}</td>
                                        <td className={num}>{formatNumber(p.requests)}</td>
                                        <td className={num}>{formatNumber(p.tokens)}</td>
                                        <td className={`${num} text-brand-muted`}>{p.last_at?.slice(0, 10)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Panel>

                    <Panel className="lg:col-span-3" title="By shop" description="Requests per feature, most tokens first." bodyClassName="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-brand-border">
                                <tr>
                                    <th className={th}>Shop</th>
                                    {Object.entries(features).map(([key, label]) => (
                                        <th key={key} className={`${th} text-right`}>
                                            {label}
                                        </th>
                                    ))}
                                    <th className={`${th} text-right`}>Requests</th>
                                    <th className={`${th} text-right`}>Tokens</th>
                                    <th className={`${th} text-right`}>Last used</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-brand-border">
                                {byShop.map((s) => (
                                    <tr key={s.shop_id ?? "none"}>
                                        <td className={td}>
                                            {s.shop_id ? (
                                                <a href={`/admin/shops/${s.shop_id}/settings`} className="font-medium hover:text-brand-accent">
                                                    {s.shop_name ?? `Shop #${s.shop_id}`}
                                                </a>
                                            ) : (
                                                <span className="text-brand-muted">No shop</span>
                                            )}
                                        </td>
                                        {Object.keys(features).map((key) => (
                                            <td key={key} className={`${num} text-brand-muted`}>
                                                {s[key] ? formatNumber(s[key]) : "-"}
                                            </td>
                                        ))}
                                        <td className={num}>{formatNumber(s.requests)}</td>
                                        <td className={`${num} font-semibold`}>{formatNumber(s.tokens)}</td>
                                        <td className={`${num} text-brand-muted`}>{s.last_at?.slice(0, 10)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Panel>

                    <Panel className="lg:col-span-3" title="Latest requests" description="The last 50." bodyClassName="overflow-x-auto">
                        <table className="w-full">
                            <thead className="border-b border-brand-border">
                                <tr>
                                    <th className={th}>When</th>
                                    <th className={th}>Feature</th>
                                    <th className={th}>Who</th>
                                    <th className={th}>Shop</th>
                                    <th className={th}>Result</th>
                                    <th className={`${th} text-right`}>Tokens</th>
                                    <th className={`${th} text-right`}>Time</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-brand-border">
                                {recent.map((r) => (
                                    <tr key={r.id}>
                                        <td className={`${td} whitespace-nowrap text-brand-muted`}>{r.at}</td>
                                        <td className={`${td} whitespace-nowrap`}>{features[r.feature] ?? r.feature}</td>
                                        <td className={td}>
                                            <span className="flex items-center gap-2">
                                                <Role type={r.actor_type} />
                                                <span className="truncate">{r.actor_name ?? "-"}</span>
                                            </span>
                                        </td>
                                        <td className={`${td} text-brand-muted`}>{r.shop_name ?? "-"}</td>
                                        <td className={`${td} whitespace-nowrap`} title={r.model ?? undefined}>
                                            <span className={`inline-flex items-center gap-1 font-medium capitalize ${STATUS[r.status] ?? ""}`}>
                                                <LuActivity className="h-3.5 w-3.5" /> {r.status}
                                            </span>
                                        </td>
                                        <td className={num}>{formatNumber(r.tokens)}</td>
                                        <td className={`${num} text-brand-muted`}>{(r.duration_ms / 1000).toFixed(1)}s</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Panel>
                </div>
            )}
        </AdminLayout>
    );
}
