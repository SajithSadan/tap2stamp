import { useEffect, useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { LuLoaderCircle, LuMessageSquare, LuNotebookPen, LuTrash2, LuX } from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import { FieldError, inputClass, primaryButton } from "@/Components/Dashboard/Ui";

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

    return (
        <li className="py-3">
            <div className="flex items-center justify-between gap-2 text-xs text-brand-muted">
                <span>
                    <span className="font-medium text-brand-text">{note.author ?? "Admin"}</span> · {note.at_label}
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

/**
 * Admin Shops grid: the notes icon (with the shop's note count). Opens a dialog
 * with the shop's notes, newest first, and a box to add one - the same notes as
 * shop settings → Account, through the same routes answering JSON
 * (GET / POST / DELETE /admin/shops/{shop}/notes), so the grid isn't reloaded.
 */
export default function ShopNotesButton({ shopId, shopName, count = 0 }) {
    const [open, setOpen] = useState(false);
    const [notes, setNotes] = useState(null); // null = not loaded yet
    const [total, setTotal] = useState(count);
    const [body, setBody] = useState("");
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [confirm, confirmDialog] = useConfirm();
    const base = `/admin/shops/${shopId}/notes`;

    useEffect(() => setTotal(count), [count]);

    useEffect(() => {
        if (!open) return;
        setError(null);
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
            const { data } = await window.axios.post(base, { body });
            show(data.notes);
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
                className="relative inline-flex h-8 w-8 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
            >
                <LuNotebookPen className="h-4 w-4" />
                {total > 0 && (
                    <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-accent px-1 text-[10px] font-semibold leading-none text-brand-accent-text tabular-nums">
                        {total > 99 ? "99+" : total}
                    </span>
                )}
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
                                    <div className="mt-2 flex items-start justify-between gap-3">
                                        <FieldError message={error} />
                                        <button type="submit" disabled={saving || !body.trim()} className={`${primaryButton} ml-auto shrink-0`}>
                                            {saving ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuMessageSquare className="h-4 w-4" />} Add note
                                        </button>
                                    </div>
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
