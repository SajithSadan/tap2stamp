import { router, useForm, usePage } from "@inertiajs/react";
import { useState } from "react";
import { FaFacebook, FaWhatsapp } from "react-icons/fa6";
import { LuEye, LuEyeOff, LuKeyRound, LuLink, LuLoaderCircle, LuPlus, LuRefreshCw, LuSmartphone, LuTrash2 } from "react-icons/lu";
import { FieldError, inputClass, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";

/**
 * The shop's own WhatsApp number for offers (App\Services\WhatsAppConnect).
 * Not connected: two ways in, through Meta's Embedded Signup popup - a new
 * WhatsApp Business account / number, or the number on their WhatsApp
 * Business app (Meta's "coexistence"). Connected: the number and Remove;
 * a different number only after Remove.
 */

const MODES = {
    existing: {
        icon: LuSmartphone,
        title: "My WhatsApp Business app",
        text: "Keep the number you use on your phone.",
    },
    new: {
        icon: LuPlus,
        title: "A new number",
        text: "One that isn't on WhatsApp yet.",
    },
};

const MODE_LABEL = { new: "new account", existing: "WhatsApp Business app", keys: "your Meta keys" };

// Meta's SDK, loaded once, only when the owner starts connecting.
let sdkPromise = null;
function loadFacebookSdk(appId, version) {
    if (window.FB) return Promise.resolve(window.FB);
    sdkPromise ??= new Promise((resolve, reject) => {
        window.fbAsyncInit = () => {
            window.FB.init({ appId, autoLogAppEvents: true, xfbml: false, version });
            resolve(window.FB);
        };
        const script = document.createElement("script");
        script.src = "https://connect.facebook.net/en_US/sdk.js";
        script.async = true;
        script.crossOrigin = "anonymous";
        script.onerror = () => {
            sdkPromise = null;
            reject(new Error("sdk"));
        };
        document.body.appendChild(script);
    });
    return sdkPromise;
}

/**
 * Opens Meta's popup. Resolves with {code, waba_id, phone_number_id}: the
 * code from the login callback, the ids from the popup's message event
 * (either can arrive first). Rejects with a message to show, or null when
 * the owner just closed it.
 */
function runEmbeddedSignup(settings, mode) {
    return loadFacebookSdk(settings.app_id, settings.api_version).then(
        (FB) =>
            new Promise((resolve, reject) => {
                let session = null;
                let code = null;
                let done = false;

                const finish = (error, value) => {
                    if (done) return;
                    done = true;
                    window.removeEventListener("message", onMessage);
                    error === undefined ? resolve(value) : reject(error);
                };
                const tryResolve = () => code && session && finish(undefined, { code, ...session });

                function onMessage(event) {
                    if (!/^https:\/\/([a-z]+\.)?facebook\.com$/.test(event.origin)) return;
                    let data;
                    try {
                        data = typeof event.data === "string" ? JSON.parse(event.data) : event.data;
                    } catch {
                        return;
                    }
                    if (data?.type !== "WA_EMBEDDED_SIGNUP") return;

                    if (String(data.event).startsWith("FINISH")) {
                        if (!data.data?.phone_number_id || !data.data?.waba_id) {
                            finish("Add a phone number in Meta's window to finish.");
                            return;
                        }
                        session = { waba_id: String(data.data.waba_id), phone_number_id: String(data.data.phone_number_id) };
                        tryResolve();
                    } else if (data.event === "CANCEL") {
                        finish(null);
                    } else if (data.event === "ERROR") {
                        finish(data.data?.error_message || "Meta couldn't finish the setup. Please try again.");
                    }
                }
                window.addEventListener("message", onMessage);

                FB.login(
                    (response) => {
                        code = response?.authResponse?.code ?? null;
                        if (!code) {
                            finish(null);
                            return;
                        }
                        tryResolve();
                        // The ids normally arrive first; give them a moment if not.
                        setTimeout(() => finish("Meta didn't send the number's details. Please try again."), 8000);
                    },
                    {
                        config_id: settings.config_id,
                        response_type: "code",
                        override_default_response_type: true,
                        extras: {
                            setup: {},
                            sessionInfoVersion: "3",
                            ...(mode === "existing" && { featureType: "whatsapp_business_app_onboarding" }),
                        },
                    },
                );
            }),
    );
}

/**
 * The keys from the owner's own Meta app (developers.facebook.com), checked
 * with Meta before saving. For owners who set WhatsApp up in Meta themselves,
 * or while Tada Tap's Connect popup isn't available.
 */
function KeysForm() {
    const form = useForm({ waba_id: "", phone_number_id: "", access_token: "" });
    const [showToken, setShowToken] = useState(false);

    function submit(event) {
        event.preventDefault();
        form.post("/dashboard/whatsapp/connection/keys", { preserveScroll: true, onSuccess: () => form.reset() });
    }

    // Not a login: names and autocomplete stop the browser filling in the owner's email / password.
    const field = (key, label, props = {}) => (
        <div>
            <label htmlFor={`wa-${key}`} className="mb-1.5 block text-sm font-medium text-brand-text">
                {label}
            </label>
            <input
                id={`wa-${key}`}
                name={`wa_${key}`}
                value={form.data[key]}
                onChange={(e) => form.setData(key, e.target.value)}
                className={inputClass}
                autoComplete="off"
                data-1p-ignore
                data-lpignore="true"
                spellCheck={false}
                {...props}
            />
            <FieldError message={form.errors[key]} />
        </div>
    );

    return (
        <form onSubmit={submit} noValidate autoComplete="off" className="space-y-4">
            <ol className="list-decimal space-y-1 pl-5 text-xs text-brand-muted">
                <li>
                    On <span className="font-medium text-brand-text">developers.facebook.com</span>, create an app and add WhatsApp.
                </li>
                <li>
                    In <span className="font-medium text-brand-text">WhatsApp → API Setup</span>, add your business number and copy its two IDs below.
                </li>
                <li>
                    In <span className="font-medium text-brand-text">Business settings → System users</span>, generate a permanent token with
                    WhatsApp permissions. (API Setup's temporary token stops after 24 hours.)
                </li>
            </ol>
            <div className="grid gap-4 sm:grid-cols-2">
                {field("waba_id", "WhatsApp Business Account ID", { inputMode: "numeric", placeholder: "e.g. 102290129340398" })}
                {field("phone_number_id", "Phone number ID", { inputMode: "numeric", placeholder: "e.g. 106540352242922" })}
            </div>
            <div>
                <label htmlFor="wa-access_token" className="mb-1.5 block text-sm font-medium text-brand-text">
                    Access token
                </label>
                <div className="relative">
                    <input
                        id="wa-access_token"
                        name="wa_access_token"
                        type={showToken ? "text" : "password"}
                        value={form.data.access_token}
                        onChange={(e) => form.setData("access_token", e.target.value)}
                        placeholder="EAAG…"
                        className={`${inputClass} pr-11 font-mono`}
                        autoComplete="new-password"
                        data-1p-ignore
                        data-lpignore="true"
                        spellCheck={false}
                    />
                    <button
                        type="button"
                        onClick={() => setShowToken((v) => !v)}
                        aria-label={showToken ? "Hide token" : "Show token"}
                        aria-pressed={showToken}
                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-brand-muted hover:text-brand-text"
                    >
                        {showToken ? <LuEyeOff className="h-4 w-4" /> : <LuEye className="h-4 w-4" />}
                    </button>
                </div>
                <FieldError message={form.errors.access_token} />
            </div>
            <button type="submit" disabled={form.processing} className={primaryButton}>
                <LuLink className="h-4 w-4" /> {form.processing ? "Checking with Meta…" : "Connect"}
            </button>
        </form>
    );
}

// Meta's review of our offer templates on the shop's account (App\Services\WhatsAppTemplates).
const TEMPLATE_STATES = {
    APPROVED: { label: "Approved", className: "bg-brand-accent/10 text-brand-accent" },
    PENDING: { label: "Waiting for Meta", className: "bg-amber-500/10 text-amber-700" },
    IN_APPEAL: { label: "Waiting for Meta", className: "bg-amber-500/10 text-amber-700" },
    REJECTED: { label: "Rejected", className: "bg-red-500/10 text-red-600" },
    PAUSED: { label: "Paused by Meta", className: "bg-red-500/10 text-red-600" },
    DISABLED: { label: "Disabled by Meta", className: "bg-red-500/10 text-red-600" },
};
const NOT_SET_UP = { label: "Not set up", className: "bg-brand-bg text-brand-muted" };

function TemplateStatus({ templates }) {
    const [checking, setChecking] = useState(false);
    const rows = [
        { key: "offer", label: "Offer message" },
        { key: "poster", label: "Offer with a poster" },
    ];
    const allApproved = rows.every((r) => templates?.[r.key] === "APPROVED");

    function check() {
        router.post("/dashboard/whatsapp/templates", {}, { preserveScroll: true, onStart: () => setChecking(true), onFinish: () => setChecking(false) });
    }

    return (
        <div className="border-t border-brand-border px-5 py-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">Message templates</p>
                {!allApproved && (
                    <button type="button" onClick={check} disabled={checking} className="inline-flex items-center gap-1.5 text-sm font-medium text-brand-muted hover:text-brand-text disabled:opacity-60">
                        <LuRefreshCw className={`h-4 w-4 ${checking ? "animate-spin" : ""}`} /> Check again
                    </button>
                )}
            </div>
            <ul className="mt-1 divide-y divide-brand-border">
                {rows.map((r) => {
                    const state = TEMPLATE_STATES[templates?.[r.key]] ?? NOT_SET_UP;

                    return (
                        <li key={r.key} className="flex items-center justify-between gap-3 py-2 text-sm">
                            <span className="text-brand-text">{r.label}</span>
                            <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${state.className}`}>{state.label}</span>
                        </li>
                    );
                })}
            </ul>
            {!allApproved && <p className="text-xs text-brand-muted">Meta reviews each new number's messages, usually within minutes, sometimes a day.</p>}
        </div>
    );
}

export default function WhatsAppConnectPanel({ account, embeddedSignup, confirm }) {
    const { errors } = usePage().props;
    const [busy, setBusy] = useState(null);
    const [error, setError] = useState(null);
    const [withKeys, setWithKeys] = useState(false);

    async function connect(mode) {
        setError(null);
        setBusy(mode);
        try {
            const result = await runEmbeddedSignup(embeddedSignup, mode);
            router.post("/dashboard/whatsapp/connection", { mode, ...result }, { preserveScroll: true, onFinish: () => setBusy(null) });
        } catch (e) {
            setBusy(null);
            if (e === null) return; // closed the window
            setError(typeof e === "string" ? e : "Couldn't open Meta's window. Allow pop-ups for this site and try again.");
        }
    }

    async function remove() {
        const ok = await confirm({
            title: "Remove this WhatsApp number?",
            message: "Offers can't be sent until you connect a number again. Nothing changes on the number itself.",
            confirmLabel: "Remove",
            danger: true,
        });
        if (!ok) return;

        router.delete("/dashboard/whatsapp/connection", { preserveScroll: true });
    }

    if (account) {
        return (
            <Panel title="Your WhatsApp number" bodyClassName="">
                <div className="flex flex-wrap items-center gap-3 px-5 py-4">
                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#25D366]/15 text-[#128C7E]">
                        <FaWhatsapp className="h-5 w-5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-brand-text">
                            {[account.phone, account.name].filter(Boolean).join(" · ") || "Connected"}
                        </p>
                        <p className="text-xs text-brand-muted">
                            Offers go out from this number · {MODE_LABEL[account.mode] ?? account.mode}
                            {account.connected_at && ` · since ${account.connected_at}`}
                        </p>
                    </div>
                    <button type="button" onClick={remove} className={secondaryButton}>
                        <LuTrash2 className="h-4 w-4" /> Remove
                    </button>
                </div>
                <TemplateStatus templates={account.templates} />
            </Panel>
        );
    }

    // Meta's window first, keys from their own Meta app behind a link. Without our Meta app's
    // keys the two rows still show, as "Coming soon", and keys are the way in.
    const popupReady = Boolean(embeddedSignup);
    const linkButton = "inline-flex items-center gap-1.5 text-sm font-medium text-brand-muted hover:text-brand-text";

    return (
        <Panel
            title="Connect your WhatsApp"
            description="Offers go out from your shop's own number."
            action={
                withKeys && (
                    <button type="button" onClick={() => setWithKeys(false)} className={linkButton}>
                        <FaFacebook className="h-4 w-4 text-[#1877F2]" /> {popupReady ? "Connect with Facebook" : "Back"}
                    </button>
                )
            }
            bodyClassName={withKeys ? "p-5" : ""}
        >
            {!withKeys ? (
                <>
                    <ul className="divide-y divide-brand-border">
                        {Object.entries(MODES).map(([mode, m]) => (
                            <li key={mode} className="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
                                <span
                                    className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${
                                        popupReady ? "bg-[#25D366]/15 text-[#128C7E]" : "bg-brand-bg text-brand-muted"
                                    }`}
                                >
                                    <m.icon className="h-5 w-5" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className={`text-sm font-semibold ${popupReady ? "text-brand-text" : "text-brand-muted"}`}>{m.title}</p>
                                    <p className="text-xs text-brand-muted">{m.text}</p>
                                </div>
                                {popupReady ? (
                                    <button
                                        type="button"
                                        onClick={() => connect(mode)}
                                        disabled={busy !== null}
                                        className={`${mode === "existing" ? primaryButton : secondaryButton} w-full sm:w-auto`}
                                    >
                                        {busy === mode ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <FaFacebook className="h-4 w-4" />}
                                        {busy === mode ? "Opening Meta…" : "Connect"}
                                    </button>
                                ) : (
                                    <span className="rounded-full bg-brand-bg px-2.5 py-1 text-xs font-medium text-brand-muted">Coming soon</span>
                                )}
                            </li>
                        ))}
                    </ul>
                    {(error || errors?.whatsapp) && (
                        <div className="px-5 pb-3">
                            <FieldError message={error ?? errors?.whatsapp} />
                        </div>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t border-brand-border px-5 py-3">
                        <p className="text-xs text-brand-muted">
                            {popupReady ? "You'll sign in with Facebook. Meta bills marketing messages." : "Have your own Meta keys? Connect with them now."}
                        </p>
                        <button
                            type="button"
                            onClick={() => setWithKeys(true)}
                            className={popupReady ? linkButton : secondaryButton}
                        >
                            <LuKeyRound className="h-4 w-4" /> {popupReady ? "Enter keys instead" : "Enter keys"}
                        </button>
                    </div>
                </>
            ) : (
                <KeysForm />
            )}
        </Panel>
    );
}
