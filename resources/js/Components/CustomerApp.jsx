import { Head } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { LuDownload } from "react-icons/lu";
import AddToHomeScreenSteps, { useChromeInstall } from "@/Components/AddToHomeScreenSteps";
import { isAndroid, isInstalledApp, isIos, isSamsungInternet, snooze, snoozedSince, useBrowserInstall } from "@/lib/pwa";
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

/**
 * "Add My Cards to your home screen", from the customer's first visit (right
 * under their QR on the card page, and on My Cards): Install where the browser
 * offers it (Chrome's own prompt); otherwise "Add" with the steps - iPhone
 * (Share → Add to Home Screen, there's no prompt at all) or Android before
 * Chrome decides to offer Install (⋮ menu → Add to Home screen). Nothing on
 * desktop without a prompt, or when already installed. "Not now" snoozes it
 * for 14 days. `autoOpenSteps`: open the steps straight away (?add=1).
 * Samsung Internet: "Open in Chrome" with the card id in the link, never
 * Samsung's own Install (Play Protect blocks it as an "unsafe app").
 */
export function InstallPrompt({ uuid, autoOpenSteps = false }) {
    const { canInstall, promptInstall } = useBrowserInstall();
    const [hidden, setHidden] = useState(() => isInstalledApp() || snoozedSince(DISMISSED_KEY, SNOOZE_DAYS));
    const [steps, setSteps] = useState(false);
    const ios = isIos();
    // Android before Chrome offers its own Install (it decides when): show the ⋮ menu steps instead.
    const android = !ios && isAndroid();
    const samsung = android && isSamsungInternet();
    const title = "Add My Cards to your home screen";
    const done = "Your cards are then one tap away.";
    const chrome = useChromeInstall({ title, done, chromePath: () => `/my-cards?c=${uuid}&add=1` });

    useEffect(() => {
        if (autoOpenSteps && (ios || android) && !samsung && !isInstalledApp()) setSteps(true);
    }, [autoOpenSteps, ios, android, samsung]);

    const dialogs = (
        <>
            {steps && <AddToHomeScreenSteps title={title} done={done} android={!ios} onClose={() => setSteps(false)} />}
            {chrome.dialog}
        </>
    );
    if (!uuid || hidden || (!ios && !android && !canInstall)) return dialogs;

    function notNow() {
        snooze(DISMISSED_KEY);
        setHidden(true);
    }

    async function install() {
        if (samsung) {
            chrome.show();
            return;
        }
        if (canInstall) {
            await promptInstall();
            return;
        }
        if (android) {
            setSteps(true);
            return;
        }
        if (ios) {
            // Add to Home Screen saves the page's own address too: make sure it carries the card id.
            if (!window.location.pathname.startsWith("/my-cards") || !window.location.search.includes("c=")) {
                window.location.href = `/my-cards?c=${uuid}&add=1`;
                return;
            }
            setSteps(true);
        }
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
                        <LuDownload className="h-3.5 w-3.5" /> {canInstall && !samsung ? "Install" : "Add"}
                    </button>
                    <button type="button" onClick={notNow} className="text-[11px] font-medium text-brand-muted hover:text-brand-text">
                        Not now
                    </button>
                </div>
            </div>
            {dialogs}
        </>
    );
}
