import { router } from '@inertiajs/react';
import { Html5Qrcode } from 'html5-qrcode';
import { stopScanner } from '@/lib/scanner';
import { useEffect, useRef, useState } from 'react';
import { LuCameraOff, LuScanLine, LuX } from 'react-icons/lu';

// In-app camera scanner for the admin: scan a printed sticker and go straight
// to its "Map this sticker" screen (GET /qr/{code}, which shows the mapping
// form to a logged-in admin). Uses html5-qrcode, like the staff scanner.

const READER_ID = 'qr-sticker-reader';
const CODE = /^[A-Za-z0-9]{4,16}$/;

/** The sticker code from a scanned link - only our own /qr/{code} links count. */
function stickerCode(text) {
    try {
        const url = new URL(text.trim());
        const match = url.pathname.match(/^\/qr\/([A-Za-z0-9]{4,16})\/?$/);
        return url.host === window.location.host && match ? match[1].toUpperCase() : null;
    } catch {
        return null;
    }
}

export default function QrStickerScanner({ onClose }) {
    const [cameraError, setCameraError] = useState(false);
    const [notOurs, setNotOurs] = useState(false);
    const [typed, setTyped] = useState('');
    const [opening, setOpening] = useState(null);
    const handled = useRef(false);
    const notOursTimer = useRef(null);

    function open(code) {
        if (handled.current) return;
        handled.current = true;
        setOpening(code);
        router.visit(`/qr/${code}`, { onFinish: onClose });
    }

    // Camera runs only while the dialog is open.
    useEffect(() => {
        const scanner = new Html5Qrcode(READER_ID);

        const started = scanner
            .start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                (text) => {
                    const code = stickerCode(text);
                    if (code) {
                        open(code);
                        return;
                    }
                    // Some other QR (a menu, a website…): say so and keep scanning.
                    setNotOurs(true);
                    clearTimeout(notOursTimer.current);
                    notOursTimer.current = setTimeout(() => setNotOurs(false), 2500);
                },
                () => {},
            )
            .catch(() => setCameraError(true));

        return () => {
            clearTimeout(notOursTimer.current);
            stopScanner(scanner, started);
        };
    }, []);

    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    function submitTyped(e) {
        e.preventDefault();
        const code = typed.trim().toUpperCase();
        if (CODE.test(code)) open(code);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/60 backdrop-blur-sm sm:items-center sm:p-4" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Scan a sticker"
                onClick={(e) => e.stopPropagation()}
                className="w-full overflow-hidden rounded-t-2xl border border-brand-border bg-brand-card sm:max-w-md sm:rounded-2xl"
            >
                <div className="flex items-center justify-between border-b border-brand-border px-5 py-4">
                    <div>
                        <h2 className="font-heading text-lg font-semibold text-brand-text">Scan a sticker</h2>
                        <p className="text-xs text-brand-muted">Point the camera at a printed QR to map it.</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Close" className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg hover:text-brand-text">
                        <LuX className="h-4 w-4" />
                    </button>
                </div>

                <div className="relative bg-neutral-950">
                    <div id={READER_ID} className="aspect-square w-full [&_video]:h-full [&_video]:w-full [&_video]:object-cover" />

                    {cameraError && (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-white">
                            <LuCameraOff className="h-8 w-8 text-white/70" />
                            <p className="text-sm font-semibold">Can’t open the camera</p>
                            <p className="text-xs text-white/70">
                                Allow camera access for this site in your browser. The camera only works on a secure (https) address or on localhost.
                            </p>
                        </div>
                    )}

                    {(notOurs || opening) && (
                        <p
                            role="status"
                            className={`absolute inset-x-4 bottom-4 rounded-xl px-3 py-2 text-center text-sm font-semibold ${
                                opening ? 'bg-brand-accent text-brand-accent-text' : 'bg-white text-neutral-900'
                            }`}
                        >
                            {opening ? `Found ${opening} - opening…` : 'That QR isn’t one of your stickers.'}
                        </p>
                    )}
                </div>

                <form onSubmit={submitTyped} className="flex items-center gap-2 border-t border-brand-border px-5 py-4">
                    <LuScanLine className="h-4 w-4 shrink-0 text-brand-muted" />
                    <input
                        value={typed}
                        onChange={(e) => setTyped(e.target.value.toUpperCase())}
                        placeholder="Or type the code, e.g. K7F2QX"
                        aria-label="Sticker code"
                        maxLength={16}
                        autoComplete="off"
                        className="min-w-0 flex-1 bg-transparent font-mono text-sm uppercase text-brand-text outline-none placeholder:font-sans placeholder:normal-case placeholder:text-brand-muted"
                    />
                    <button
                        type="submit"
                        disabled={!CODE.test(typed.trim())}
                        className="rounded-lg bg-brand-accent px-3 py-1.5 text-xs font-semibold text-brand-accent-text disabled:opacity-40"
                    >
                        Open
                    </button>
                </form>
            </div>
        </div>
    );
}
