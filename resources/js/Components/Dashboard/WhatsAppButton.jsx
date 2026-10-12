import { usePage } from "@inertiajs/react";
import QRCode from "qrcode";
import { useEffect, useState } from "react";
import { createPortal } from "react-dom";
import { FaWhatsapp } from "react-icons/fa6";
import { LuCheck, LuExternalLink, LuHandHeart, LuMessageCircle, LuX } from "react-icons/lu";
import { CopyButton, secondaryButton } from "@/Components/Dashboard/Ui";

/** A mouse and a big screen: show the QR to scan with the phone instead of opening WhatsApp here. */
const isDesktop = () => typeof window !== "undefined" && window.matchMedia?.("(hover: hover) and (pointer: fine)").matches;

/** "+447700900123" from "447700900123" - for reading; the link uses the digits. */
const pretty = (number) => `+${number}`;

const OPTIONS = [
    { key: "chat", label: "Chat", icon: LuMessageCircle },
    { key: "welcome", label: "Welcome", icon: LuHandHeart },
];

/**
 * Admin: message a shop / owner on WhatsApp. `number` is the full
 * international number, digits only (`App\Support\WhatsApp::number()`).
 * Opens a small dialog: a plain **Chat**, or the **Welcome message** (Admin →
 * Settings → WhatsApp, shared as `whatsappWelcome`) typed in, ready to send -
 * WhatsApp's own `?text=`, so it's sent by the admin, from their WhatsApp.
 * On a phone: "Open WhatsApp". On a desktop: a QR of the same link to scan
 * with the phone, plus "Open on this computer" (WhatsApp Web / Desktop).
 * `text`: a labelled button (page headers) instead of the grid's icon.
 *
 * What the admin opens is reported (POST /admin/whatsapp-contacts →
 * activity log; the owner's first welcome → users.welcome_sent_at), for the
 * grids and the AI analysis: automatically for "Open WhatsApp" / "Open on this
 * computer"; after scanning the QR, with "Mark as sent". `shopId` / `userId`
 * say who it was; `welcomeSentLabel` = when the welcome was first sent.
 */
export default function WhatsAppButton({ number, name, text, shopId = null, userId = null, welcomeSentLabel = null }) {
    const welcome = usePage().props.whatsappWelcome;
    const [open, setOpen] = useState(false);
    const [sentLabel, setSentLabel] = useState(welcomeSentLabel);
    const [marked, setMarked] = useState(false);
    const [kind, setKind] = useState("chat");
    const [qr, setQr] = useState(null);
    const desktop = isDesktop();
    const chatLink = `https://wa.me/${number}`;
    const link = kind === "welcome" && welcome ? `${chatLink}?text=${encodeURIComponent(welcome)}` : chatLink;

    useEffect(() => {
        if (!open || !desktop) return;
        QRCode.toDataURL(link, { margin: 1, width: 240 })
            .then(setQr)
            .catch(() => setQr(null));
    }, [open, desktop, link]);

    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === "Escape" && setOpen(false);
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [open]);

    if (!number) return null;

    // Fire and forget: reporting must never get in the way of opening WhatsApp.
    function report() {
        if (!shopId && !userId) return;
        window.axios
            ?.post("/admin/whatsapp-contacts", { kind, shop_id: shopId, user_id: userId })
            .then(({ data }) => data.welcome_sent_label && setSentLabel(data.welcome_sent_label))
            .catch(() => {});
    }

    const label = `WhatsApp ${name ?? pretty(number)}`;
    const buttonClass = text
        ? secondaryButton
        : "inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[#25D366] transition-colors hover:bg-[#25D366]/10";

    return (
        <>
            <a
                href={chatLink}
                target="_blank"
                rel="noopener noreferrer"
                aria-label={label}
                title={label}
                onClick={(e) => {
                    e.preventDefault();
                    setKind("chat");
                    setMarked(false);
                    setOpen(true);
                }}
                className={buttonClass}
            >
                <FaWhatsapp className={`h-4 w-4 ${text ? "text-[#25D366]" : ""}`} /> {text}
            </a>

            {open &&
                createPortal(
                    <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 p-4 backdrop-blur-sm sm:items-center" onClick={() => setOpen(false)}>
                        <div
                            role="dialog"
                            aria-modal="true"
                            aria-label={label}
                            onClick={(e) => e.stopPropagation()}
                            className="w-full max-w-sm rounded-2xl bg-brand-card p-5 shadow-xl"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <h2 className="flex items-center gap-2 font-heading text-lg font-semibold text-brand-text">
                                        <FaWhatsapp className="h-5 w-5 shrink-0 text-[#25D366]" />
                                        <span className="truncate">{name ?? "WhatsApp"}</span>
                                    </h2>
                                    <p className="mt-0.5 flex items-center gap-1 text-sm tabular-nums text-brand-muted">
                                        {pretty(number)}
                                        <CopyButton text={pretty(number)} label="Copy number" />
                                    </p>
                                </div>
                                <button type="button" onClick={() => setOpen(false)} aria-label="Close" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                                    <LuX className="h-5 w-5" />
                                </button>
                            </div>

                            {welcome && (
                                <div role="radiogroup" aria-label="What to send" className="mt-4 grid grid-cols-2 gap-1 rounded-xl bg-brand-bg p-1">
                                    {OPTIONS.map(({ key, label: optionLabel, icon: Icon }) => (
                                        <button
                                            key={key}
                                            type="button"
                                            role="radio"
                                            aria-checked={kind === key}
                                            onClick={() => {
                                                setKind(key);
                                                setMarked(false);
                                            }}
                                            className={`flex items-center justify-center gap-1.5 rounded-lg px-2 py-2 text-sm font-medium transition-colors ${
                                                kind === key ? "bg-brand-card text-brand-text shadow-sm" : "text-brand-muted hover:text-brand-text"
                                            }`}
                                        >
                                            <Icon className="h-4 w-4" /> {optionLabel}
                                        </button>
                                    ))}
                                </div>
                            )}

                            {kind === "welcome" && welcome && (
                                // WhatsApp-style bubble: exactly what will be typed in.
                                <div className="mt-3 rounded-xl bg-[#e7f8ee] p-3">
                                    <p className="whitespace-pre-line break-words text-sm text-neutral-800">{welcome}</p>
                                </div>
                            )}
                            {kind === "welcome" && welcome && sentLabel && (
                                <p className="mt-2 flex items-center gap-1.5 text-xs text-emerald-700">
                                    <LuCheck className="h-3.5 w-3.5" /> Welcome first sent {sentLabel}
                                </p>
                            )}

                            {desktop ? (
                                <>
                                    <div className="mx-auto mt-4 flex h-48 w-48 items-center justify-center rounded-xl bg-white p-2 ring-1 ring-brand-border">
                                        {qr && <img src={qr} alt={`QR code to open WhatsApp with ${pretty(number)}`} className="h-full w-full" />}
                                    </div>
                                    <p className="mt-3 text-center text-xs text-brand-muted">
                                        {kind === "welcome"
                                            ? "Scan with your phone's camera - WhatsApp opens with the message typed in, ready to send."
                                            : "Scan with your phone's camera to open the chat in WhatsApp."}
                                    </p>
                                    <div className={`mt-4 grid gap-2 ${kind === "welcome" ? "grid-cols-2" : ""}`}>
                                        {kind === "welcome" && (
                                            // Scanned with the phone: the app can't see that, so the admin says so.
                                            <button
                                                type="button"
                                                disabled={marked}
                                                onClick={() => {
                                                    report();
                                                    setMarked(true);
                                                }}
                                                className={`${secondaryButton} w-full`}
                                            >
                                                <LuCheck className="h-4 w-4" /> {marked ? "Recorded" : "Mark as sent"}
                                            </button>
                                        )}
                                        <a href={link} target="_blank" rel="noopener noreferrer" onClick={report} className={`${secondaryButton} w-full`}>
                                            <LuExternalLink className="h-4 w-4" /> {kind === "welcome" ? "Open here" : "Open on this computer"}
                                        </a>
                                    </div>
                                </>
                            ) : (
                                <a
                                    href={link}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    onClick={() => {
                                        report();
                                        setOpen(false);
                                    }}
                                    className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-3 text-sm font-semibold text-white"
                                >
                                    <FaWhatsapp className="h-4 w-4" />
                                    {kind === "welcome" ? "Send welcome message" : "Open WhatsApp"}
                                </a>
                            )}
                        </div>
                    </div>,
                    document.body,
                )}
        </>
    );
}
