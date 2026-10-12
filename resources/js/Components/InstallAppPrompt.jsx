import { useEffect, useState } from "react";
import { LuDownload, LuLoaderCircle } from "react-icons/lu";
import AddToHomeScreenSteps from "@/Components/AddToHomeScreenSteps";
import { isAndroid, isInstalledApp, isIos, snooze, snoozedSince, useBrowserInstall } from "@/lib/pwa";

const SNOOZE_DAYS = 14;

/**
 * "Add {app} to your home screen" for the staff and owner apps: Install
 * (Chrome's own prompt), the ⋮ menu steps on Android before Chrome offers it,
 * or the Share → Add to Home Screen steps on iPhone. Hidden once installed,
 * and on a desktop without Chrome's prompt.
 *
 * - `snoozeKey`: "Not now" hides it for 14 days (no key = always shown, no Not now).
 * - `prepareIos`: runs before the iPhone steps; may move to another address
 *   (the one Safari will save) and return true to stop here (see StaffApp).
 * - `autoOpenSteps`: open the iPhone steps straight away (back from prepareIos).
 */
export default function InstallAppPrompt({ title, subtitle, stepsTitle, stepsDone, snoozeKey = null, prepareIos = null, autoOpenSteps = false, className = "" }) {
    const { canInstall, promptInstall } = useBrowserInstall();
    const [hidden, setHidden] = useState(() => isInstalledApp() || (snoozeKey !== null && snoozedSince(snoozeKey, SNOOZE_DAYS)));
    const [steps, setSteps] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const ios = isIos();
    const android = !ios && isAndroid();

    useEffect(() => {
        if (autoOpenSteps && ios && !isInstalledApp()) setSteps(true);
    }, [autoOpenSteps, ios]);

    const stepsDialog = steps ? <AddToHomeScreenSteps title={stepsTitle} done={stepsDone} android={!ios} onClose={() => setSteps(false)} /> : null;

    if (hidden || (!ios && !android && !canInstall)) return stepsDialog;

    async function add() {
        setError(null);
        if (canInstall) {
            await promptInstall();
            return;
        }
        if (ios && prepareIos) {
            setBusy(true);
            try {
                if (await prepareIos()) return; // moving to the address to save
            } catch {
                setError("Couldn't get it ready - check the connection and try again.");
                return;
            } finally {
                setBusy(false);
            }
        }
        setSteps(true);
    }

    return (
        <>
            <div className={`rounded-2xl border border-brand-border bg-brand-card p-3.5 ${className}`}>
                <div className="flex items-center gap-3">
                    <img src="/icons/icon-192.png" alt="" className="h-11 w-11 shrink-0 rounded-xl" />
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-brand-text">{title}</p>
                        <p className="text-xs text-brand-muted">{subtitle}</p>
                    </div>
                    <div className="flex shrink-0 flex-col items-end gap-1">
                        <button
                            type="button"
                            onClick={add}
                            disabled={busy}
                            className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-brand-accent px-3.5 py-1.5 text-xs font-semibold text-brand-accent-text disabled:opacity-60"
                        >
                            {busy ? <LuLoaderCircle className="h-3.5 w-3.5 animate-spin" /> : <LuDownload className="h-3.5 w-3.5" />}
                            {canInstall ? "Install" : "Add"}
                        </button>
                        {snoozeKey && (
                            <button
                                type="button"
                                onClick={() => {
                                    snooze(snoozeKey);
                                    setHidden(true);
                                }}
                                className="text-[11px] font-medium text-brand-muted hover:text-brand-text"
                            >
                                Not now
                            </button>
                        )}
                    </div>
                </div>
                {error && (
                    <p role="alert" className="mt-2 text-xs text-red-700">
                        {error}
                    </p>
                )}
            </div>
            {stepsDialog}
        </>
    );
}
