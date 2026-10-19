import { useEffect, useState } from "react";
import { LuCookie } from "react-icons/lu";

const KEY = "cookie_notice_ok";

/**
 * Once per browser: what cookies Tada Tap uses. Only essential ones (sign-in,
 * security, remembering a loyalty card) - which need no consent under UK /
 * EU rules - so this is a notice with "OK", not an accept / reject choice.
 * If non-essential cookies (analytics, ads) are ever added, this must become
 * a real choice and they must wait for it. On the public pages, sign-up /
 * log-in and setup; not on customers' cards or the staff app.
 */
export default function CookieNotice() {
    const [show, setShow] = useState(false);

    // After mounting only: browser storage isn't there during the first render.
    useEffect(() => {
        try {
            setShow(!window.localStorage.getItem(KEY));
        } catch {
            setShow(false); // storage blocked: don't nag on every page
        }
    }, []);

    if (!show) return null;

    function ok() {
        try {
            window.localStorage.setItem(KEY, new Date().toISOString());
        } catch {
            // ignore
        }
        setShow(false);
    }

    return (
        <div
            role="region"
            aria-label="Cookies"
            className="fixed inset-x-3 bottom-3 z-50 rounded-2xl border border-brand-border bg-brand-card p-4 shadow-2xl shadow-black/15 sm:inset-x-auto sm:left-6 sm:bottom-6 sm:max-w-sm"
        >
            <div className="flex items-start gap-3">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-accent/10 text-brand-accent">
                    <LuCookie className="h-5 w-5" />
                </span>
                <p className="text-sm text-brand-text">
                    We only use <strong>essential cookies</strong> and browser storage, to keep you signed in, keep the site secure and remember your loyalty card. No
                    advertising or tracking cookies.
                </p>
            </div>
            <div className="mt-3 flex items-center justify-end gap-3">
                <a href="/privacy#cookies" className="text-sm font-medium text-brand-muted hover:text-brand-text">
                    Cookie details
                </a>
                <button type="button" onClick={ok} className="rounded-full bg-brand-accent px-5 py-2 text-sm font-semibold text-brand-accent-text hover:brightness-95">
                    OK
                </button>
            </div>
        </div>
    );
}
