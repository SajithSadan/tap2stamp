import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Html5Qrcode } from 'html5-qrcode';
import { useEffect, useRef, useState } from 'react';
import { CameraIcon, StoreIcon } from '@/Components/Icons';
import { STAFF_TOKEN_KEY } from '@/lib/storage';

const READER_ID = 'staff-qr-reader';
const DEBOUNCE_MS = 2000;
const DISMISS_MS = 3000;

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
        // Audio can be blocked (autoplay policy, unsupported browser) -
        // vibration below is enough of a fallback, no need to surface this.
    }

    if (navigator.vibrate) {
        navigator.vibrate(ok ? 80 : [80, 60, 80]);
    }
}

function resultTone(result) {
    if (!result) return null;
    if (result.status === 'ok') return 'success';
    if (result.code === 'cooldown') return 'warning';

    return 'error';
}

const toneClasses = {
    success: 'bg-green-600 text-white',
    warning: 'bg-amber-500 text-white',
    error: 'bg-red-600 text-white',
};

function ResultBanner({ result }) {
    const tone = resultTone(result);
    if (!tone) return null;

    const heading =
        result.code === 'reward_redeemed'
            ? 'Reward redeemed!'
            : result.code === 'stamp_added' && result.reward_ready
              ? 'Stamped — reward ready!'
              : result.code === 'stamp_added'
                ? 'Stamped'
                : result.message;

    return (
        <div className={`fixed inset-x-0 top-0 z-20 px-5 py-4 text-center shadow-md ${toneClasses[tone]}`}>
            <p className="text-lg font-bold">{heading}</p>
            {result.customer_name && (
                <p className="mt-0.5 text-sm opacity-90">
                    {result.customer_name}
                    {typeof result.stamps === 'number' && ` · ${result.stamps}/${result.max_stamps}`}
                </p>
            )}
            {!result.customer_name && result.code !== 'stamp_added' && result.code !== 'reward_redeemed' && (
                <p className="mt-0.5 text-sm opacity-90">{result.message}</p>
            )}
        </div>
    );
}

export default function Scanner() {
    const [token] = useState(() => {
        try {
            return window.localStorage.getItem(STAFF_TOKEN_KEY);
        } catch {
            return null;
        }
    });

    const [authState, setAuthState] = useState('loading'); // loading | ready | unauthenticated
    const [shopName, setShopName] = useState(null);
    const [deviceName, setDeviceName] = useState(null);
    const [cameraError, setCameraError] = useState(false);
    const [result, setResult] = useState(null);
    const [stampsToday, setStampsToday] = useState(null);

    const scannerRef = useRef(null);
    const lastScanRef = useRef({ payload: null, time: 0 });
    const dismissTimerRef = useRef(null);
    const busyRef = useRef(false);

    const authHeaders = token ? { Authorization: `Bearer ${token}` } : {};

    useEffect(() => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(() => {});
        }
    }, []);

    function refreshSummary() {
        axios
            .get('/api/staff/summary', { headers: authHeaders })
            .then(({ data }) => setStampsToday(data.stamps_today))
            .catch(() => {});
    }

    useEffect(() => {
        if (!token) {
            setAuthState('unauthenticated');
            return;
        }

        axios
            .get('/api/staff/me', { headers: authHeaders })
            .then(({ data }) => {
                setShopName(data.shop_name);
                setDeviceName(data.device_name);
                setAuthState('ready');
            })
            .catch(() => {
                window.localStorage.removeItem(STAFF_TOKEN_KEY);
                setAuthState('unauthenticated');
            });
    }, [token]);

    useEffect(() => {
        if (authState === 'ready') refreshSummary();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [authState]);

    function showResult(data) {
        setResult(data);
        playFeedback(data.status === 'ok');

        if (dismissTimerRef.current) clearTimeout(dismissTimerRef.current);
        dismissTimerRef.current = setTimeout(() => setResult(null), DISMISS_MS);
    }

    function submitScan(payload) {
        if (busyRef.current) return;
        busyRef.current = true;

        axios
            .post('/api/staff/scan', { payload }, { headers: authHeaders })
            .then(({ data }) => {
                showResult(data);
                refreshSummary();
            })
            .catch((error) => {
                showResult(
                    error.response?.data ?? {
                        status: 'error',
                        code: 'network_error',
                        message: 'Could not reach the server — check your connection.',
                    },
                );
            })
            .finally(() => {
                busyRef.current = false;
            });
    }

    function onDecode(decodedText) {
        const now = Date.now();

        if (decodedText === lastScanRef.current.payload && now - lastScanRef.current.time < DEBOUNCE_MS) {
            return;
        }

        lastScanRef.current = { payload: decodedText, time: now };
        submitScan(decodedText);
    }

    useEffect(() => {
        if (authState !== 'ready') return;

        const scanner = new Html5Qrcode(READER_ID);
        scannerRef.current = scanner;

        scanner
            .start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 250, height: 250 } }, onDecode, () => {})
            .catch(() => setCameraError(true));

        return () => {
            scanner
                .stop()
                .then(() => scanner.clear())
                .catch(() => {});
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [authState]);

    const headTags = (
        <Head title="Staff Scanner">
            <link rel="manifest" href="/manifest.webmanifest" />
            <meta name="theme-color" content="#c1272d" />
            <meta name="apple-mobile-web-app-capable" content="yes" />
            <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
            <meta name="apple-mobile-web-app-title" content="Staff Scanner" />
            <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />
        </Head>
    );

    if (authState === 'unauthenticated') {
        return (
            <>
                {headTags}
                <div className="flex min-h-screen items-center justify-center bg-brand-bg px-5 text-center">
                    <div>
                        <StoreIcon className="mx-auto h-10 w-10 text-brand-muted" />
                        <h1 className="mt-3 font-heading text-lg font-bold text-brand-text">This device is not set up</h1>
                        <p className="mt-2 text-sm text-brand-muted">
                            Ask the shop owner to add this device from the dashboard and scan the QR code it shows.
                        </p>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            {headTags}
            <ResultBanner result={result} />

            <div className="flex min-h-screen flex-col bg-black">
                <div className="flex items-center justify-between bg-brand-card px-5 py-3">
                    <div>
                        <p className="text-sm font-semibold text-brand-text">{shopName ?? 'Loading…'}</p>
                        <p className="text-xs text-brand-muted">{deviceName}</p>
                    </div>
                    {stampsToday !== null && (
                        <div className="text-right">
                            <p className="text-lg font-bold text-brand-text">{stampsToday}</p>
                            <p className="text-xs text-brand-muted">stamps today</p>
                        </div>
                    )}
                </div>

                <div className="relative flex flex-1 items-center justify-center">
                    {cameraError ? (
                        <div className="px-6 text-center text-white">
                            <CameraIcon className="mx-auto h-10 w-10 text-white/70" />
                            <p className="mt-3 font-semibold">Camera access denied</p>
                            <p className="mt-1 text-sm text-white/70">
                                Allow camera access for this site in your browser settings, then reload the page.
                            </p>
                        </div>
                    ) : (
                        <div id={READER_ID} className="w-full max-w-md" />
                    )}
                </div>
            </div>
        </>
    );
}
