import { Head } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { LuDownload, LuShare, LuSquarePlus, LuX } from "react-icons/lu";
import { CUSTOMER_UUID_KEY } from "@/lib/storage";

/**
 * The customer's "My Cards" home-screen app (Add to Home Screen / Install),
 * so they can check their stamps from home without hunting for a link.
 *
 * iPhone notes (why it works this way):
 * - iPhones never show an install prompt: the customer taps Share → Add to
 *   Home Screen, so we show those steps instead of an Install button.
 * - An iPhone home-screen app does NOT share Safari's storage, so the app's
 *   link carries the customer's card id (manifest start_url, and the page
 *   URL when they add it): /my-cards?c={uuid}, adopted by adoptLinkedCustomer().
 * - Safari clears a site's storage after ~7 days unused; home-screen apps
 *   are exempt.
 */

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const DISMISSED_KEY = "my_cards_install_dismissed_at";
const SNOOZE_DAYS = 14;

/** Running as the installed home-screen app (Android or iPhone). */
export const isInstalledApp = () =>
    typeof window !== "undefined" && (window.matchMedia?.("(display-mode: standalone)").matches || window.navigator.standalone === true);

const isIos = () =>
    typeof navigator !== "undefined" &&
    (/iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1));

/**
 * /my-cards?c={uuid} - the installed app's first launch (its storage starts
 * empty on iPhone). Only adopted when nothing is stored, so opening someone
 * else's link can never replace a customer's own card.
 */
export function adoptLinkedCustomer() {
    try {
        const linked = new URLSearchParams(window.location.search).get("c");
        if (linked && UUID.test(linked) && !window.localStorage.getItem(CUSTOMER_UUID_KEY)) {
            window.localStorage.setItem(CUSTOMER_UUID_KEY, linked);
        }
    } catch {
        // Storage blocked - the page just shows its empty state.
    }
}

// Chrome / Android fire this once, possibly before any React component mounts - keep it.
let deferredInstall = null;
const installListeners = new Set();
if (typeof window !== "undefined") {
    window.addEventListener("beforeinstallprompt", (event) => {
        event.preventDefault();
        deferredInstall = event;
        installListeners.forEach((fn) => fn());
    });
}

/** The head tags that make the page installable as this customer's My Cards app. */
export function CustomerAppHead({ uuid }) {
    useEffect(() => {
        if (uuid && "serviceWorker" in navigator) navigator.serviceWorker.register("/sw.js").catch(() => {});
    }, [uuid]);

    if (!uuid) return null;

    return (
        <Head>
            <link rel="manifest" href={`/my-cards/app/${uuid}.webmanifest`} />
            <meta name="apple-mobile-web-app-capable" content="yes" />
            <meta name="apple-mobile-web-app-title" content="My Cards" />
            <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        </Head>
    );
}

function snoozed() {
    try {
        const at = Number(window.localStorage.getItem(DISMISSED_KEY));
        return at && Date.now() - at < SNOOZE_DAYS * 86400000;
    } catch {
        return false;
    }
}

/** iPhone: the three taps, with Safari's own Share icon. */
function IosSteps({ onClose }) {
    return (
        <div className="fixed inset-0 z-40 flex items-end justify-center bg-black/60 p-4 sm:items-center" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Add My Cards to your home screen"
                onClick={(e) => e.stopPropagation()}
                className="w-full max-w-sm rounded-3xl bg-brand-card p-6 shadow-2xl"
            >
                <div className="flex items-start justify-between gap-3">
                    <h2 className="font-heading text-xl font-bold text-brand-text">Add My Cards to your home screen</h2>
                    <button type="button" onClick={onClose} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                        <LuX className="h-5 w-5" />
                    </button>
                </div>
                <ol className="mt-5 space-y-4 text-sm text-brand-text">
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg">
                            <LuShare className="h-5 w-5 text-[#007aff]" />
                        </span>
                        <span>
                            Tap <strong>Share</strong> in your browser's toolbar.
                        </span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg">
                            <LuSquarePlus className="h-5 w-5 text-brand-text" />
                        </span>
                        <span>
                            Scroll down and tap <strong>Add to Home Screen</strong>.
                        </span>
                    </li>
                    <li className="flex items-center gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-bg text-sm font-bold text-[#007aff]">Add</span>
                        <span>
                            Tap <strong>Add</strong>. Your cards are then one tap away.
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

/**
 * "Add My Cards to your home screen": an Install button where the browser
 * offers one (Android / Chrome), the Share steps on iPhone, nothing
 * elsewhere or when already installed. "Not now" snoozes it for 14 days.
 * `autoOpenSteps`: open the iPhone steps straight away (?add=1).
 */
export function InstallPrompt({ uuid, autoOpenSteps = false }) {
    const [canInstall, setCanInstall] = useState(Boolean(deferredInstall));
    const [hidden, setHidden] = useState(() => isInstalledApp() || snoozed());
    const [steps, setSteps] = useState(false);
    const ios = isIos();

    useEffect(() => {
        const update = () => setCanInstall(Boolean(deferredInstall));
        installListeners.add(update);
        return () => installListeners.delete(update);
    }, []);

    useEffect(() => {
        if (autoOpenSteps && ios && !isInstalledApp()) setSteps(true);
    }, [autoOpenSteps, ios]);

    if (!uuid || hidden || (!ios && !canInstall)) return steps ? <IosSteps onClose={() => setSteps(false)} /> : null;

    function notNow() {
        try {
            window.localStorage.setItem(DISMISSED_KEY, String(Date.now()));
        } catch {
            // ignore
        }
        setHidden(true);
    }

    async function install() {
        if (ios) {
            // Add to Home Screen saves the page's own address too: make sure it carries the card id.
            if (!window.location.pathname.startsWith("/my-cards") || !window.location.search.includes("c=")) {
                window.location.href = `/my-cards?c=${uuid}&add=1`;
                return;
            }
            setSteps(true);
            return;
        }
        const prompt = deferredInstall;
        deferredInstall = null;
        setCanInstall(false);
        await prompt?.prompt();
    }

    return (
        <>
            <div className="flex items-center gap-3 rounded-2xl border border-brand-border bg-brand-card p-3.5">
                <img src="/icons/icon-192.png" alt="" className="h-11 w-11 shrink-0 rounded-xl" />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold text-brand-text">Keep your cards on your home screen</p>
                    <p className="text-xs text-brand-muted">Check your stamps anytime, in one tap.</p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-1">
                    <button type="button" onClick={install} className="inline-flex items-center gap-1.5 rounded-full bg-brand-accent px-3.5 py-1.5 text-xs font-semibold text-brand-accent-text">
                        <LuDownload className="h-3.5 w-3.5" /> {ios ? "Add" : "Install"}
                    </button>
                    <button type="button" onClick={notNow} className="text-[11px] font-medium text-brand-muted hover:text-brand-text">
                        Not now
                    </button>
                </div>
            </div>
            {steps && <IosSteps onClose={() => setSteps(false)} />}
        </>
    );
}
