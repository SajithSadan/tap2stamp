import { router } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useRef, useState } from "react";
import { FaWhatsapp } from "react-icons/fa6";
import { LuArrowLeft, LuCheck, LuCheckCheck, LuCircleAlert, LuSend, LuTag } from "react-icons/lu";
import { EmptyState, FieldError, inputClass, Panel, primaryButton } from "@/Components/Dashboard/Ui";

/**
 * Owner → WhatsApp → Replies (App\Services\WhatsAppInbox): customers'
 * messages to the shop's number with the offers they were sent, and the
 * owner's replies. Chats come with the page; one chat at a time as JSON.
 * Both refresh every 20 s while the page is open.
 */

const POLL_MS = 20000;

function Tick({ status, error }) {
    if (status === "failed") {
        return (
            <span className="inline-flex items-center gap-1 text-red-600" title={error ?? undefined}>
                <LuCircleAlert className="h-3.5 w-3.5" /> Not delivered
            </span>
        );
    }
    if (status === "read") return <LuCheckCheck className="h-3.5 w-3.5 text-[#53BDEB]" aria-label="Read" />;
    if (status === "delivered") return <LuCheckCheck className="h-3.5 w-3.5" aria-label="Delivered" />;

    return <LuCheck className="h-3.5 w-3.5" aria-label="Sent" />;
}

function Bubble({ m }) {
    const out = m.direction === "out";

    return (
        <li className={`flex ${out ? "justify-end" : "justify-start"}`}>
            <div className={`max-w-[85%] rounded-xl px-3 py-2 text-sm sm:max-w-[70%] ${out ? "bg-brand-accent/10 text-brand-text" : "bg-brand-bg text-brand-text"}`}>
                {m.kind === "offer" && (
                    <p className="mb-1 inline-flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-brand-muted">
                        <LuTag className="h-3 w-3" /> Offer
                    </p>
                )}
                {m.poster_url && <img src={m.poster_url} alt="" className="mb-1.5 max-h-48 rounded-lg object-cover" />}
                <p className="whitespace-pre-line break-words">{m.body}</p>
                <p className="mt-1 flex items-center justify-end gap-1.5 text-[11px] text-brand-muted">
                    {m.time}
                    {out && <Tick status={m.status} error={m.error} />}
                </p>
            </div>
        </li>
    );
}

function Thread({ chat, replyLimit, onBack, onSent }) {
    const [text, setText] = useState("");
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);
    const endRef = useRef(null);

    useEffect(() => endRef.current?.scrollIntoView({ block: "end" }), [chat.messages.length]);

    async function send(event) {
        event.preventDefault();
        if (!text.trim()) return;
        setSending(true);
        setError(null);
        try {
            const { data } = await axios.post("/dashboard/whatsapp/chat", { phone: chat.phone, body: text });
            setText("");
            onSent(data);
        } catch (e) {
            setError(e.response?.data?.errors?.body?.[0] ?? e.response?.data?.message ?? "Couldn't send. Check your connection and try again.");
        } finally {
            setSending(false);
        }
    }

    return (
        <div className="flex min-h-[28rem] min-w-0 flex-col">
            <div className="flex items-center gap-3 border-b border-brand-border px-4 py-3">
                <button type="button" onClick={onBack} aria-label="Back to chats" className="text-brand-muted hover:text-brand-text lg:hidden">
                    <LuArrowLeft className="h-5 w-5" />
                </button>
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-brand-text">{chat.name ?? chat.phone}</p>
                    <p className="truncate text-xs text-brand-muted">
                        {chat.name ? `${chat.phone} · ` : ""}
                        {chat.customer ? (chat.opted_in ? "Customer · gets offers" : "Customer · no offers") : "Not a customer"}
                    </p>
                </div>
            </div>

            <ul className="max-h-[32rem] flex-1 space-y-2 overflow-y-auto px-4 py-4">
                {chat.messages.map((m) => (
                    <Bubble key={m.id} m={m} />
                ))}
                <li ref={endRef} aria-hidden="true" />
            </ul>

            <form onSubmit={send} className="border-t border-brand-border px-4 py-3">
                {chat.can_reply ? (
                    <>
                        <div className="flex items-end gap-2">
                            <label htmlFor="wa-reply" className="sr-only">
                                Reply
                            </label>
                            <textarea
                                id="wa-reply"
                                rows={1}
                                maxLength={replyLimit}
                                value={text}
                                onChange={(e) => setText(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === "Enter" && !e.shiftKey) send(e);
                                }}
                                placeholder="Write a reply"
                                className={`${inputClass} max-h-32 resize-none`}
                            />
                            <button type="submit" disabled={sending || !text.trim()} className={primaryButton} aria-label="Send reply">
                                <LuSend className="h-4 w-4" />
                            </button>
                        </div>
                        <p className="mt-1.5 text-xs text-brand-muted">You can reply until {chat.reply_until}.</p>
                        <FieldError message={error} />
                    </>
                ) : (
                    <p className="text-xs text-brand-muted">WhatsApp only allows a reply within 24 hours of their last message.</p>
                )}
            </form>
        </div>
    );
}

export default function WhatsAppChats({ chats, account, replyLimit }) {
    const [phone, setPhone] = useState(null);
    const [chat, setChat] = useState(null);
    const [loadError, setLoadError] = useState(null);

    async function load(p, { quiet = false } = {}) {
        try {
            const { data } = await axios.get("/dashboard/whatsapp/chat", { params: { phone: p } });
            setChat(data);
            setLoadError(null);
            if (!quiet) router.reload({ only: ["chats", "unreadChats"] });
        } catch {
            if (!quiet) setLoadError("Couldn't open this chat. Try again.");
        }
    }

    function open(p) {
        setPhone(p);
        setChat(null);
        load(p);
    }

    // New messages: the list and the open chat, while the tab is visible.
    useEffect(() => {
        const timer = setInterval(() => {
            if (document.hidden) return;
            router.reload({ only: ["chats", "unreadChats"] });
            if (phone) load(phone, { quiet: true });
        }, POLL_MS);

        return () => clearInterval(timer);
    }, [phone]);

    if (chats.length === 0) {
        return (
            <Panel title="Replies" bodyClassName="">
                <EmptyState icon={FaWhatsapp} title="No replies yet">
                    {account && !account.webhooks
                        ? "Replies show here for numbers connected with Facebook. With your own keys, they go to your Meta app."
                        : "When customers reply to an offer, their messages show here."}
                </EmptyState>
            </Panel>
        );
    }

    return (
        <Panel bodyClassName="">
            <div className="grid lg:grid-cols-[18rem_minmax(0,1fr)]">
                <ul className={`divide-y divide-brand-border lg:max-h-[38rem] lg:overflow-y-auto lg:border-r lg:border-brand-border ${phone ? "hidden lg:block" : ""}`}>
                    {chats.map((c) => (
                        <li key={c.phone}>
                            <button
                                type="button"
                                onClick={() => open(c.phone)}
                                className={`flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-brand-bg ${phone === c.phone ? "bg-brand-bg" : ""}`}
                            >
                                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#25D366]/15 text-sm font-semibold text-[#128C7E]">
                                    {(c.name ?? "#").trim().charAt(0).toUpperCase()}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="flex items-baseline justify-between gap-2">
                                        <span className={`truncate text-sm ${c.unread ? "font-semibold text-brand-text" : "font-medium text-brand-text"}`}>
                                            {c.name ?? c.phone}
                                        </span>
                                        <span className={`shrink-0 text-[11px] ${c.unread ? "font-semibold text-[#128C7E]" : "text-brand-muted"}`}>{c.at}</span>
                                    </span>
                                    <span className="flex items-center justify-between gap-2">
                                        <span className="truncate text-xs text-brand-muted">{c.last}</span>
                                        {c.unread > 0 && (
                                            <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-[#25D366] px-1.5 text-[11px] font-semibold tabular-nums text-white">
                                                {c.unread}
                                            </span>
                                        )}
                                    </span>
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>

                <div className={phone ? "" : "hidden lg:block"}>
                    {chat ? (
                        <Thread
                            chat={chat}
                            replyLimit={replyLimit}
                            onBack={() => {
                                setPhone(null);
                                setChat(null);
                            }}
                            onSent={setChat}
                        />
                    ) : loadError ? (
                        <div className="px-4 py-10">
                            <FieldError message={loadError} />
                        </div>
                    ) : phone ? (
                        <p className="px-4 py-10 text-center text-sm text-brand-muted">Loading…</p>
                    ) : (
                        <EmptyState icon={FaWhatsapp} title="Pick a chat" />
                    )}
                </div>
            </div>
        </Panel>
    );
}
