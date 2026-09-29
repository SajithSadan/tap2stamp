import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { LuCircleHelp, LuTriangleAlert } from 'react-icons/lu';

// In-app confirmation dialog - used instead of the browser's confirm()/prompt()
// boxes everywhere. Usage:
//
//   const [confirm, confirmDialog] = useConfirm();
//   if (!(await confirm({ title: 'Delete this?', message: '…', confirmLabel: 'Delete', danger: true }))) return;
//   ...
//   return <>{page}{confirmDialog}</>;
//
// `requireText: 'DELETE'` makes the person type that word before the button
// unlocks - for things that are hard to undo.

function ConfirmDialog({ title, message, confirmLabel = 'Confirm', cancelLabel = 'Cancel', danger = false, requireText = null, onConfirm, onCancel }) {
    const [typed, setTyped] = useState('');
    const titleId = useId();
    const messageId = useId();
    const confirmButton = useRef(null);
    const input = useRef(null);
    const unlocked = !requireText || typed.trim().toUpperCase() === requireText.toUpperCase();

    // Focus the input when typing is needed, otherwise the safe choice for danger (Cancel is next to it).
    useEffect(() => {
        (requireText ? input : confirmButton).current?.focus();
    }, [requireText]);

    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && onCancel();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onCancel]);

    const Icon = danger ? LuTriangleAlert : LuCircleHelp;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={onCancel}>
            <div
                role="alertdialog"
                aria-modal="true"
                aria-labelledby={titleId}
                aria-describedby={messageId}
                onClick={(e) => e.stopPropagation()}
                className="w-full rounded-t-2xl border border-brand-border bg-brand-card p-6 sm:max-w-md sm:rounded-2xl"
            >
                <div className="flex gap-4">
                    <span className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${danger ? 'bg-red-50 text-red-600' : 'bg-brand-accent/10 text-brand-accent'}`}>
                        <Icon className="h-5 w-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <h2 id={titleId} className="font-heading text-lg font-semibold text-brand-text">
                            {title}
                        </h2>
                        <div id={messageId} className="mt-1.5 whitespace-pre-line text-sm text-brand-muted">
                            {message}
                        </div>

                        {requireText && (
                            <div className="mt-4">
                                <label htmlFor={`${titleId}-type`} className="block text-sm font-medium text-brand-text">
                                    Type <span className="font-mono font-semibold">{requireText}</span> to confirm
                                </label>
                                <input
                                    ref={input}
                                    id={`${titleId}-type`}
                                    value={typed}
                                    onChange={(e) => setTyped(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && unlocked && onConfirm()}
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="mt-1.5 w-full rounded-xl border border-brand-border bg-brand-card px-3.5 py-2.5 font-mono text-sm uppercase text-brand-text outline-none focus:border-red-400 focus:ring-4 focus:ring-red-500/10"
                                />
                            </div>
                        )}
                    </div>
                </div>

                <div className="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        onClick={onCancel}
                        className="inline-flex items-center justify-center rounded-xl border border-brand-border bg-brand-card px-4 py-2.5 text-sm font-medium text-brand-text transition-colors hover:bg-brand-bg"
                    >
                        {cancelLabel}
                    </button>
                    <button
                        ref={confirmButton}
                        type="button"
                        onClick={onConfirm}
                        disabled={!unlocked}
                        className={`inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-40 ${
                            danger ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-brand-accent text-brand-accent-text hover:opacity-90'
                        }`}
                    >
                        {confirmLabel}
                    </button>
                </div>
            </div>
        </div>
    );
}

/**
 * `const [confirm, confirmDialog] = useConfirm()` - `confirm(options)` shows the
 * dialog and resolves to true/false; render `confirmDialog` somewhere in the page.
 */
export function useConfirm() {
    const [pending, setPending] = useState(null);

    const confirm = useCallback((options) => new Promise((resolve) => setPending({ options, resolve })), []);

    const settle = (result) => {
        pending?.resolve(result);
        setPending(null);
    };

    const dialog = pending ? <ConfirmDialog {...pending.options} onConfirm={() => settle(true)} onCancel={() => settle(false)} /> : null;

    return [confirm, dialog];
}
