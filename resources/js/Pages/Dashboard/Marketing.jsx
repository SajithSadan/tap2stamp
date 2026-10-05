import { router, useForm } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useRef, useState } from "react";
import { FaWhatsapp } from "react-icons/fa6";
import { LuCheckCheck, LuClock, LuExternalLink, LuImagePlus, LuSend, LuTrash2, LuUsers } from "react-icons/lu";
import { useConfirm } from "@/Components/ConfirmDialog";
import OwnerLayout from "@/Components/Dashboard/OwnerLayout";
import { EmptyState, FieldError, inputClass, Panel, primaryButton, secondaryButton, StatTile } from "@/Components/Dashboard/Ui";

const SAMPLE_NAME = "Sam";

function audienceOptions(activeDays) {
    return [
        { key: "all", label: "Everyone", hint: "All opted-in customers" },
        { key: "active", label: "Regulars", hint: `Visited in the last ${activeDays} days` },
        { key: "lapsed", label: "Win back", hint: `Not seen for ${activeDays}+ days` },
    ];
}

/**
 * Phone photos can be huge; WhatsApp takes JPG/PNG up to 5 MB. Big ones are
 * re-saved as a ≤ 1600 px JPEG (on white, for transparent PNGs).
 */
async function preparePoster(file) {
    if (file.size <= 1.5 * 1024 * 1024) return file;

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, 1600 / Math.max(bitmap.width, bitmap.height));
        const canvas = document.createElement("canvas");
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.88));

        return blob ? new File([blob], file.name.replace(/\.\w+$/, "") + ".jpg", { type: "image/jpeg" }) : file;
    } catch {
        return file;
    }
}

/** Object URL for a picked file, released when it changes. */
function useFileUrl(file) {
    const [url, setUrl] = useState(null);

    useEffect(() => {
        if (!file) return setUrl(null);
        const objectUrl = URL.createObjectURL(file);
        setUrl(objectUrl);
        return () => URL.revokeObjectURL(objectUrl);
    }, [file]);

    return url;
}

function PosterPicker({ file, url, onChange, error }) {
    const inputRef = useRef(null);
    const [preparing, setPreparing] = useState(false);

    async function pick(picked) {
        if (!picked) return;
        setPreparing(true);
        onChange(await preparePoster(picked));
        setPreparing(false);
    }

    return (
        <div>
            <p className="mb-1.5 text-sm font-medium text-brand-text">
                Poster <span className="font-normal text-brand-muted">(optional)</span>
            </p>
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png"
                className="hidden"
                onChange={(e) => {
                    pick(e.target.files[0]);
                    e.target.value = "";
                }}
            />
            {file && url ? (
                <div className="flex items-center gap-3">
                    <img src={url} alt="Poster" className="h-20 w-20 rounded-xl border border-brand-border object-cover" />
                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={() => inputRef.current?.click()} className={secondaryButton}>
                            Change
                        </button>
                        <button type="button" onClick={() => onChange(null)} className={secondaryButton}>
                            <LuTrash2 className="h-4 w-4" /> Remove
                        </button>
                    </div>
                </div>
            ) : (
                <button
                    type="button"
                    disabled={preparing}
                    onClick={() => inputRef.current?.click()}
                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-brand-border py-4 text-sm font-medium text-brand-muted transition-colors hover:border-brand-accent hover:text-brand-text"
                >
                    <LuImagePlus className="h-4 w-4" /> {preparing ? "Preparing…" : "Add a poster · JPG or PNG"}
                </button>
            )}
            <FieldError message={error} />
        </div>
    );
}

/** The approved template with the owner's words in it - what customers will see. */
function WhatsAppPreview({ templateBody, shopName, message, posterUrl }) {
    const filled = templateBody
        .replace("{{1}}", SAMPLE_NAME)
        .replace("{{2}}", shopName)
        .replace("{{3}}", message.trim().replace(/\s+/g, " ") || "Your message appears here.");

    return (
        <div className="overflow-hidden rounded-[1.75rem] border-[6px] border-neutral-900">
            <div className="flex items-center gap-2.5 bg-[#075E54] px-4 py-3 text-white">
                <FaWhatsapp className="h-5 w-5" />
                <span className="text-sm font-semibold">Preview</span>
            </div>
            <div className="min-h-[340px] bg-[#EFEAE2] px-3 py-4">
                <div className="max-w-[88%]">
                    <div className={`rounded-lg rounded-tl-none bg-white text-[13.5px] leading-snug text-[#111B21] ${posterUrl ? "p-1 pb-1.5" : "px-3 pb-1.5 pt-2"}`}>
                        {posterUrl && <img src={posterUrl} alt="" className="mb-1.5 max-h-72 w-full rounded-md object-cover" />}
                        <p className={`whitespace-pre-line break-words ${posterUrl ? "px-2" : ""}`}>{filled}</p>
                        <p className={`mt-1 text-right text-[10px] text-[#667781] ${posterUrl ? "px-2" : ""}`}>12:00</p>
                    </div>
                    <div className="mt-0.5 flex items-center justify-center gap-1.5 rounded-lg bg-white py-2 text-[13.5px] font-medium text-[#027EB5]">
                        <LuExternalLink className="h-3.5 w-3.5" /> Unsubscribe
                    </div>
                </div>
            </div>
        </div>
    );
}

/** Sends an unfinished campaign, batch by batch, while this page is open. */
function useCampaignSender(campaign) {
    const [progress, setProgress] = useState(null);
    const [error, setError] = useState(null);
    const [attempt, setAttempt] = useState(0);

    useEffect(() => {
        if (!campaign) return;

        let stopped = false;
        setError(null);
        setProgress({ sent: campaign.sent, failed: campaign.failed, total: campaign.recipients });

        (async () => {
            try {
                while (!stopped) {
                    const { data } = await axios.post(`/dashboard/marketing/${campaign.id}/send`);
                    if (stopped) return;
                    setProgress(data);
                    if (data.done) {
                        router.reload({ only: ["campaigns", "sentThisMonth", "audiences"] });
                        return;
                    }
                }
            } catch (e) {
                if (!stopped) setError(e.response?.data?.message ?? "Sending paused - check your connection.");
            }
        })();

        return () => {
            stopped = true;
        };
    }, [campaign?.id, attempt]);

    return { progress, error, retry: () => setAttempt((n) => n + 1) };
}

function ProgressBar({ value, total }) {
    return (
        <div className="h-2 overflow-hidden rounded-full bg-brand-bg">
            <div className="h-full rounded-full bg-brand-accent transition-all" style={{ width: `${total ? (value / total) * 100 : 0}%` }} />
        </div>
    );
}

export default function Marketing({
    shop,
    audiences,
    customersCount,
    sentThisMonth,
    campaigns,
    nextAllowedAt,
    whatsappReady,
    templateBody,
    maxMessage,
    activeDays,
}) {
    const form = useForm({ audience: "all", message: "", poster: null });
    const posterUrl = useFileUrl(form.data.poster);
    const [confirm, confirmDialog] = useConfirm();
    const sending = campaigns.find((c) => c.sending) ?? null;
    const { progress, error: sendError, retry } = useCampaignSender(sending);
    const options = audienceOptions(activeDays);
    const recipients = audiences[form.data.audience] ?? 0;
    const tooShort = form.data.message.trim().length < 10;

    const blocked = !whatsappReady
        ? "WhatsApp sending isn't switched on for your shop yet."
        : nextAllowedAt
          ? `One message a day keeps customers happy. Your next one can go after ${nextAllowedAt}.`
          : null;

    async function send() {
        const ok = await confirm({
            title: `Send to ${recipients} ${recipients === 1 ? "customer" : "customers"}?`,
            message: "It goes out on WhatsApp straight away and can't be taken back.",
            confirmLabel: "Send now",
        });
        if (!ok) return;

        form.post("/dashboard/marketing", { preserveScroll: true, onSuccess: () => form.reset("message", "poster") });
    }

    const audienceLabel = (key) => options.find((o) => o.key === key)?.label ?? key;

    return (
        <OwnerLayout shop={shop} title="WhatsApp" description="Send offers to customers who opted in.">
            {confirmDialog}

            <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <StatTile compact icon={LuUsers} label="Opted in" value={audiences.all} hint={`of ${customersCount} customers`} />
                <StatTile compact icon={LuCheckCheck} label="Sent this month" value={sentThisMonth} />
                <StatTile compact icon={LuClock} label="Next message" value={nextAllowedAt ? "Waiting" : "Ready"} hint={nextAllowedAt ? `After ${nextAllowedAt}` : "You can send now"} />
            </div>

            <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <Panel title="New message">
                    {sending && progress ? (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between text-sm">
                                <span className="font-medium text-brand-text">
                                    {sendError ? "Sending paused" : "Sending… keep this page open"}
                                </span>
                                <span className="tabular-nums text-brand-muted">
                                    {progress.sent + progress.failed} / {progress.total}
                                </span>
                            </div>
                            <ProgressBar value={progress.sent + progress.failed} total={progress.total} />
                            {sendError && (
                                <div className="flex items-center gap-3">
                                    <FieldError message={sendError} />
                                    <button type="button" onClick={retry} className={secondaryButton}>
                                        Continue
                                    </button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-5">
                            <fieldset>
                                <legend className="mb-2 text-sm font-medium text-brand-text">Send to</legend>
                                <div className="grid gap-2 sm:grid-cols-3">
                                    {options.map((o) => {
                                        const selected = form.data.audience === o.key;

                                        return (
                                            <label
                                                key={o.key}
                                                className={`cursor-pointer rounded-xl border px-3.5 py-3 transition-colors ${
                                                    selected ? "border-brand-accent bg-brand-accent/5" : "border-brand-border hover:bg-brand-bg"
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="audience"
                                                    value={o.key}
                                                    checked={selected}
                                                    onChange={() => form.setData("audience", o.key)}
                                                    className="sr-only"
                                                />
                                                <span className="flex items-baseline justify-between gap-2">
                                                    <span className="text-sm font-semibold text-brand-text">{o.label}</span>
                                                    <span className="text-sm tabular-nums text-brand-muted">{audiences[o.key]}</span>
                                                </span>
                                                <span className="mt-0.5 block text-xs text-brand-muted">{o.hint}</span>
                                            </label>
                                        );
                                    })}
                                </div>
                                <FieldError message={form.errors.audience} />
                            </fieldset>

                            <div>
                                <div className="mb-1.5 flex items-baseline justify-between">
                                    <label htmlFor="message" className="text-sm font-medium text-brand-text">
                                        Message
                                    </label>
                                    <span className={`text-xs tabular-nums ${form.data.message.length > maxMessage ? "text-red-600" : "text-brand-muted"}`}>
                                        {form.data.message.length}/{maxMessage}
                                    </span>
                                </div>
                                <textarea
                                    id="message"
                                    rows={5}
                                    maxLength={maxMessage}
                                    value={form.data.message}
                                    onChange={(e) => form.setData("message", e.target.value)}
                                    placeholder="e.g. Half-price cakes after 3pm this Friday - show this message at the till."
                                    className={`${inputClass} resize-y`}
                                />
                                <p className="mt-1 text-xs text-brand-muted">One paragraph. The greeting and Unsubscribe button are added for you.</p>
                                <FieldError message={form.errors.message} />
                            </div>

                            <PosterPicker
                                file={form.data.poster}
                                url={posterUrl}
                                onChange={(file) => form.setData("poster", file)}
                                error={form.errors.poster}
                            />

                            {blocked && <p className="rounded-xl bg-amber-500/10 px-4 py-3 text-sm text-amber-800">{blocked}</p>}

                            <button
                                type="button"
                                onClick={send}
                                disabled={Boolean(blocked) || recipients === 0 || tooShort || form.processing}
                                className={primaryButton}
                            >
                                <LuSend className="h-4 w-4" />
                                {form.processing ? "Starting…" : `Send to ${recipients} ${recipients === 1 ? "customer" : "customers"}`}
                            </button>
                        </div>
                    )}
                </Panel>

                <WhatsAppPreview
                    templateBody={templateBody}
                    shopName={shop.name}
                    message={sending ? sending.message : form.data.message}
                    posterUrl={sending ? sending.poster_url : posterUrl}
                />
            </div>

            <Panel title="Sent" className="mt-4" bodyClassName="">
                {campaigns.length === 0 ? (
                    <EmptyState icon={FaWhatsapp} title="Nothing sent yet">
                        Your sent messages show here.
                    </EmptyState>
                ) : (
                    <ul className="divide-y divide-brand-border">
                        {campaigns.map((c) => (
                            <li key={c.id} className="flex flex-wrap items-start gap-x-4 gap-y-1 px-5 py-3.5">
                                {c.poster_url && (
                                    <img src={c.poster_url} alt="" className="h-12 w-12 shrink-0 rounded-lg border border-brand-border object-cover" />
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="line-clamp-2 text-sm text-brand-text">{c.message}</p>
                                    <p className="mt-0.5 text-xs text-brand-muted">
                                        {c.date} · {audienceLabel(c.audience)}
                                    </p>
                                </div>
                                <p className="shrink-0 text-sm tabular-nums">
                                    {c.sending ? (
                                        <span className="text-brand-muted">Sending…</span>
                                    ) : (
                                        <>
                                            <span className="text-brand-text">{c.sent} sent</span>
                                            {c.failed > 0 && <span className="text-red-600"> · {c.failed} failed</span>}
                                        </>
                                    )}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </Panel>
        </OwnerLayout>
    );
}
