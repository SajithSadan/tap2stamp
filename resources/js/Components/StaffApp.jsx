import axios from "axios";
import InstallAppPrompt from "@/Components/InstallAppPrompt";
import { STAFF_TOKEN_KEY } from "@/lib/storage";

/**
 * The staff app on the home screen (Add to Home Screen / Install), so staff
 * open the scanner in one tap.
 *
 * Android: Chrome's installed app shares the browser's storage, so it's
 * already signed in - Install (Chrome's prompt) or the ⋮ menu steps.
 * iPhone: a home-screen app gets its own, empty storage, so "Add" first
 * fetches a one-time code (POST /api/staff/app-link, App\Services\StaffAppLinks)
 * and moves to /staff?link={code}&add=1 - the address Safari saves - then
 * shows the Share → Add to Home Screen steps. On its first launch the
 * installed app swaps the code for the device token (redeemAppLink()).
 */

const DISMISSED_KEY = "staff_install_dismissed_at";
const LINK = /^[A-Za-z0-9]{40}$/;

/** The one-time code in this page's address (?link=), if any. */
export function appLinkCode() {
    try {
        const code = new URLSearchParams(window.location.search).get("link");
        return code && LINK.test(code) ? code : null;
    } catch {
        return null;
    }
}

/**
 * The installed app's first launch (no device token stored yet): swap the
 * code for the token and keep it. 'stored' | 'expired' | 'none' (nothing to
 * do: a token is already stored, or there's no code) | 'error' (offline -
 * the code still works next time).
 */
export async function redeemAppLink() {
    const code = appLinkCode();
    let stored = null;
    try {
        stored = window.localStorage.getItem(STAFF_TOKEN_KEY);
    } catch {
        return "none";
    }
    if (!code || stored) return "none";

    try {
        const { data } = await axios.post("/staff/app/redeem", { code });
        window.localStorage.setItem(STAFF_TOKEN_KEY, data.token);
        return "stored";
    } catch (error) {
        return error.response?.status === 404 ? "expired" : "error";
    }
}

/** The manifest to install from: one whose start address carries the code, when there is one. */
export const staffManifestHref = () => {
    const code = appLinkCode();
    return code ? `/staff/app/${code}.webmanifest` : "/manifest.webmanifest";
};

/**
 * "Add the staff app to your home screen". `variant`: `banner` (the PIN
 * screen; "Not now" hides it for 14 days) or `row` (Today tab, always there
 * until installed). On iPhone it first fetches the one-time code and moves to
 * /staff?link={code}&add=1 - the address Safari will save.
 */
export function StaffInstallPrompt({ api, variant = "banner" }) {
    async function prepareIos() {
        if (appLinkCode()) return false; // already on the address to save
        const { data } = await api.post("/api/staff/app-link");
        window.location.href = `${data.url}&add=1`;
        return true;
    }

    return (
        <InstallAppPrompt
            title="Add the staff app to your home screen"
            subtitle="Open the scanner in one tap, every shift."
            stepsTitle="Add the staff app to your home screen"
            stepsDone="The scanner is then one tap away, already signed in to this shop."
            snoozeKey={variant === "banner" ? DISMISSED_KEY : null}
            prepareIos={prepareIos}
            autoOpenSteps={variant === "banner" && new URLSearchParams(window.location.search).get("add") === "1"}
            className={variant === "row" ? "mt-6" : ""}
        />
    );
}
