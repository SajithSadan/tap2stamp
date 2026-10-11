import { AnimatePresence, motion } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { LuCheck, LuLoaderCircle, LuMic, LuSparkles, LuSquare, LuX } from "react-icons/lu";
import { RiRobot2Fill } from "react-icons/ri";

/**
 * The AI menu assistant: "Chicken theernnu" / "Disable fish items" / "Meals
 * available now". A highlighted card opens a full-screen panel - big mic
 * (Web Speech API) that fills a multi-line box, or type - then Gemini
 * (MenuCommandParser) picks the items and a dialog asks to confirm before
 * anything changes. Shared by the menu editor and the staff app's Menu tab,
 * which pass:
 * - `parse(command)` → the server's {availability, items, explanation}
 * - `apply(ids, availability)` → saves (the same call as the manual switches)
 */

const LANGUAGES = [
    ["en-GB", "English"],
    ["en-IN", "English (India)"],
    ["ml-IN", "മലയാളം"],
];
const LANGUAGE_KEY = "menu_ai_voice_lang";
const LABEL = { available: "Available", unavailable: "Not available", hidden: "Hidden" };
const EXAMPLES = ["Chicken theernnu", "Disable fish items", "Meals available now"];
const AI_GRADIENT = "bg-[linear-gradient(110deg,#7c3aed,#db2777,#f59e0b,#17c68b,#2a78d6,#7c3aed)] bg-[length:300%_100%]";

const Recognition = typeof window !== "undefined" ? window.SpeechRecognition || window.webkitSpeechRecognition : null;

function storedLanguage() {
    try {
        return window.localStorage.getItem(LANGUAGE_KEY) || "en-GB";
    } catch {
        return "en-GB";
    }
}

/** The AI's face: a robot that bobs, with a spinning halo and "thinking" dots while busy. */
function Bot({ size = "lg", thinking = false }) {
    const box = size === "lg" ? "h-28 w-28" : "h-11 w-11";
    const icon = size === "lg" ? "h-14 w-14" : "h-6 w-6";

    return (
        <div className={`relative flex shrink-0 items-center justify-center ${box}`}>
            {thinking && (
                <motion.span
                    className={`absolute inset-0 rounded-full ${AI_GRADIENT} p-[3px] [mask:radial-gradient(farthest-side,transparent_calc(100%-3px),#000_calc(100%-3px))]`}
                    animate={{ rotate: 360 }}
                    transition={{ duration: 1.4, repeat: Infinity, ease: "linear" }}
                />
            )}
            <motion.span
                className={`flex items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-lg ${
                    size === "lg" ? "h-24 w-24" : "h-11 w-11"
                }`}
                animate={thinking ? { y: [0, -6, 0], rotate: [0, -6, 6, 0] } : { y: [0, -3, 0] }}
                transition={{ duration: thinking ? 0.9 : 2.4, repeat: Infinity, ease: "easeInOut" }}
            >
                <RiRobot2Fill className={icon} />
            </motion.span>
            <motion.span
                className="absolute -right-0.5 -top-0.5 text-amber-300"
                animate={{ scale: [1, 1.35, 1], rotate: [0, 20, 0], opacity: [0.7, 1, 0.7] }}
                transition={{ duration: 1.6, repeat: Infinity }}
            >
                <LuSparkles className={size === "lg" ? "h-6 w-6" : "h-3.5 w-3.5"} />
            </motion.span>
        </div>
    );
}

function ThinkingDots() {
    return (
        <span className="inline-flex gap-1" aria-hidden="true">
            {[0, 1, 2].map((i) => (
                <motion.span
                    key={i}
                    className="h-1.5 w-1.5 rounded-full bg-white"
                    animate={{ y: [0, -4, 0], opacity: [0.4, 1, 0.4] }}
                    transition={{ duration: 0.8, repeat: Infinity, delay: i * 0.15 }}
                />
            ))}
        </span>
    );
}

/** Big mic with rings pulsing out while it listens. */
function MicButton({ listening, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={listening ? "Stop listening" : "Speak"}
            className="relative flex h-28 w-28 items-center justify-center"
        >
            {listening &&
                [0, 1, 2].map((i) => (
                    <motion.span
                        key={i}
                        className="absolute inset-0 rounded-full bg-fuchsia-500/40"
                        initial={{ scale: 0.7, opacity: 0.7 }}
                        animate={{ scale: 1.9, opacity: 0 }}
                        transition={{ duration: 1.8, repeat: Infinity, delay: i * 0.6, ease: "easeOut" }}
                    />
                ))}
            <motion.span
                className={`relative flex h-24 w-24 items-center justify-center rounded-full text-white shadow-xl ${
                    listening ? "bg-gradient-to-br from-fuchsia-500 to-rose-500" : "bg-gradient-to-br from-violet-500 to-fuchsia-500"
                }`}
                animate={listening ? { scale: [1, 1.06, 1] } : { scale: 1 }}
                transition={{ duration: 0.9, repeat: listening ? Infinity : 0 }}
            >
                {listening ? <LuSquare className="h-8 w-8" /> : <LuMic className="h-10 w-10" />}
            </motion.span>
        </button>
    );
}

function ConfirmDialog({ result, onConfirm, onCancel }) {
    const [picked, setPicked] = useState(() => new Set(result.items.map((i) => i.id)));
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const target = result.availability;
    const count = picked.size;

    async function confirm() {
        setBusy(true);
        setError(null);
        try {
            await onConfirm([...picked], target);
        } catch (e) {
            setError(e?.message || "Couldn't update - try again.");
            setBusy(false);
        }
    }

    return (
        <div className="fixed inset-0 z-[70] flex items-end justify-center bg-neutral-950/60 sm:items-center sm:p-4" onClick={busy ? undefined : onCancel}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Confirm menu update"
                onClick={(e) => e.stopPropagation()}
                className="flex max-h-[85dvh] w-full flex-col rounded-t-2xl bg-brand-card shadow-xl sm:max-w-md sm:rounded-2xl"
            >
                <div className="flex items-start justify-between gap-3 px-5 pt-5">
                    <div className="flex items-start gap-3">
                        <Bot size="sm" />
                        <div>
                            <h2 className="font-heading text-lg font-semibold text-brand-text">
                                Gemini matched {result.items.length} {result.items.length === 1 ? "item" : "items"}
                            </h2>
                            {result.explanation && <p className="mt-0.5 text-sm text-brand-muted">{result.explanation}</p>}
                        </div>
                    </div>
                    <button type="button" onClick={onCancel} disabled={busy} aria-label="Cancel" className="-m-1 rounded-lg p-1 text-brand-muted hover:text-brand-text">
                        <LuX className="h-5 w-5" />
                    </button>
                </div>

                <ul className="mt-3 flex-1 space-y-1 overflow-y-auto px-5">
                    {result.items.map((item) => {
                        const on = picked.has(item.id);
                        return (
                            <li key={item.id}>
                                <label className="flex cursor-pointer items-center gap-3 rounded-xl px-2 py-2 hover:bg-brand-bg">
                                    <input
                                        type="checkbox"
                                        checked={on}
                                        onChange={() =>
                                            setPicked((current) => {
                                                const next = new Set(current);
                                                on ? next.delete(item.id) : next.add(item.id);
                                                return next;
                                            })
                                        }
                                        className="h-4 w-4 accent-brand-accent"
                                    />
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium text-brand-text">{item.name}</span>
                                    <span className="shrink-0 text-xs text-brand-muted">{item.availability === target ? "already" : LABEL[item.availability]}</span>
                                </label>
                            </li>
                        );
                    })}
                </ul>

                <div className="border-t border-brand-border px-5 pb-5 pt-4">
                    <p className="text-sm text-brand-text">
                        Set {count === result.items.length ? "these" : `${count} of these`} to{" "}
                        <strong className={target === "available" ? "text-emerald-700" : "text-amber-700"}>{LABEL[target]}</strong>?
                    </p>
                    {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
                    <div className="mt-4 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            onClick={onCancel}
                            disabled={busy}
                            className="rounded-xl border border-brand-border bg-brand-card px-4 py-2.5 text-sm font-medium text-brand-text hover:bg-brand-bg disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={confirm}
                            disabled={busy || count === 0}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text hover:opacity-90 disabled:opacity-50"
                        >
                            {busy ? <LuLoaderCircle className="h-4 w-4 animate-spin" /> : <LuCheck className="h-4 w-4" />} Confirm update
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/** The full-screen assistant: mic (with rings) or type, a multi-line box, Update menu. */
function AssistantScreen({ startListening, parse, onApplied, apply, onClose }) {
    const [command, setCommand] = useState("");
    const [busy, setBusy] = useState(false);
    const [listening, setListening] = useState(false);
    const [note, setNote] = useState(null); // { tone: 'info' | 'error', text }
    const [result, setResult] = useState(null);
    const [language, setLanguage] = useState(storedLanguage);
    const recognition = useRef(null);
    const typed = useRef("");

    // Esc closes; the page behind doesn't scroll.
    useEffect(() => {
        const onKey = (e) => e.key === "Escape" && !busy && !result && onClose();
        window.addEventListener("keydown", onKey);
        const overflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";
        return () => {
            window.removeEventListener("keydown", onKey);
            document.body.style.overflow = overflow;
        };
    }, [busy, result, onClose]);

    useEffect(() => {
        if (startListening) listen();
        return () => recognition.current?.abort();
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    function listen() {
        if (!Recognition) return;
        if (listening) {
            recognition.current?.stop();
            return;
        }
        const r = new Recognition();
        r.lang = language;
        r.interimResults = true;
        r.continuous = false;
        // Speech adds to what's already in the box (a second sentence goes on a new line).
        typed.current = command.trim();
        r.onresult = (event) => {
            const heard = Array.from(event.results)
                .map((res) => res[0].transcript)
                .join(" ");
            setCommand(typed.current ? `${typed.current}\n${heard}` : heard);
        };
        r.onerror = (event) =>
            setNote({
                tone: "error",
                text: event.error === "not-allowed" ? "Allow the microphone for this site, or type it instead." : "Didn't catch that - tap the mic and try again, or type it.",
            });
        r.onend = () => setListening(false);
        recognition.current = r;
        setNote(null);
        setListening(true);
        r.start();
    }

    function pickLanguage(value) {
        setLanguage(value);
        try {
            window.localStorage.setItem(LANGUAGE_KEY, value);
        } catch {
            // ignore
        }
    }

    async function submit() {
        const said = command.trim();
        if (!said || busy) return;
        recognition.current?.stop();
        setBusy(true);
        setNote(null);
        try {
            const data = await parse(said);
            if (data.items?.length) setResult(data);
            else setNote({ tone: "info", text: data.explanation || "Nothing on the menu matches that. Try the item's name." });
        } catch (e) {
            setNote({ tone: "error", text: e?.message || "Couldn't reach the AI - switch items by hand." });
        } finally {
            setBusy(false);
        }
    }

    async function confirm(ids, availability) {
        await apply(ids, availability);
        onApplied(`${ids.length} ${ids.length === 1 ? "item" : "items"} set to ${LABEL[availability].toLowerCase()}.`);
    }

    const status = busy ? "Checking your menu…" : listening ? "Listening… speak now" : Recognition ? "Tap the mic and say it, or type below" : "Type what's sold out or back";

    return createPortal(
        <motion.div
            role="dialog"
            aria-modal="true"
            aria-label="AI menu assistant"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            className="fixed inset-0 z-[60] flex flex-col overflow-y-auto bg-[#0d1530] text-white"
        >
            {/* A slow colour wash behind everything - the "this is AI" cue. */}
            <motion.div
                aria-hidden="true"
                className="pointer-events-none fixed inset-0 opacity-40"
                style={{ background: "radial-gradient(60% 45% at 50% 30%, #7c3aed55, transparent 70%), radial-gradient(50% 40% at 80% 80%, #db277744, transparent 70%)" }}
                animate={{ opacity: [0.3, 0.5, 0.3] }}
                transition={{ duration: 4, repeat: Infinity }}
            />

            <div className="relative mx-auto flex w-full max-w-md flex-1 flex-col px-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))]">
                <div className="flex items-center justify-between gap-3">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">
                        <LuSparkles className="h-3.5 w-3.5 text-amber-300" /> AI menu assistant
                    </span>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={busy}
                        aria-label="Close"
                        className="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 disabled:opacity-40"
                    >
                        <LuX className="h-5 w-5" />
                    </button>
                </div>

                <div className="mt-6 flex flex-1 flex-col items-center text-center">
                    {busy ? <Bot thinking /> : Recognition ? <MicButton listening={listening} onClick={listen} /> : <Bot />}

                    <p className="mt-5 flex min-h-6 items-center gap-2 text-base font-semibold" role="status">
                        {status} {busy && <ThinkingDots />}
                    </p>
                    {Recognition && !busy && (
                        <select
                            value={language}
                            onChange={(e) => pickLanguage(e.target.value)}
                            disabled={listening}
                            aria-label="Language you'll speak"
                            className="mt-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs text-white/80 outline-none [&>option]:text-neutral-900"
                        >
                            {LANGUAGES.map(([code, label]) => (
                                <option key={code} value={code}>
                                    🎙 {label}
                                </option>
                            ))}
                        </select>
                    )}

                    <textarea
                        value={command}
                        onChange={(e) => setCommand(e.target.value)}
                        rows={4}
                        maxLength={200}
                        disabled={busy}
                        placeholder={"e.g. 'Chicken theernnu' or 'Disable fish items'..."}
                        aria-label="What's sold out or back"
                        className="mt-6 w-full resize-none rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-left text-base text-white outline-none placeholder:text-white/40 focus:border-fuchsia-400 focus:ring-4 focus:ring-fuchsia-500/20 disabled:opacity-60"
                    />

                    {!command && !busy && (
                        <div className="mt-3 flex flex-wrap justify-center gap-2">
                            {EXAMPLES.map((example) => (
                                <button
                                    key={example}
                                    type="button"
                                    onClick={() => setCommand(example)}
                                    className="rounded-full border border-white/15 px-3 py-1 text-xs text-white/70 hover:bg-white/10"
                                >
                                    {example}
                                </button>
                            ))}
                        </div>
                    )}

                    {note && <p className={`mt-3 text-sm ${note.tone === "error" ? "text-rose-300" : "text-white/80"}`}>{note.text}</p>}

                    <div className="mt-auto w-full pt-6">
                        <motion.button
                            type="button"
                            onClick={submit}
                            disabled={busy || !command.trim()}
                            className={`flex w-full items-center justify-center gap-2 rounded-2xl ${AI_GRADIENT} px-5 py-4 text-base font-semibold text-white shadow-lg disabled:opacity-40`}
                            animate={{ backgroundPosition: ["0% 50%", "100% 50%", "0% 50%"] }}
                            transition={{ duration: 6, repeat: Infinity, ease: "linear" }}
                        >
                            {busy ? <LuLoaderCircle className="h-5 w-5 animate-spin" /> : <LuSparkles className="h-5 w-5" />}
                            {busy ? "Checking…" : "Update menu"}
                        </motion.button>
                        <p className="mt-2 text-xs text-white/50">Nothing changes until you confirm.</p>
                    </div>
                </div>
            </div>

            {result && <ConfirmDialog result={result} onConfirm={confirm} onCancel={() => setResult(null)} />}
        </motion.div>,
        document.body,
    );
}

export default function AiStockCommand({ parse, apply, className = "" }) {
    const [open, setOpen] = useState(null); // null | { listen: bool }
    const [done, setDone] = useState(null);

    return (
        <div className={className}>
            {/* Highlighted entry: an animated gradient border so it reads as the AI. */}
            <motion.div
                className={`rounded-2xl ${AI_GRADIENT} p-[2px] shadow-sm`}
                animate={{ backgroundPosition: ["0% 50%", "100% 50%", "0% 50%"] }}
                transition={{ duration: 8, repeat: Infinity, ease: "linear" }}
            >
                <div className="flex items-center gap-3 rounded-[14px] bg-brand-card p-3">
                    <button type="button" onClick={() => setOpen({ listen: false })} className="flex min-w-0 flex-1 items-center gap-3 text-left" aria-label="Open the AI menu assistant">
                        <Bot size="sm" />
                        <span className="min-w-0">
                            <span className="flex items-center gap-1.5 text-sm font-semibold text-brand-text">
                                AI menu assistant
                                <span className="rounded-full bg-violet-500/10 px-1.5 py-px text-[10px] font-bold uppercase tracking-wide text-violet-700">AI</span>
                            </span>
                            <span className="block truncate text-xs text-brand-muted">
                                {done ?? "Say or type what's sold out - \"Chicken theernnu\""}
                            </span>
                        </span>
                    </button>
                    {Recognition && (
                        <button
                            type="button"
                            onClick={() => setOpen({ listen: true })}
                            aria-label="Speak to the AI menu assistant"
                            className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white shadow-md transition active:scale-95"
                        >
                            <LuMic className="h-5 w-5" />
                        </button>
                    )}
                </div>
            </motion.div>

            <AnimatePresence>
                {open && (
                    <AssistantScreen
                        startListening={open.listen}
                        parse={parse}
                        apply={apply}
                        onApplied={(text) => {
                            setDone(text);
                            setOpen(null);
                        }}
                        onClose={() => setOpen(null)}
                    />
                )}
            </AnimatePresence>
        </div>
    );
}
