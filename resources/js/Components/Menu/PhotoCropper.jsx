import { useEffect, useRef, useState } from "react";
import { LuLoaderCircle, LuMinus, LuPlus, LuX } from "react-icons/lu";
import { primaryButton, secondaryButton } from "@/Components/Dashboard/Ui";

/** The square photo the menu gets: big enough for any layout, small enough to load fast. */
const OUTPUT = 800;
const MAX_ZOOM = 4;

/**
 * The square crop as a compressed blob: WebP, or JPEG where the browser
 * can't make WebP (older Safari quietly returns PNG). Drawing it again also
 * drops the photo's metadata (EXIF, incl. GPS location).
 */
async function cropToBlob(img, crop) {
    const size = Math.round(Math.min(OUTPUT, crop.size));
    const canvas = document.createElement("canvas");
    canvas.width = size;
    canvas.height = size;
    const ctx = canvas.getContext("2d");
    ctx.imageSmoothingQuality = "high";
    ctx.drawImage(img, crop.x, crop.y, crop.size, crop.size, 0, 0, size, size);

    const make = (type, quality) => new Promise((resolve) => canvas.toBlob(resolve, type, quality));
    const webp = await make("image/webp", 0.8);

    return webp && webp.type === "image/webp" ? webp : make("image/jpeg", 0.82);
}

/**
 * Crop a phone photo to a square before it's uploaded: drag to move, pinch /
 * wheel / slider to zoom. `onDone(blob)` gets the compressed square (a few
 * hundred KB at most, from photos of many MB) and may return a promise -
 * the dialog shows it's busy until it settles.
 */
export default function PhotoCropper({ file, itemName, onCancel, onDone }) {
    const viewport = useRef(null);
    const imgRef = useRef(null);
    const pointers = useRef(new Map());
    const pinch = useRef(null);
    const [src, setSrc] = useState(null);
    const [natural, setNatural] = useState(null); // { w, h }
    const [box, setBox] = useState(0); // the square viewport's size in CSS px
    const [view, setView] = useState({ zoom: 1, x: 0, y: 0 });
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        const url = URL.createObjectURL(file);
        setSrc(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);

    useEffect(() => {
        const measure = () => setBox(viewport.current?.clientWidth ?? 0);
        measure();
        window.addEventListener("resize", measure);
        return () => window.removeEventListener("resize", measure);
    }, []);

    // Scale that makes the photo just cover the square, at zoom 1.
    const base = natural && box ? box / Math.min(natural.w, natural.h) : 1;
    const scale = base * view.zoom;

    /** Keep the photo covering the whole square. */
    const clamp = (x, y, s) => ({
        x: Math.min(0, Math.max(box - natural.w * s, x)),
        y: Math.min(0, Math.max(box - natural.h * s, y)),
    });

    // Centre the photo once its size and the square's are known.
    useEffect(() => {
        if (!natural || !box) return;
        const s = box / Math.min(natural.w, natural.h);
        setView({ zoom: 1, x: (box - natural.w * s) / 2, y: (box - natural.h * s) / 2 });
    }, [natural, box]);

    /** Zoom around a point of the square (its centre by default). */
    function zoomTo(next, cx = box / 2, cy = box / 2) {
        if (!natural) return;
        setView((v) => {
            const zoom = Math.min(MAX_ZOOM, Math.max(1, next));
            const before = base * v.zoom;
            const after = base * zoom;
            const px = (cx - v.x) / before;
            const py = (cy - v.y) / before;

            return { zoom, ...clamp(cx - px * after, cy - py * after, after) };
        });
    }

    function onPointerDown(e) {
        e.currentTarget.setPointerCapture(e.pointerId);
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
        if (pointers.current.size === 2) {
            const [a, b] = [...pointers.current.values()];
            pinch.current = { distance: Math.hypot(a.x - b.x, a.y - b.y), zoom: view.zoom };
        }
    }

    function onPointerMove(e) {
        const last = pointers.current.get(e.pointerId);
        if (!last || !natural) return;
        pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });

        if (pointers.current.size === 2 && pinch.current) {
            const [a, b] = [...pointers.current.values()];
            const rect = viewport.current.getBoundingClientRect();
            zoomTo(pinch.current.zoom * (Math.hypot(a.x - b.x, a.y - b.y) / pinch.current.distance), (a.x + b.x) / 2 - rect.left, (a.y + b.y) / 2 - rect.top);
        } else if (pointers.current.size === 1) {
            setView((v) => ({ ...v, ...clamp(v.x + e.clientX - last.x, v.y + e.clientY - last.y, base * v.zoom) }));
        }
    }

    function onPointerUp(e) {
        pointers.current.delete(e.pointerId);
        if (pointers.current.size < 2) pinch.current = null;
    }

    function onWheel(e) {
        const rect = viewport.current.getBoundingClientRect();
        zoomTo(view.zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1), e.clientX - rect.left, e.clientY - rect.top);
    }

    async function done() {
        if (!natural || busy) return;
        setBusy(true);
        setError(null);
        try {
            // The visible square, in the photo's own pixels.
            const blob = await cropToBlob(imgRef.current, { x: -view.x / scale, y: -view.y / scale, size: box / scale });
            if (!blob) throw new Error("crop");
            await onDone(blob);
        } catch (e) {
            setError(e?.message && e.message !== "crop" ? e.message : "Couldn't prepare that photo - try another one.");
            setBusy(false);
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-950/60 backdrop-blur-sm sm:items-center sm:p-4">
            <div role="dialog" aria-modal="true" aria-label="Crop photo" className="w-full overflow-hidden rounded-t-2xl bg-brand-card shadow-xl sm:max-w-md sm:rounded-2xl">
                <div className="flex items-center justify-between px-5 pt-4">
                    <div className="min-w-0">
                        <h2 className="truncate font-heading text-lg font-semibold text-brand-text">Photo for {itemName}</h2>
                        <p className="text-xs text-brand-muted">Drag to position · pinch or use the slider to zoom</p>
                    </div>
                    <button type="button" onClick={onCancel} disabled={busy} aria-label="Cancel" className="rounded-lg p-2 text-brand-muted hover:bg-brand-bg">
                        <LuX className="h-4 w-4" />
                    </button>
                </div>

                <div className="px-5 pt-3">
                    <div
                        ref={viewport}
                        onPointerDown={onPointerDown}
                        onPointerMove={onPointerMove}
                        onPointerUp={onPointerUp}
                        onPointerCancel={onPointerUp}
                        onWheel={onWheel}
                        className="relative aspect-square w-full cursor-grab touch-none select-none overflow-hidden rounded-xl bg-neutral-900 active:cursor-grabbing"
                    >
                        {src && (
                            <img
                                ref={imgRef}
                                src={src}
                                alt=""
                                draggable={false}
                                onLoad={(e) => setNatural({ w: e.currentTarget.naturalWidth, h: e.currentTarget.naturalHeight })}
                                onError={() => setError("This photo format can't be opened here - choose a JPG or PNG.")}
                                className="pointer-events-none absolute left-0 top-0 max-w-none origin-top-left"
                                style={natural ? { width: natural.w, height: natural.h, transform: `translate(${view.x}px, ${view.y}px) scale(${scale})` } : { opacity: 0 }}
                            />
                        )}
                        {!natural && !error && (
                            <span className="absolute inset-0 flex items-center justify-center">
                                <LuLoaderCircle className="h-6 w-6 animate-spin text-white/70" />
                            </span>
                        )}
                    </div>

                    <div className="mt-3 flex items-center gap-3">
                        <button type="button" onClick={() => zoomTo(view.zoom / 1.25)} aria-label="Zoom out" className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg">
                            <LuMinus className="h-4 w-4" />
                        </button>
                        <input
                            type="range"
                            min={1}
                            max={MAX_ZOOM}
                            step={0.01}
                            value={view.zoom}
                            onChange={(e) => zoomTo(Number(e.target.value))}
                            aria-label="Zoom"
                            className="flex-1 accent-[var(--color-brand-accent)]"
                        />
                        <button type="button" onClick={() => zoomTo(view.zoom * 1.25)} aria-label="Zoom in" className="rounded-lg p-1.5 text-brand-muted hover:bg-brand-bg">
                            <LuPlus className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {error && (
                    <p role="alert" className="mx-5 mt-2 rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-700">
                        {error}
                    </p>
                )}

                <div className="grid grid-cols-2 gap-2 px-5 pb-5 pt-4">
                    <button type="button" onClick={onCancel} disabled={busy} className={`${secondaryButton} py-3`}>
                        Cancel
                    </button>
                    <button type="button" onClick={done} disabled={!natural || busy} className={`${primaryButton} py-3`}>
                        {busy ? (
                            <>
                                <LuLoaderCircle className="h-4 w-4 animate-spin" /> Uploading…
                            </>
                        ) : (
                            "Use photo"
                        )}
                    </button>
                </div>
            </div>
        </div>
    );
}
