import { router, useForm } from "@inertiajs/react";
import { useState } from "react";
import {
    LuBot,
    LuCalendarClock,
    LuCircleCheck,
    LuCircleDot,
    LuFlag,
    LuListTodo,
    LuLoaderCircle,
    LuMessageSquare,
    LuPlus,
    LuSparkles,
    LuTag,
    LuTrash2,
    LuX,
} from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import { LeadBadge, LeadChips } from "@/Components/Dashboard/ShopNotesButton";
import { FieldError, inputClass, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";
import { leadStatus } from "@/lib/leadStatus";

/**
 * Admin → shop settings → Account, phase 2 (the light CRM): tags, follow-ups,
 * notes, the AI "Brief me" and the shop's timeline. Everything here is
 * admin-only and saved straight away.
 */

export const URGENCY = {
    overdue: "text-red-700",
    today: "font-semibold text-amber-700",
    soon: "text-brand-text",
    later: "text-brand-muted",
    none: "text-brand-muted",
};

/** YYYY-MM-DD `days` from today (local). */
const inDays = (days) => {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
};

export function TagsPanel({ shop, tags, suggestions }) {
    const [draft, setDraft] = useState("");
    const save = (next) => router.put(`/admin/shops/${shop.id}/tags`, { tags: next }, { preserveScroll: true, preserveState: true });
    const add = (tag) => {
        const clean = tag.trim();
        if (!clean || tags.some((t) => t.toLowerCase() === clean.toLowerCase())) return;
        save([...tags, clean]);
        setDraft("");
    };

    return (
        <Panel title="Tags" description="Short labels to sort and search shops by on the Shops page.">
            <div className="flex flex-wrap gap-1.5">
                {tags.length === 0 && <span className="text-sm text-brand-muted">No tags yet.</span>}
                {tags.map((tag) => (
                    <span key={tag} className="inline-flex items-center gap-1 rounded-full bg-brand-accent/10 py-1 pl-2.5 pr-1 text-xs font-medium text-brand-text">
                        <LuTag className="h-3 w-3 text-brand-accent" /> {tag}
                        <button type="button" onClick={() => save(tags.filter((t) => t !== tag))} aria-label={`Remove ${tag}`} className="rounded-full p-0.5 text-brand-muted hover:bg-brand-bg hover:text-brand-text">
                            <LuX className="h-3 w-3" />
                        </button>
                    </span>
                ))}
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    add(draft);
                }}
                className="mt-3 flex gap-2"
            >
                <input value={draft} onChange={(e) => setDraft(e.target.value)} maxLength={30} placeholder="Add a tag" className={inputClass} />
                <button type="submit" disabled={!draft.trim()} className={secondaryButton}>
                    <LuPlus className="h-4 w-4" />
                </button>
            </form>
            <div className="mt-2 flex flex-wrap gap-1.5">
                {suggestions
                    .filter((s) => !tags.some((t) => t.toLowerCase() === s.toLowerCase()))
                    .map((s) => (
                        <button key={s} type="button" onClick={() => add(s)} className="rounded-full border border-dashed border-brand-border px-2.5 py-1 text-xs text-brand-muted hover:border-brand-accent hover:text-brand-text">
                            + {s}
                        </button>
                    ))}
            </div>
        </Panel>
    );
}

export function TaskRow({ task, shopId, children = null }) {
    const [confirm, confirmDialog] = useConfirm();
    const toggle = () => router.put(`/admin/shops/${shopId}/tasks/${task.id}`, { done: !task.done }, { preserveScroll: true, preserveState: true });

    async function remove() {
        if (await confirm({ title: "Delete this follow-up?", message: task.title, confirmLabel: "Delete", danger: true })) {
            router.delete(`/admin/shops/${shopId}/tasks/${task.id}`, { preserveScroll: true, preserveState: true });
        }
    }

    return (
        <li className="flex items-start gap-3 py-2.5">
            <button
                type="button"
                onClick={toggle}
                aria-label={task.done ? "Mark as not done" : "Mark as done"}
                className={`mt-0.5 shrink-0 ${task.done ? "text-emerald-600" : "text-brand-muted hover:text-brand-accent"}`}
            >
                {task.done ? <LuCircleCheck className="h-5 w-5" /> : <LuCircleDot className="h-5 w-5" />}
            </button>
            <div className="min-w-0 flex-1">
                <p className={`text-sm ${task.done ? "text-brand-muted line-through" : "text-brand-text"}`}>{task.title}</p>
                <p className="text-xs">
                    {task.done ? (
                        <span className="text-brand-muted">Done {task.done_label}</span>
                    ) : task.due_label ? (
                        <span className={URGENCY[task.urgency]}>
                            {task.urgency === "overdue" ? "Overdue - " : task.urgency === "today" ? "Today - " : "Due "}
                            {task.due_label}
                        </span>
                    ) : (
                        <span className="text-brand-muted">No date</span>
                    )}
                </p>
                {children}
            </div>
            <button type="button" onClick={remove} aria-label="Delete follow-up" className="shrink-0 rounded-lg p-1 text-brand-muted hover:bg-red-500/10 hover:text-red-700">
                <LuTrash2 className="h-4 w-4" />
            </button>
            {confirmDialog}
        </li>
    );
}

export function TasksPanel({ shop, tasks }) {
    const form = useForm({ title: "", due_on: inDays(2) });
    const open = tasks.filter((t) => !t.done);
    const done = tasks.filter((t) => t.done);

    return (
        <Panel title="Follow-ups" description="Reminders for you; overdue and today's show on Follow-ups.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/admin/shops/${shop.id}/tasks`, { preserveScroll: true, onSuccess: () => form.reset("title") });
                }}
                noValidate
                className="space-y-2"
            >
                <input value={form.data.title} onChange={(e) => form.setData("title", e.target.value)} maxLength={200} placeholder="e.g. Call about the counter display" className={inputClass} />
                <div className="flex flex-wrap items-center gap-2">
                    <div className="w-44">
                        <input type="date" value={form.data.due_on} onChange={(e) => form.setData("due_on", e.target.value)} aria-label="Due" className={inputClass} />
                    </div>
                    {[
                        ["Tomorrow", 1],
                        ["In 3 days", 3],
                        ["Next week", 7],
                    ].map(([label, days]) => (
                        <button key={label} type="button" onClick={() => form.setData("due_on", inDays(days))} className="rounded-full border border-brand-border px-2.5 py-1 text-xs text-brand-muted hover:text-brand-text">
                            {label}
                        </button>
                    ))}
                    <button type="submit" disabled={form.processing || !form.data.title.trim()} className={`${primaryButton} ml-auto`}>
                        <LuPlus className="h-4 w-4" /> Add
                    </button>
                </div>
                <FieldError message={form.errors.title ?? form.errors.due_on} />
            </form>

            <ul className="mt-3 divide-y divide-brand-border">
                {open.length === 0 && <li className="py-3 text-sm text-brand-muted">Nothing to follow up.</li>}
                {open.map((task) => (
                    <TaskRow key={task.id} task={task} shopId={shop.id} />
                ))}
            </ul>
            {done.length > 0 && (
                <details className="mt-2">
                    <summary className="cursor-pointer text-xs font-medium text-brand-muted">Done ({done.length})</summary>
                    <ul className="divide-y divide-brand-border">
                        {done.map((task) => (
                            <TaskRow key={task.id} task={task} shopId={shop.id} />
                        ))}
                    </ul>
                </details>
            )}
        </Panel>
    );
}

export function NotesPanel({ shop, notes, lead = null }) {
    const form = useForm({ body: "", lead_status: lead });
    const [confirm, confirmDialog] = useConfirm();

    async function remove(note) {
        if (await confirm({ title: "Delete this note?", message: note.body.slice(0, 120), confirmLabel: "Delete", danger: true })) {
            router.delete(`/admin/shops/${shop.id}/notes/${note.id}`, { preserveScroll: true, preserveState: true });
        }
    }

    return (
        <Panel title="Notes" description="Calls, objections, what was agreed. Only admins see them; the AI reads the latest few.">
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/admin/shops/${shop.id}/notes`, { preserveScroll: true, onSuccess: () => form.reset("body") });
                }}
                noValidate
            >
                <textarea rows={3} maxLength={2000} value={form.data.body} onChange={(e) => form.setData("body", e.target.value)} placeholder="What happened? e.g. Called Sam - staff keep forgetting to scan; sending a QR sticker." className={`${inputClass} resize-y`} />
                <FieldError message={form.errors.body} />
                <div className="mt-2 flex flex-wrap items-center gap-3">
                    <LeadChips value={form.data.lead_status} onChange={(v) => form.setData("lead_status", v)} />
                    <button type="submit" disabled={form.processing || !form.data.body.trim()} className={`${primaryButton} ml-auto`}>
                        <LuMessageSquare className="h-4 w-4" /> Add note
                    </button>
                </div>
            </form>
            <ul className="mt-4 space-y-3">
                {notes.map((note) => (
                    <li key={note.id} className={`group rounded-xl bg-brand-bg p-3 ${leadStatus(note.lead_status) ? `border-l-2 ${leadStatus(note.lead_status).edge}` : ""}`}>
                        <p className="whitespace-pre-line break-words text-sm text-brand-text">{note.body}</p>
                        <div className="mt-1.5 flex items-center justify-between gap-2 text-xs text-brand-muted">
                            <span className="flex flex-wrap items-center gap-1.5">
                                <LeadBadge status={note.lead_status} />
                                {note.author ?? "Admin"} · {note.at_label}
                            </span>
                            <button type="button" onClick={() => remove(note)} className="rounded p-0.5 hover:text-red-700" aria-label="Delete note">
                                <LuTrash2 className="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </li>
                ))}
            </ul>
            {confirmDialog}
        </Panel>
    );
}

/** "Brief me": the AI's call-prep sheet, from the shop's figures, notes and history. Not saved. */
export function BriefMePanel({ shop }) {
    const [state, setState] = useState({ status: "idle" }); // idle | loading | done | error

    async function brief() {
        setState({ status: "loading" });
        try {
            const { data } = await window.axios.post(`/admin/shops/${shop.id}/brief`);
            setState({ status: "done", brief: data });
        } catch (error) {
            setState({ status: "error", message: error.response?.data?.message ?? "Couldn't prepare the brief right now." });
        }
    }

    const brief_ = state.brief;

    return (
        <Panel title="Before you call" description="AI reads this shop's figures, notes and history and prepares you. It changes nothing.">
            {state.status !== "done" && (
                <button type="button" onClick={brief} disabled={state.status === "loading"} className={primaryButton}>
                    {state.status === "loading" ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuSparkles className="h-4 w-4" />}
                    {state.status === "loading" ? "Preparing…" : "Brief me"}
                </button>
            )}
            {state.status === "error" && (
                <p role="alert" className="mt-2 text-sm text-red-700">
                    {state.message}
                </p>
            )}
            {state.status === "done" && (
                <div className="space-y-4 text-sm">
                    <p className="whitespace-pre-line text-brand-text">{brief_.summary}</p>
                    {[
                        ["Talking points", brief_.talking_points],
                        ["They may say", brief_.likely_objections],
                    ].map(
                        ([title, lines]) =>
                            lines.length > 0 && (
                                <div key={title}>
                                    <h3 className="text-xs font-semibold uppercase tracking-wide text-brand-muted">{title}</h3>
                                    <ul className="mt-1 list-disc space-y-1 pl-5 text-brand-text">
                                        {lines.map((line) => (
                                            <li key={line}>{line}</li>
                                        ))}
                                    </ul>
                                </div>
                            ),
                    )}
                    {brief_.next_step && (
                        <p className="flex items-start gap-2 rounded-xl bg-brand-accent/10 p-3 font-medium text-brand-text">
                            <LuFlag className="mt-0.5 h-4 w-4 shrink-0 text-brand-accent" /> {brief_.next_step}
                        </p>
                    )}
                    <button type="button" onClick={brief} className={secondaryButton}>
                        <LuBot className="h-4 w-4" /> Brief me again
                    </button>
                </div>
            )}
        </Panel>
    );
}

const KIND = {
    milestone: { icon: LuFlag, tone: "bg-emerald-500/15 text-emerald-700" },
    note: { icon: LuMessageSquare, tone: "bg-sky-500/15 text-sky-700" },
    task: { icon: LuListTodo, tone: "bg-violet-500/15 text-violet-700" },
    activity: { icon: LuCalendarClock, tone: "bg-brand-bg text-brand-muted" },
};

export function TimelinePanel({ timeline }) {
    const [showAll, setShowAll] = useState(false);
    const shown = showAll ? timeline : timeline.slice(0, 15);

    return (
        <Panel title="Timeline" description="Milestones, your notes and follow-ups, and what the owner, staff and admins did - newest first.">
            <ol className="relative space-y-4 border-l border-brand-border pl-6">
                {shown.map((entry, i) => {
                    const kind = KIND[entry.kind] ?? KIND.activity;
                    return (
                        <li key={`${entry.at}-${i}`} className="relative">
                            <span className={`absolute -left-[2.15rem] flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-brand-card ${kind.tone}`}>
                                <kind.icon className="h-3.5 w-3.5" />
                            </span>
                            <p className={`text-sm ${entry.kind === "milestone" ? "font-semibold text-brand-text" : "text-brand-text"}`}>{entry.title}</p>
                            {entry.detail && <p className="mt-0.5 whitespace-pre-line break-words text-sm text-brand-muted">{entry.detail}</p>}
                            <p className="mt-0.5 text-xs text-brand-muted">
                                {entry.at_label}
                                {entry.who && ` · ${entry.who}`}
                            </p>
                        </li>
                    );
                })}
            </ol>
            {timeline.length > 15 && (
                <button type="button" onClick={() => setShowAll(!showAll)} className={`${secondaryButton} mt-4`}>
                    {showAll ? "Show less" : `Show all ${timeline.length}`}
                </button>
            )}
        </Panel>
    );
}
