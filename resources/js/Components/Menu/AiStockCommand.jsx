import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { LuCheck, LuLoaderCircle, LuMic, LuMicOff, LuSparkles, LuX } from "react-icons/lu";

/**
 * "Chicken theernnu" / "Disable fish items" / "Meals available now": type or
 * say it, Gemini (MenuCommandParser) picks the items, and a dialog asks to
 * confirm before anything changes. Shared by the menu editor and the staff
 * app's Menu tab, which pass:
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

const Recognition = typeof window !== "undefined" ? window.SpeechRecognition || window.webkitSpeechRecognition : null;

function storedLanguage() {
    try {
        return window.localStorage.getItem(LANGUAGE_KEY) || "en-GB";
    } catch {
        return "en-GB";
    }
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

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/50 backdrop-blur-sm sm:items-center sm:p-4" onClick={busy ? undefined : onCancel}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Confirm menu update"
                onClick={(e) => e.stopPropagation()}
                className="flex max-h-[85dvh] w-full flex-col rounded-t-2xl bg-brand-card shadow-xl sm:max-w-md sm:rounded-2xl"
            >
                <div className="flex items-start justify-between gap-3 px-5 pt-5">
                    <div>
                        <h2 className="font-heading text-lg font-semibold text-brand-text">
                            Gemini matched {result.items.length} {result.items.length === 1 ? "item" : "items"}
                        </h2>
                        {result.explanation && <p className="mt-0.5 text-sm text-brand-muted">{result.explanation}</p>}
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
                                        className="h-4 w-4 accent-[var(--color-brand-accent)]"
                                    />
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium text-brand-text">{item.name}</span>
                                    <span className="shrink-0 text-xs text-brand-muted">
                                        {item.availability === target ? "already" : LABEL[item.availability]}
                                    </span>
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
        </div>,
        document.body,
    );
}

export default function AiStockCommand({ parse, apply, className = "" }) {
    const [command, setCommand] = useState("");
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState(null); // { tone: 'info' | 'error' | 'done', text }
    const [result, setResult] = useState(null);
    const [listening, setListening] = useState(false);
    const [language, setLanguage] = useState(storedLanguage);
    const recognition = useRef(null);

    useEffect(() => () => recognition.current?.abort(), []);

    async function submit(text = command) {
        const said = text.trim();
        if (!said || busy) return;
        setBusy(true);
        setMessage(null);
        try {
            const data = await parse(said);
            if (!data.items?.length) {
                setMessage({ tone: "info", text: data.explanation || "Nothing on the menu matches that. Try the item's name." });
            } else {
                setResult(data);
            }
        } catch (e) {
            setMessage({ tone: "error", text: e?.message || "Couldn't reach the AI - switch items by hand." });
        } finally {
            setBusy(false);
        }
    }

    function listen() {
        if (!Recognition) return;
        if (listening) {
            recognition.current?.stop();
            return;
        }
        const r = new Recognition();
        r.lang = language;
        r.interimResults = true;
        r.maxAlternatives = 1;
        let finalText = "";
        r.onresult = (event) => {
            const text = Array.from(event.results)
                .map((res) => res[0].transcript)
                .join(" ");
            setCommand(text);
            if (event.results[event.results.length - 1].isFinal) finalText = text;
        };
        r.onerror = (event) =>
            setMessage({
                tone: "error",
                text: event.error === "not-allowed" ? "Allow the microphone for this site to speak a command." : "Didn't catch that - try again or type it.",
            });
        r.onend = () => {
            setListening(false);
            // Spoken commands go straight to the AI (nothing changes until confirmed).
            if (finalText) submit(finalText);
        };
        recognition.current = r;
        setMessage(null);
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

    async function confirm(ids, availability) {
        await apply(ids, availability);
        setResult(null);
        setCommand("");
        setMessage({ tone: "done", text: `${ids.length} ${ids.length === 1 ? "item" : "items"} set to ${LABEL[availability].toLowerCase()}.` });
    }

    return (
        <div className={className}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
                className="flex items-center gap-1.5 rounded-xl border border-brand-border bg-brand-card p-1.5 focus-within:border-brand-accent focus-within:ring-4 focus-within:ring-brand-accent/10"
            >
                <LuSparkles className="ml-1.5 h-4 w-4 shrink-0 text-brand-accent" aria-hidden="true" />
                <input
                    type="text"
                    value={command}
                    onChange={(e) => setCommand(e.target.value)}
                    maxLength={200}
                    placeholder={listening ? "Listening…" : "e.g. 'Chicken theernnu' or 'Disable fish items'..."}
                    aria-label="Tell the AI what's sold out or back"
                    className="min-w-0 flex-1 bg-transparent px-1 py-1.5 text-sm text-brand-text outline-none placeholder:text-brand-muted/70"
                />
                {Recognition && (
                    <button
                        type="button"
                        onClick={listen}
                        aria-label={listening ? "Stop listening" : "Speak a command"}
                        title={listening ? "Stop" : "Speak"}
                        className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition-colors ${
                            listening ? "animate-pulse bg-red-500 text-white" : "text-brand-muted hover:bg-brand-bg hover:text-brand-text"
                        }`}
                    >
                        {listening ? <LuMicOff className="h-4 w-4" /> : <LuMic className="h-4 w-4" />}
                    </button>
                )}
                <button
                    type="submit"
                    disabled={busy || !command.trim()}
                    className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg bg-brand-accent px-3 text-sm font-semibold text-brand-accent-text disabled:opacity-40"
                >
                    {busy ? <LuLoaderCircle className="h-4 w-4 animate-spin" aria-label="Asking the AI" /> : "Go"}
                </button>
            </form>

            <div className="mt-1.5 flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-1 text-xs">
                <span
                    role="status"
                    className={
                        message?.tone === "error" ? "text-red-600" : message?.tone === "done" ? "font-medium text-emerald-700" : "text-brand-muted"
                    }
                >
                    {busy ? "Asking the AI…" : (message?.text ?? "Type or speak it - you'll confirm before anything changes.")}
                </span>
                {Recognition && (
                    <select
                        value={language}
                        onChange={(e) => pickLanguage(e.target.value)}
                        aria-label="Voice language"
                        className="rounded-md bg-transparent py-0.5 text-xs text-brand-muted outline-none hover:text-brand-text"
                    >
                        {LANGUAGES.map(([code, label]) => (
                            <option key={code} value={code}>
                                🎙 {label}
                            </option>
                        ))}
                    </select>
                )}
            </div>

            {result && <ConfirmDialog result={result} onConfirm={confirm} onCancel={() => setResult(null)} />}
        </div>
    );
}
