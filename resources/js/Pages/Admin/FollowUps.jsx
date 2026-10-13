import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { LuCalendarClock, LuLoaderCircle, LuPlus, LuSparkles } from "react-icons/lu";
import AdminLayout from "@/Components/Dashboard/AdminLayout";
import { TaskRow } from "@/Components/Dashboard/ShopCrmPanels";
import { EmptyState, FieldError, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";
import WhatsAppButton from "@/Components/Dashboard/WhatsAppButton";

/**
 * Admin → Follow-ups (MONETISATION-PLAN.md phase 2): what to do today.
 * The AI weekly briefing (fixed rules pick the shops, Gemini writes why +
 * one action each), overdue / today's follow-ups, trials ending this week,
 * and the rest of the open follow-ups.
 */

const FLAG_TONE = {
    declining: "bg-red-500/10 text-red-700",
    trial_ending: "bg-amber-500/10 text-amber-800",
    ready_to_convert: "bg-emerald-500/10 text-emerald-700",
    stalled: "bg-orange-500/10 text-orange-800",
    never_started: "bg-neutral-500/10 text-neutral-700",
};

const PRIORITY_DOT = { high: "bg-red-500", medium: "bg-amber-500", low: "bg-neutral-400" };

const account = (shopId) => `/admin/shops/${shopId}/settings#account`;

function tomorrow() {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    return d.toISOString().slice(0, 10);
}

function Briefing({ briefing, flags, aiEnabled, candidates }) {
    const { errors } = usePage().props;
    const [busy, setBusy] = useState(false);
    const [added, setAdded] = useState({});

    function generate() {
        router.post("/admin/follow-ups/briefing", {}, { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) });
    }

    function addFollowUp(item) {
        router.post(`/admin/shops/${item.shop_id}/tasks`, { title: item.action.slice(0, 200), due_on: tomorrow() }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setAdded((a) => ({ ...a, [item.shop_id]: true })),
        });
    }

    return (
        <Panel
            title="This week's briefing"
            description="Fixed rules pick the shops that need you (declining, trial ending, ready to convert, stalled, never started); AI writes why and one action for each, from their real figures and your notes."
            action={
                aiEnabled && (
                    <button type="button" onClick={generate} disabled={busy} className={briefing ? secondaryButton : primaryButton}>
                        {busy ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuSparkles className="h-4 w-4" />}
                        {busy ? "Thinking…" : briefing ? "Refresh" : "Make the briefing"}
                    </button>
                )
            }
        >
            {!aiEnabled && <p className="text-sm text-brand-muted">Add GEMINI_API_KEY to .env to use the AI briefing.</p>}
            <FieldError message={errors?.briefing} />
            {aiEnabled && !briefing && (
                <p className="text-sm text-brand-muted">
                    {candidates} {candidates === 1 ? "shop needs" : "shops need"} a follow-up by the rules. Make the briefing to see why and what to do.
                </p>
            )}
            {briefing && (
                <>
                    <p className="text-xs text-brand-muted">
                        Made {briefing.generated_ago} ({briefing.generated_label}) by {briefing.generated_by}. Figures may have moved since - refresh any time.
                    </p>
                    {briefing.summary && <p className="mt-2 text-sm font-medium text-brand-text">{briefing.summary}</p>}
                    <ul className="mt-4 divide-y divide-brand-border">
                        {briefing.items.map((item) => (
                            <li key={item.shop_id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-start">
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className={`h-2 w-2 shrink-0 rounded-full ${PRIORITY_DOT[item.priority]}`} title={`${item.priority} priority`} />
                                        <Link href={account(item.shop_id)} className="font-semibold text-brand-text hover:underline">
                                            {item.shop_name}
                                        </Link>
                                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-medium ${FLAG_TONE[item.flag]}`}>{flags[item.flag]}</span>
                                        <span className="text-xs tabular-nums text-brand-muted">score {item.score}</span>
                                    </div>
                                    <p className="mt-1 text-sm text-brand-muted">{item.why}</p>
                                    <p className="mt-1 text-sm text-brand-text">
                                        <span className="font-medium">Do: </span>
                                        {item.action}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    disabled={added[item.shop_id]}
                                    onClick={() => addFollowUp(item)}
                                    className={`${secondaryButton} shrink-0 self-start`}
                                >
                                    <LuPlus className="h-4 w-4" /> {added[item.shop_id] ? "Added" : "Follow-up"}
                                </button>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </Panel>
    );
}

function TaskList({ tasks, empty }) {
    if (tasks.length === 0) return <p className="text-sm text-brand-muted">{empty}</p>;

    return (
        <ul className="divide-y divide-brand-border">
            {tasks.map((task) => (
                <TaskRow key={task.id} task={task} shopId={task.shop_id}>
                    <div className="mt-1 flex items-center gap-2">
                        <Link href={account(task.shop_id)} className="text-xs font-medium text-brand-accent hover:underline">
                            {task.shop_name}
                        </Link>
                        <WhatsAppButton number={task.whatsapp} name={task.contact_name ? `${task.contact_name} · ${task.shop_name}` : task.shop_name} shopId={task.shop_id} />
                    </div>
                </TaskRow>
            ))}
        </ul>
    );
}

export default function FollowUps({ today, upcoming, trials, briefing, flags, aiEnabled, candidates }) {
    return (
        <AdminLayout title="Follow-ups" description="Who to help, call or convert - today and this week.">
            <div className="space-y-4">
                <Briefing briefing={briefing} flags={flags} aiEnabled={aiEnabled} candidates={candidates} />

                <div className="grid items-start gap-4 lg:grid-cols-2">
                    <div className="space-y-4">
                        <Panel title={`Today${today.length ? ` (${today.length})` : ""}`} description="Overdue and due today.">
                            <TaskList tasks={today} empty="Nothing due - nice." />
                        </Panel>
                        <Panel title="Coming up" description="Every other open follow-up, soonest first.">
                            <TaskList tasks={upcoming} empty="No other follow-ups. Add them on a shop's Account tab." />
                        </Panel>
                    </div>

                    <Panel title="Trials ending" description="Ended in the last 3 days or ending in the next 8 - not paid or lost.">
                        {trials.length === 0 ? (
                            <EmptyState icon={LuCalendarClock} title="No trials ending this week" />
                        ) : (
                            <ul className="divide-y divide-brand-border">
                                {trials.map((shop) => (
                                    <li key={shop.id} className="flex items-center gap-3 py-2.5">
                                        <div className="min-w-0 flex-1">
                                            <Link href={account(shop.id)} className="text-sm font-semibold text-brand-text hover:underline">
                                                {shop.name}
                                            </Link>
                                            <p className={`text-xs ${shop.trial_days_left < 0 ? "text-red-700" : shop.trial_days_left <= 2 ? "font-medium text-amber-700" : "text-brand-muted"}`}>
                                                {shop.trial_ends_label} ·{" "}
                                                {shop.trial_days_left < 0
                                                    ? `ended ${-shop.trial_days_left} ${shop.trial_days_left === -1 ? "day" : "days"} ago`
                                                    : shop.trial_days_left === 0
                                                      ? "ends today"
                                                      : `${shop.trial_days_left} ${shop.trial_days_left === 1 ? "day" : "days"} left`}
                                            </p>
                                        </div>
                                        <WhatsAppButton number={shop.whatsapp} name={shop.contact_name ? `${shop.contact_name} · ${shop.name}` : shop.name} shopId={shop.id} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </div>
            </div>
        </AdminLayout>
    );
}
