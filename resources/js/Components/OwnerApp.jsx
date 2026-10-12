import { Head } from "@inertiajs/react";
import { useEffect } from "react";
import InstallAppPrompt from "@/Components/InstallAppPrompt";

/**
 * The shop owner's home-screen app, "Tada Tap Business"
 * (public/owner.webmanifest). It starts at /login: signed in → the login page
 * sends them to their home (dashboard, or the admin panel), otherwise the
 * login form ("Keep me signed in" is on by default). On iPhone the app keeps
 * its own cookies, so the owner logs in once inside the app.
 */
export function OwnerAppHead() {
    useEffect(() => {
        if ("serviceWorker" in navigator) navigator.serviceWorker.register("/sw.js").catch(() => {});
    }, []);

    return (
        <Head>
            <link rel="manifest" href="/owner.webmanifest" />
            <meta name="apple-mobile-web-app-capable" content="yes" />
            <meta name="apple-mobile-web-app-title" content="Tada Tap" />
            <meta name="apple-mobile-web-app-status-bar-style" content="default" />
        </Head>
    );
}

/** "Add Tada Tap to your home screen" on the owner's Overview ("Not now" = 14 days). */
export function OwnerInstallPrompt({ className = "" }) {
    return (
        <InstallAppPrompt
            title="Add Tada Tap to your home screen"
            subtitle="Your dashboard and scanner in one tap."
            stepsTitle="Add Tada Tap to your home screen"
            stepsDone="Open it from your home screen and log in once - it keeps you signed in."
            snoozeKey="owner_install_dismissed_at"
            className={className}
        />
    );
}
