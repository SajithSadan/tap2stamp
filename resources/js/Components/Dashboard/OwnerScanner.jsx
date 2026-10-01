import axios from 'axios';
import { Html5Qrcode } from 'html5-qrcode';
import { useEffect, useRef, useState } from 'react';
import { LuCamera, LuCheck, LuScanLine, LuX } from 'react-icons/lu';

const READER_ID = 'owner-qr-reader';
const DEBOUNCE_MS = 2000;
const DISMISS_MS = 3000;

function ResultBanner({ result }) {
    if (!result) return null;

    const success = result.status === 'ok';
    const warning = result.code === 'cooldown';
    const tone = success ? 'bg-green-600' : warning ? 'bg-amber-500' : 'bg-red-600';
    const heading = result.code === 'reward_redeemed'
        ? 'Reward redeemed!'
        : result.code === 'stamp_added' && result.reward_ready
            ? 'Stamped · reward ready!'
            : result.code === 'stamp_added'
                ? 'Stamped'
                : result.message;

    return (
        <div className={`absolute inset-x-0 top-0 z-10 px-5 pb-4 pt-[max(1rem,env(safe-area-inset-top))] text-center text-white shadow-md ${tone}`}>
            <p className="text-lg font-bold">{heading}</p>
            {result.customer_name && <p className="mt-0.5 text-sm opacity-90">{result.customer_name}{typeof result.stamps === 'number' && ` · ${result.stamps}/${result.max_stamps}`}</p>}
        </div>
    );
}

export default function OwnerScanner({ onClose }) {
    const [cameraError, setCameraError] = useState(false);
    const [result, setResult] = useState(null);
    const [manualPayload, setManualPayload] = useState('');
    const [busy, setBusy] = useState(false);
    const busyRef = useRef(false);
    const lastScanRef = useRef({ payload: null, time: 0 });
    const dismissTimerRef = useRef(null);

    function showResult(data) {
        setResult(data);
        if (dismissTimerRef.current) clearTimeout(dismissTimerRef.current);
        dismissTimerRef.current = setTimeout(() => setResult(null), DISMISS_MS);
    }

    async function submitScan(rawPayload) {
        const payload = rawPayload.trim();
        const now = Date.now();
        if (!payload || busyRef.current) return;
        if (payload === lastScanRef.current.payload && now - lastScanRef.current.time < DEBOUNCE_MS) return;

        lastScanRef.current = { payload, time: now };
        busyRef.current = true;
        setBusy(true);

        try {
            const { data } = await axios.post('/dashboard/scan', { payload });
            showResult(data);
        } catch (error) {
            const response = error.response;
            showResult(response?.data?.code
                ? response.data
                : { status: 'error', message: response?.status === 429 ? 'Scanning too fast. Wait a moment and try again.' : 'Could not reach the server. Check your connection.' });
        } finally {
            busyRef.current = false;
            setBusy(false);
        }
    }

    useEffect(() => {
        let cancelled = false;
        const scanner = new Html5Qrcode(READER_ID);

        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (decodedText) => submitScan(decodedText),
            () => {},
        ).catch(() => {
            if (!cancelled) setCameraError(true);
        });

        return () => {
            cancelled = true;
            if (dismissTimerRef.current) clearTimeout(dismissTimerRef.current);
            scanner.stop().then(() => scanner.clear()).catch(() => {});
        };
        // Scanner is started once when this overlay mounts.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function submitManual(event) {
        event.preventDefault();
        const payload = manualPayload;
        setManualPayload('');
        submitScan(payload);
    }

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-black" role="dialog" aria-modal="true" aria-label="Scan customer card">
            <header className="flex items-center justify-between bg-brand-deep px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))] text-white">
                <div className="flex items-center gap-2"><LuScanLine className="h-5 w-5 text-brand-accent" /><h2 className="font-semibold">Scan customer card</h2></div>
                <button type="button" onClick={onClose} aria-label="Close scanner" className="rounded-lg p-2 text-white/80 hover:bg-white/10"><LuX className="h-5 w-5" /></button>
            </header>

            <div className="relative flex min-h-0 flex-1 items-center justify-center">
                <ResultBanner result={result} />
                {cameraError ? (
                    <div className="px-6 text-center text-white">
                        <LuCamera className="mx-auto h-10 w-10 text-white/70" />
                        <p className="mt-3 font-semibold">Camera unavailable</p>
                        <p className="mt-1 text-sm text-white/70">Allow camera access, or enter the QR code below.</p>
                    </div>
                ) : <div id={READER_ID} className="w-full max-w-md" />}
            </div>

            <div className="bg-brand-card px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
                <form onSubmit={submitManual} className="mx-auto flex w-full max-w-md gap-2">
                    <input
                        type="text"
                        value={manualPayload}
                        onChange={(event) => setManualPayload(event.target.value)}
                        placeholder="Scan or enter QR code"
                        aria-label="Scan or enter customer QR code"
                        autoComplete="off"
                        autoCapitalize="off"
                        spellCheck={false}
                        className="h-12 min-w-0 flex-1 rounded-xl border border-brand-border bg-white px-3 text-sm text-brand-text outline-none placeholder:text-brand-muted/70 focus:border-brand-accent focus:ring-4 focus:ring-brand-accent/10"
                    />
                    <button type="submit" disabled={busy || !manualPayload.trim()} className="inline-flex h-12 shrink-0 items-center gap-2 rounded-xl bg-brand-accent px-4 text-sm font-semibold text-brand-accent-text disabled:opacity-50">
                        {busy ? <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" /> : <LuCheck className="h-4 w-4" />}
                        Submit
                    </button>
                </form>
            </div>
        </div>
    );
}
