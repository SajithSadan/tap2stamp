import { useState } from "react";
import { FaChrome } from "react-icons/fa6";
import { LuEllipsisVertical, LuLoaderCircle, LuShare, LuSquarePlus, LuX } from "react-icons/lu";
import { chromeIntentUrl } from "@/lib/pwa";

/**
 * The "Add from Chrome" dialog for Samsung Internet. `chromePath` returns
 * (or resolves to) the address on this site Chrome should open - one that
 * carries whatever identifies the app, since Chrome has its own storage.
 */
export function useChromeInstall({ title, done, chromePath }) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    async function openChrome() {
        setError(null);
        setBusy(true);
        try {
            window.location.href = chromeIntentUrl(await chromePath());
        } catch {
            setError("Couldn't get it ready - check the connection and try again.");
        } finally {
            setBusy(false);
        }
    }

    const dialog = open ? <ChromeInstallSteps title={title} done={done} onOpen={openChrome} onClose={() => setOpen(false)} busy={busy} error={error} /> : null;

    return { show: () => setOpen(true), dialog };
}

/**
 * Samsung Internet: add the app from Chrome instead. Samsung's own Install
 * makes Google Play Protect block it as an "unsafe app" (lib/pwa.js).
 * `onOpen` moves to Chrome (it may fetch the address first: `busy`, `error`).
 */
export function ChromeInstallSteps({ title, done, onOpen, onClose, busy = false, error = null }) {
    return (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/60 p-4 sm:items-center" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                onClick={(e) => e.stopPropagation()}
                className="w-full max-w-sm rounded-3xl bg-brand-card p-6 shadow-2xl"
            >
                <div className="flex items-start justify-between gap-3">
                    <h2 className="font-heading text-xl font-bold text-brand-text">{title}</h2>
                    <button type="button" onClick={onClose} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                        <LuX className="h-5 w-5" />
                    </button>
                </div>
                <p className="mt-2 text-sm text-brand-muted">Samsung Internet's Install shows an "unsafe app" warning. Add it from Chrome instead - same app, no warning.</p>
                <ol className="mt-5 space-y-4 text-sm text-brand-text">
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg">
                            <FaChrome className="h-5 w-5 text-brand-text" />
                        </span>
                        <span>
                            Tap <strong>Open in Chrome</strong> below.
                        </span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg">
                            <LuSquarePlus className="h-5 w-5 text-brand-text" />
                        </span>
                        <span>
                            In Chrome, tap <strong>Install</strong> (or <strong>⋮</strong> → <strong>Add to Home screen</strong>). {done}
                        </span>
                    </li>
                </ol>
                {error && (
                    <p role="alert" className="mt-4 text-xs text-red-700">
                        {error}
                    </p>
                )}
                <button
                    type="button"
                    onClick={onOpen}
                    disabled={busy}
                    className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-brand bg-brand-accent px-5 py-3 font-semibold text-brand-accent-text disabled:opacity-60"
                >
                    {busy ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <FaChrome className="h-4 w-4" />} Open in Chrome
                </button>
            </div>
        </div>
    );
}

/**
 * The taps to add an app to the home screen by hand: iPhone (Safari's Share
 * icon) or Android when Chrome hasn't offered its own Install yet (the ⋮
 * browser menu). `title` names the app; `done` ends the last step.
 */
export default function AddToHomeScreenSteps({ title, done, onClose, android = false }) {
    return (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/60 p-4 sm:items-center" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label={title}
                onClick={(e) => e.stopPropagation()}
                className="w-full max-w-sm rounded-3xl bg-brand-card p-6 shadow-2xl"
            >
                <div className="flex items-start justify-between gap-3">
                    <h2 className="font-heading text-xl font-bold text-brand-text">{title}</h2>
                    <button type="button" onClick={onClose} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                        <LuX className="h-5 w-5" />
                    </button>
                </div>
                <ol className="mt-5 space-y-4 text-sm text-brand-text">
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg">
                            {android ? <LuEllipsisVertical className="h-5 w-5 text-brand-text" /> : <LuShare className="h-5 w-5 text-[#007aff]" />}
                        </span>
                        <span>
                            {android ? (
                                <>
                                    Tap the <strong>⋮ menu</strong> at the top right of your browser.
                                </>
                            ) : (
                                <>
                                    Tap <strong>Share</strong> in your browser's toolbar.
                                </>
                            )}
                        </span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg">
                            <LuSquarePlus className="h-5 w-5 text-brand-text" />
                        </span>
                        <span>
                            {android ? (
                                <>
                                    Tap <strong>Add to Home screen</strong> (or <strong>Install app</strong>).
                                </>
                            ) : (
                                <>
                                    Scroll down and tap <strong>Add to Home Screen</strong>.
                                </>
                            )}
                        </span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg text-sm font-bold ${android ? "text-brand-accent" : "text-[#007aff]"}`}>
                            {android ? "OK" : "Add"}
                        </span>
                        <span>
                            {android ? (
                                <>
                                    Confirm with <strong>Add</strong> or <strong>Install</strong>. {done}
                                </>
                            ) : (
                                <>
                                    Tap <strong>Add</strong>. {done}
                                </>
                            )}
                        </span>
                    </li>
                </ol>
                <button type="button" onClick={onClose} className="mt-6 w-full rounded-brand bg-brand-accent px-5 py-3 font-semibold text-brand-accent-text">
                    Got it
                </button>
            </div>
        </div>
    );
}
