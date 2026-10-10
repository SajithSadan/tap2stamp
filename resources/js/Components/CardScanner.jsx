import { Html5Qrcode } from "html5-qrcode";
import { useEffect, useRef, useState } from "react";
import { LuCamera, LuCheck, LuCircleAlert, LuClock, LuGift, LuLoaderCircle, LuScanLine, LuX } from "react-icons/lu";
import { stopScanner } from "@/lib/scanner";

/**
 * The customer-card scanner, shared by the staff app (Staff/Dashboard) and the
 * owner's dashboard (OwnerScanner). One scan at a time:
 *
 *   camera → (decoded) → result screen with "Scan next" (camera closed)
 *   camera → full card → "Reward ready" → "Mark reward as given" → result
 *
 * A full card is never reset by a scan alone (StampService::scan): the
 * staff member confirms, so an accidental second scan can't lose a reward.
 *
 * `request(payload, redeem)` posts the scan (returns the axios promise);
 * `onApiError(error)` may handle an error itself (return true);
 * `onScanned()` runs after every answered scan.
 */

const READER_ID = "card-scanner-reader";

function playFeedback(ok) {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        const ctx = new Ctx();
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();
        oscillator.frequency.value = ok ? 880 : 220;
        oscillator.connect(gain);
        gain.connect(ctx.destination);
        gain.gain.setValueAtTime(0.15, ctx.currentTime);
        oscillator.start();
        oscillator.stop(ctx.currentTime + 0.15);
    } catch {
        // Audio can be blocked (autoplay policy) - the vibration is enough.
    }
    if (navigator.vibrate) navigator.vibrate(ok ? 80 : [80, 60, 80]);
}

/** Stamps as dots: ●●●●○○ */
function StampDots({ stamps, max }) {
    if (typeof stamps !== "number" || !max) return null;

    return (
        <div className="mt-4 flex flex-wrap justify-center gap-1.5" aria-label={`${stamps} of ${max} stamps`}>
            {Array.from({ length: max }, (_, i) => (
                <span key={i} className={`h-3 w-3 rounded-full ${i < stamps ? "bg-brand-accent" : "bg-white/20"}`} />
            ))}
        </div>
    );
}

const bigButton = "flex w-full items-center justify-center gap-2 rounded-2xl px-5 py-4 text-base font-semibold transition disabled:opacity-60";

function ResultScreen({ result, onNext, onGiveNow }) {
    const look = {
        stamp_added: { icon: LuCheck, ring: "bg-green-500", title: result.reward_ready ? "Stamped - card full!" : "Stamped" },
        reward_redeemed: { icon: LuGift, ring: "bg-brand-accent", title: "Reward given" },
        cooldown: { icon: LuClock, ring: "bg-amber-500", title: "Already stamped" },
        no_reward: { icon: LuCircleAlert, ring: "bg-amber-500", title: "No reward to give" },
    }[result.code] ?? { icon: LuX, ring: "bg-red-500", title: "Couldn't scan" };
    const Icon = look.icon;
    const showMessage = !["stamp_added", "reward_redeemed"].includes(result.code);

    return (
        <div className="flex w-full max-w-sm flex-col items-center px-6 text-center text-white" role="status" aria-live="assertive">
            <span className={`flex h-20 w-20 items-center justify-center rounded-full ${look.ring} shadow-lg`}>
                <Icon className="h-10 w-10" strokeWidth={2.5} />
            </span>
            <h2 className="mt-5 font-heading text-2xl font-bold">{look.title}</h2>
            {result.customer_name && <p className="mt-1 text-lg text-white/85">{result.customer_name}</p>}
            {showMessage && <p className="mt-2 text-sm text-white/70">{result.message}</p>}
            {result.code === "stamp_added" && result.reward_ready && result.reward_title && (
                <p className="mt-2 text-sm text-brand-accent">Reward ready: {result.reward_title}</p>
            )}
            <StampDots stamps={result.stamps} max={result.max_stamps} />

            <div className="mt-8 w-full space-y-3">
                <button type="button" onClick={onNext} autoFocus className={`${bigButton} bg-brand-accent text-brand-accent-text`}>
                    <LuScanLine className="h-5 w-5" /> Scan next
                </button>
                {result.code === "stamp_added" && result.reward_ready && (
                    <button type="button" onClick={onGiveNow} className={`${bigButton} bg-white/10 text-white`}>
                        <LuGift className="h-5 w-5" /> Give the reward now
                    </button>
                )}
            </div>
        </div>
    );
}

/** A full card: show the reward and wait for the staff member to confirm it was handed over. */
function RewardScreen({ result, busy, onGive, onNotNow }) {
    return (
        <div className="flex w-full max-w-sm flex-col items-center px-6 text-center text-white" role="alertdialog" aria-label="Reward ready">
            <span className="flex h-20 w-20 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text shadow-lg">
                <LuGift className="h-10 w-10" />
            </span>
            <p className="mt-5 text-sm font-semibold uppercase tracking-wider text-brand-accent">Reward ready</p>
            <h2 className="mt-1 font-heading text-2xl font-bold">{result.reward_title || "Reward"}</h2>
            <p className="mt-1 text-lg text-white/85">for {result.customer_name}</p>
            <StampDots stamps={result.stamps} max={result.max_stamps} />
            <p className="mt-5 text-sm text-white/70">Hand over the reward, then mark it as given. Their card starts again from 0.</p>

            <div className="mt-6 w-full space-y-3">
                <button type="button" onClick={onGive} disabled={busy} className={`${bigButton} bg-brand-accent text-brand-accent-text`}>
                    {busy ? <LuLoaderCircle className="h-5 w-5 animate-spin" /> : <LuCheck className="h-5 w-5" />}
                    Mark reward as given
                </button>
                <button type="button" onClick={onNotNow} disabled={busy} className={`${bigButton} bg-white/10 text-white`}>
                    Not now
                </button>
            </div>
        </div>
    );
}

export default function CardScanner({ request, onApiError, onScanned, autoFocusInput = false }) {
    // camera | working | result | reward
    const [view, setView] = useState("camera");
    const [result, setResult] = useState(null);
    const [cameraError, setCameraError] = useState(false);
    const [manualPayload, setManualPayload] = useState("");
    const [redeeming, setRedeeming] = useState(false);
    const payloadRef = useRef(null);
    const handledRef = useRef(false);

    function finish(data) {
        playFeedback(data.status === "ok");
        setResult(data);
        setView(data.code === "reward_ready" ? "reward" : "result");
        onScanned?.();
    }

    async function send(payload, redeem = false) {
        payloadRef.current = payload;
        if (!redeem) setView("working");
        try {
            const { data } = await request(payload, redeem);
            finish(data);
        } catch (error) {
            if (onApiError?.(error)) return;
            const response = error.response;
            finish(
                response?.data?.code
                    ? response.data
                    : {
                          status: "error",
                          code: response?.status === 429 ? "rate_limited" : response ? "server_error" : "network_error",
                          message:
                              response?.status === 429
                                  ? "Scanning too fast. Wait a moment and try again."
                                  : response
                                    ? "Something went wrong. Try scanning again."
                                    : "Could not reach the server. Check your connection.",
                      },
            );
        }
    }

    async function giveReward() {
        setRedeeming(true);
        try {
            await send(payloadRef.current, true);
        } finally {
            setRedeeming(false);
        }
    }

    function scanNext() {
        setResult(null);
        setView("camera");
    }

    // The camera runs only on the camera screen: one decode, then it's closed until "Scan next".
    useEffect(() => {
        if (view !== "camera") return undefined;
        handledRef.current = false;
        setCameraError(false);
        const scanner = new Html5Qrcode(READER_ID);
        const started = scanner
            .start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decoded) => {
                    const payload = decoded.trim();
                    if (!payload || handledRef.current) return;
                    handledRef.current = true;
                    send(payload);
                },
                () => {},
            )
            .catch(() => setCameraError(true));

        return () => stopScanner(scanner, started);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [view]);

    function submitManual(event) {
        event.preventDefault();
        const payload = manualPayload.trim();
        if (!payload || view === "working") return;
        setManualPayload("");
        send(payload);
    }

    return (
        <>
            <div className="relative flex min-h-0 flex-1 items-center justify-center overflow-y-auto bg-black py-6">
                {view === "camera" &&
                    (cameraError ? (
                        <div className="px-6 text-center text-white">
                            <LuCamera className="mx-auto h-10 w-10 text-white/70" />
                            <p className="mt-3 font-semibold">Camera unavailable</p>
                            <p className="mt-1 text-sm text-white/70">Allow camera access for this site, or enter the QR code below.</p>
                        </div>
                    ) : (
                        <div className="w-full max-w-md">
                            <div id={READER_ID} className="w-full" />
                            <p className="mt-3 px-6 text-center text-sm text-white/70">Point the camera at the customer's card QR code.</p>
                        </div>
                    ))}
                {view === "working" && (
                    <div className="flex flex-col items-center gap-3 text-white">
                        <LuLoaderCircle className="h-8 w-8 animate-spin text-brand-accent" />
                        <p className="text-sm text-white/80">Checking the card…</p>
                    </div>
                )}
                {view === "result" && result && <ResultScreen result={result} onNext={scanNext} onGiveNow={giveReward} />}
                {view === "reward" && result && <RewardScreen result={result} busy={redeeming} onGive={giveReward} onNotNow={scanNext} />}
            </div>

            {view !== "reward" && (
                <div className="bg-brand-card px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
                    <form onSubmit={submitManual} className="mx-auto flex w-full max-w-md gap-2">
                        <input
                            type="text"
                            value={manualPayload}
                            onChange={(e) => setManualPayload(e.target.value)}
                            placeholder="Scan or enter QR code"
                            aria-label="Scan or enter customer QR code"
                            autoComplete="off"
                            autoCapitalize="off"
                            spellCheck={false}
                            autoFocus={autoFocusInput}
                            className="h-12 min-w-0 flex-1 rounded-xl border border-brand-border bg-white px-3 text-sm text-brand-text outline-none placeholder:text-brand-muted/70 focus:border-brand-accent focus:ring-4 focus:ring-brand-accent/10"
                        />
                        <button
                            type="submit"
                            disabled={!manualPayload.trim() || view === "working"}
                            className="h-12 shrink-0 rounded-xl bg-brand-accent px-4 text-sm font-semibold text-brand-accent-text transition-opacity disabled:opacity-50"
                        >
                            Submit
                        </button>
                    </form>
                </div>
            )}
        </>
    );
}
