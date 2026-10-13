import { router, useForm, usePage } from "@inertiajs/react";
import { useState } from "react";
import { FaFacebook, FaWhatsapp } from "react-icons/fa6";
import { LuEye, LuEyeOff, LuKeyRound, LuLink, LuLoaderCircle, LuPlus, LuSmartphone, LuTrash2 } from "react-icons/lu";
import { FieldError, inputClass, Panel, primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";

/**
 * The shop's own WhatsApp number for offers (App\Services\WhatsAppConnect).
 * Not connected: two ways in, through Meta's Embedded Signup popup - a new
 * WhatsApp Business account / number, or the number on their WhatsApp
 * Business app (Meta's "coexistence"). Connected: the number and Remove;
 * a different number only after Remove.
 */

const MODES = {
    new: {
        icon: LuPlus,
        title: "Create a new WhatsApp Business account",
        text: "Use a number that isn't on WhatsApp yet. Meta's window walks you through it.",
    },
    existing: {
        icon: LuSmartphone,
        title: "Use my WhatsApp Business app",
        text: "Keep using the app on your phone. Offers go out from the same number; you'll scan a QR in the app.",
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
            <Panel title="Your WhatsApp number">
                <div className="flex flex-wrap items-center gap-3">
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
            </Panel>
        );
    }

    // With our Meta app set up, two ways in: Meta's window, or keys from their own Meta app.
    const ways = embeddedSignup
        ? [
              { key: "facebook", label: "Connect with Facebook", icon: FaFacebook },
              { key: "keys", label: "Enter keys", icon: LuKeyRound },
          ]
        : null;
    const way = ways ? (withKeys ? "keys" : "facebook") : "keys";

    return (
        <Panel title="Connect your WhatsApp" description="Offers go out from your shop's own WhatsApp number. Connect one to start sending.">
            {ways && (
                <div role="tablist" aria-label="How to connect" className="mb-5 inline-flex rounded-xl bg-brand-bg p-1">
                    {ways.map((w) => (
                        <button
                            key={w.key}
                            type="button"
                            role="tab"
                            aria-selected={way === w.key}
                            onClick={() => setWithKeys(w.key === "keys")}
                            className={`inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors ${
                                way === w.key ? "bg-brand-card text-brand-text ring-1 ring-brand-border" :"text-brand-muted hover:text-brand-text"
                            }`}
                        >
                            <w.icon className="h-4 w-4" /> {w.label}
                        </button>
                    ))}
                </div>
            )}
            {way === "facebook" ? (
                <>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {Object.entries(MODES).map(([mode, m]) => (
                            <button
                                key={mode}
                                type="button"
                                onClick={() => connect(mode)}
                                disabled={busy !== null}
                                className="flex items-start gap-3 rounded-2xl border border-brand-border p-4 text-left transition-colors hover:border-brand-accent disabled:opacity-60"
                            >
                                {busy === mode ? (
                                    <LuLoaderCircle className="mt-0.5 h-5 w-5 shrink-0 animate-spin text-brand-accent" />
                                ) : (
                                    <m.icon className="mt-0.5 h-5 w-5 shrink-0 text-brand-accent" />
                                )}
                                <span className="min-w-0">
                                    <span className="block text-sm font-semibold text-brand-text">{m.title}</span>
                                    <span className="mt-1 block text-xs text-brand-muted">{m.text}</span>
                                </span>
                            </button>
                        ))}
                    </div>
                    <p className="mt-3 text-xs text-brand-muted">You'll log in with Facebook. Meta bills your business for marketing messages.</p>
                    <FieldError message={error ?? errors?.whatsapp} />
                </>
            ) : (
                <KeysForm />
            )}
        </Panel>
    );
}
