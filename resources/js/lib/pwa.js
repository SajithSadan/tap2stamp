import { useEffect, useState } from "react";

/**
 * Home-screen app helpers shared by the customer's My Cards app
 * (Components/CustomerApp.jsx) and the staff app (Components/StaffApp.jsx).
 * iPhones never offer an install prompt (Share → Add to Home Screen by hand),
 * and an iPhone home-screen app gets its own, empty storage - so each app's
 * link has to carry whatever identifies it. The same link carries the app
 * over to Chrome from Samsung Internet (Chrome has its own storage too).
 */

/** Running as the installed home-screen app (Android or iPhone). */
export const isInstalledApp = () =>
    typeof window !== "undefined" && (window.matchMedia?.("(display-mode: standalone)").matches || window.navigator.standalone === true);

export const isIos = () =>
    typeof navigator !== "undefined" &&
    (/iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1));

export const isAndroid = () => typeof navigator !== "undefined" && /Android/i.test(navigator.userAgent);

/**
 * Samsung Internet. Its own Install wraps the site in an app built for an old
 * Android, which Google Play Protect blocks as an "unsafe app" - so on Samsung
 * Internet we send people to Chrome to install instead (chromeIntentUrl()).
 */
export const isSamsungInternet = () => typeof navigator !== "undefined" && /SamsungBrowser/i.test(navigator.userAgent);

/** An Android link that opens `path` on this site in Chrome (the Play Store offers Chrome if it's missing). */
export function chromeIntentUrl(path) {
    const url = new URL(path, window.location.href);
    return `intent://${url.host}${url.pathname}${url.search}#Intent;scheme=${url.protocol.replace(":", "")};package=com.android.chrome;end`;
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

/** Chrome's own Install prompt: whether it's on offer, and showing it (once). */
export function useBrowserInstall() {
    const [canInstall, setCanInstall] = useState(Boolean(deferredInstall));

    useEffect(() => {
        const update = () => setCanInstall(Boolean(deferredInstall));
        installListeners.add(update);
        return () => installListeners.delete(update);
    }, []);

    async function promptInstall() {
        const prompt = deferredInstall;
        deferredInstall = null;
        setCanInstall(false);
        await prompt?.prompt();
    }

    return { canInstall, promptInstall };
}

/** "Not now" for `days`, remembered in this browser. */
export function snoozedSince(key, days) {
    try {
        const at = Number(window.localStorage.getItem(key));
        return Boolean(at) && Date.now() - at < days * 86400000;
    } catch {
        return false;
    }
}

export function snooze(key) {
    try {
        window.localStorage.setItem(key, String(Date.now()));
    } catch {
        // ignore
    }
}
