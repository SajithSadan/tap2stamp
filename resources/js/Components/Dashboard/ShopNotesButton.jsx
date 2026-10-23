import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { LuLoaderCircle, LuMessageSquare, LuNotebookPen, LuNotebookText, LuTrash2, LuX } from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import { FieldError, inputClass, primaryButton } from "@/Components/Dashboard/Ui";
import { LEAD_STATUSES, leadStatus } from "@/lib/leadStatus";

/** A note's follow-up status as a small pill (nothing when it has none). */
export function LeadBadge({ status }) {
    const s = leadStatus(status);
    if (!s) return null;

    return <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold ${s.pill}`}>{s.label}</span>;
}

/** Pick the status a new note is written with; tapping the picked one again clears it. */
export function LeadChips({ value, onChange }) {
    return (
        <div className="flex flex-wrap items-center gap-1.5" role="radiogroup" aria-label="Follow-up status">
            {LEAD_STATUSES.map((s) => {
                const on = value === s.key;
                return (
                    <button
                        key={s.key}
                        type="button"
                        role="radio"
                        aria-checked={on}
                        onClick={() => onChange(on ? null : s.key)}
                        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold transition-colors ${on ? s.pill : "border-brand-border text-brand-muted hover:text-brand-text"}`}
                    >
                        <span className={`h-2 w-2 rounded-full ${s.dot}`} />
                        {s.label}
                    </button>
                );
            })}
        </div>
    );
}

/** One note: long ones show their first 4 lines, with "More" for the rest. */
function NoteItem({ note, onRemove }) {
    const ref = useRef(null);
    const [expanded, setExpanded] = useState(false);
    const [long, setLong] = useState(false);

    // Clamped text that still overflows = there's more to show.
    useLayoutEffect(() => {
        const el = ref.current;
        if (el && !expanded) setLong(el.scrollHeight > el.clientHeight + 1);
    }, [note.body, expanded]);

    const lead = leadStatus(note.lead_status);

    return (
        <li className={`py-3 ${lead ? `border-l-2 pl-3 ${lead.edge}` : ""}`}>
            <div className="flex items-center justify-between gap-2 text-xs text-brand-muted">
                <span className="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                    <LeadBadge status={note.lead_status} />
                    <span>
                        <span className="font-medium text-brand-text">{note.author ?? "Admin"}</span> · {note.at_label}
                    </span>
                </span>
                <button type="button" onClick={onRemove} className="rounded p-1 hover:text-red-700" aria-label="Delete note">
                    <LuTrash2 className="h-3.5 w-3.5" />
                </button>
            </div>
            <p ref={ref} className={`mt-1 whitespace-pre-line break-words text-sm leading-relaxed text-brand-text ${expanded ? "" : "line-clamp-4"}`}>
                {note.body}
            </p>
            {(long || expanded) && (
                <button type="button" onClick={() => setExpanded(!expanded)} className="mt-1 text-xs font-semibold text-brand-accent hover:underline">
                    {expanded ? "Less" : "More"}
                </button>
            )}
        </li>
    );
}

const HAS_NOTES = "bg-brand-accent/10 text-brand-accent hover:bg-brand-accent/20";
const NO_NOTES = "text-brand-muted hover:bg-brand-bg hover:text-brand-text";

/** Explains the two looks of the notes icon, shown under the Shops grid. */
export function NotesLegend() {
    return (
        <p className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-brand-muted">
            <span className="inline-flex items-center gap-1.5">
                <span className={`inline-flex h-6 w-6 items-center justify-center rounded-md ${HAS_NOTES}`}>
                    <LuNotebookText className="h-3.5 w-3.5" />
                </span>
                Has notes
            </span>
            <span className="inline-flex items-center gap-1.5">
                <span className="inline-flex h-6 w-6 items-center justify-center rounded-md text-brand-muted">
                    <LuNotebookPen className="h-3.5 w-3.5" />
                </span>
                No notes yet
            </span>
            {LEAD_STATUSES.map((s) => (
                <span key={s.key} className="inline-flex items-center gap-1.5">
                    <span className={`h-2.5 w-2.5 rounded-full ${s.dot}`} />
                    {s.label}
                </span>
            ))}
        </p>
    );
}

/**
 * Admin Shops grid: the notes icon (highlighted when the shop has notes). Opens a dialog
 * with the shop's notes, newest first, and a box to add one - the same notes as
 * shop settings → Account, through the same routes answering JSON
 * (GET / POST / DELETE /admin/shops/{shop}/notes), so the grid isn't reloaded.
 * A note can be written with a follow-up status (hot / warm / cold): it keeps it, and it
 * becomes the shop's status - `onLeadChange` tells the grid's Follow-up column.
 */
export default function ShopNotesButton({ shopId, shopName, count = 0, lead = null, onLeadChange }) {
    const [open, setOpen] = useState(false);
    const [notes, setNotes] = useState(null); // null = not loaded yet
    const [total, setTotal] = useState(count);
    const [noteLead, setNoteLead] = useState(lead);
    const [body, setBody] = useState("");
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [confirm, confirmDialog] = useConfirm();
    const base = `/admin/shops/${shopId}/notes`;

    useEffect(() => setTotal(count), [count]);

    useEffect(() => {
        if (!open) return;
        setError(null);
        setNoteLead(lead);
        window.axios
            .get(base)
            .then(({ data }) => show(data.notes))
            .catch(() => setError("Couldn't load the notes - try again."));
        const onKey = (e) => e.key === "Escape" && setOpen(false);
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [open]);

    function show(list) {
        setNotes(list);
        setTotal(list.length);
    }

    async function add(e) {
        e.preventDefault();
        if (!body.trim() || saving) return;
        setSaving(true);
        setError(null);
        try {
            const { data } = await window.axios.post(base, { body, lead_status: noteLead });
            show(data.notes);
            onLeadChange?.(data.lead_status);
            setBody("");
        } catch (err) {
            setError(err.response?.data?.errors?.body?.[0] ?? "Couldn't save the note - try again.");
        } finally {
            setSaving(false);
        }
    }

    async function remove(note) {
        if (!(await confirm({ title: "Delete this note?", message: note.body.slice(0, 120), confirmLabel: "Delete", danger: true }))) return;
        try {
            const { data } = await window.axios.delete(`${base}/${note.id}`);
            show(data.notes);
        } catch {
            setError("Couldn't delete the note - try again.");
        }
    }

    const label = `Notes on ${shopName}`;

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label={total ? `${label} (${total})` : label}
                title={total ? `Notes (${total})` : "Add a note"}
                className={`inline-flex h-8 w-8 items-center justify-center rounded-lg transition-colors ${total > 0 ? HAS_NOTES : NO_NOTES}`}
            >
                {total > 0 ? <LuNotebookText className="h-4 w-4" /> : <LuNotebookPen className="h-4 w-4" />}
            </button>

            {open &&
                createPortal(
                    <>
                        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 p-4 backdrop-blur-sm sm:items-center" onClick={() => setOpen(false)}>
                            <div
                                role="dialog"
                                aria-modal="true"
                                aria-label={label}
                                onClick={(e) => e.stopPropagation()}
                                className="flex max-h-[85vh] w-full max-w-2xl flex-col rounded-2xl bg-brand-card p-5 shadow-xl sm:p-6"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <h2 className="font-heading text-lg font-semibold text-brand-text">Notes</h2>
                                        <p className="truncate text-sm text-brand-muted">{shopName}</p>
                                    </div>
                                    <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                                        <LuX className="h-5 w-5" />
                                    </button>
                                </div>

                                <form onSubmit={add} noValidate className="mt-4">
                                    <textarea
                                        rows={3}
                                        maxLength={2000}
                                        value={body}
                                        onChange={(e) => setBody(e.target.value)}
                                        onKeyDown={(e) => (e.metaKey || e.ctrlKey) && e.key === "Enter" && add(e)}
                                        autoFocus
                                        placeholder="What happened? e.g. Called - staff keep forgetting to scan."
                                        className={`${inputClass} resize-y`}
                                    />
                                    <div className="mt-2 flex flex-wrap items-center justify-between gap-3">
                                        <LeadChips value={noteLead} onChange={setNoteLead} />
                                        <button type="submit" disabled={saving || !body.trim()} className={`${primaryButton} ml-auto shrink-0`}>
                                            {saving ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuMessageSquare className="h-4 w-4" />} Add note
                                        </button>
                                    </div>
                                    <FieldError message={error} />
                                </form>

                                <div className="-mx-1 mt-4 min-h-0 flex-1 overflow-y-auto px-1">
                                    {notes === null ? (
                                        !error && (
                                            <p className="flex items-center gap-2 py-3 text-sm text-brand-muted">
                                                <LuLoaderCircle className="h-4 w-4 animate-spin" /> Loading…
                                            </p>
                                        )
                                    ) : notes.length === 0 ? (
                                        <p className="py-3 text-sm text-brand-muted">No notes yet.</p>
                                    ) : (
                                        <ul className="divide-y divide-brand-border border-t border-brand-border">
                                            {notes.map((note) => (
                                                <NoteItem key={note.id} note={note} onRemove={() => remove(note)} />
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            </div>
                        </div>
                        {confirmDialog}
                    </>,
                    document.body,
                )}
        </>
    );
}
