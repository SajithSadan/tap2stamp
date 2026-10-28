import { Html5Qrcode } from 'html5-qrcode';
import { useEffect, useRef, useState } from 'react';
import { LuCameraOff, LuX } from 'react-icons/lu';
import { stopScanner } from '@/lib/scanner';

// The customer's Scan button (BottomNav, shops with Quick scan): scan the shop's
// QR at the counter from inside the app. It only opens this site's shop QRs - a
// sticker / counter display (/qr/{code}) or a card link (/s/{slug}, sent as
// ?scan=1) - as a full page load, so the server's Quick scan link does the
// stamping (App\Support\QuickScan). The camera runs only while this is open.

const READER_ID = 'shop-qr-reader';

/** Where a scanned QR should go, or null when it isn't one of our shop QRs. */
function shopLink(text) {
    try {
        const url = new URL(text.trim());
        if (url.host !== window.location.host) return null;

        const sticker = url.pathname.match(/^\/qr\/([A-Za-z0-9]{4,16})\/?$/);
        if (sticker) return `/qr/${sticker[1].toUpperCase()}`;

        const card = url.pathname.match(/^\/s\/([^/]+)\/?$/);
        return card ? `/s/${card[1]}?scan=1` : null;
    } catch {
        return null;
    }
}

export default function ShopQrScanner({ onClose }) {
    const [cameraError, setCameraError] = useState(false);
    const [notOurs, setNotOurs] = useState(false);
    const [opening, setOpening] = useState(false);
    const handled = useRef(false);
    const notOursTimer = useRef(null);

    useEffect(() => {
        const scanner = new Html5Qrcode(READER_ID);

        const started = scanner
            .start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 240, height: 240 } },
                (text) => {
                    const link = shopLink(text);
                    if (link) {
                        if (handled.current) return;
                        handled.current = true;
                        setOpening(true);
                        window.location.assign(link);
                        return;
                    }
                    // Their own card QR, a menu, a website…: say so and keep scanning.
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

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/60 sm:items-center sm:p-4" onClick={onClose}>
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Scan the shop's QR code"
                onClick={(e) => e.stopPropagation()}
                className="w-full overflow-hidden rounded-t-2xl bg-brand-card sm:max-w-md sm:rounded-2xl"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                <div className="flex items-center justify-between px-5 py-4">
                    <div>
                        <h2 className="font-heading text-lg font-semibold text-brand-text">Scan to collect a stamp</h2>
                        <p className="text-xs text-brand-muted">Point your camera at the QR code at the counter.</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Close" className="rounded-lg p-2 text-brand-muted hover:text-brand-text">
                        <LuX className="h-5 w-5" />
                    </button>
                </div>

                <div className="relative bg-neutral-950">
                    <div id={READER_ID} className="aspect-square w-full [&_video]:h-full [&_video]:w-full [&_video]:object-cover" />

                    {cameraError && (
                        <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 p-6 text-center text-white">
                            <LuCameraOff className="h-8 w-8 text-white/70" />
                            <p className="text-sm font-semibold">Can’t open the camera</p>
                            <p className="text-xs text-white/70">Allow camera access for this site, or scan the QR with your phone’s camera app.</p>
                        </div>
                    )}

                    {(notOurs || opening) && (
                        <p
                            role="status"
                            className={`absolute inset-x-4 bottom-4 rounded-xl px-3 py-2 text-center text-sm font-semibold ${
                                opening ? 'bg-brand-accent text-brand-accent-text' : 'bg-white text-neutral-900'
                            }`}
                        >
                            {opening ? 'Found it - adding your stamp…' : 'That’s not a shop’s QR code. Scan the one at the counter.'}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
