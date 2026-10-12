import { LuEllipsisVertical, LuShare, LuSquarePlus, LuX } from "react-icons/lu";

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
