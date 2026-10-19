import { useEffect } from "react";
import { createPortal } from "react-dom";
import { LuX } from "react-icons/lu";

/**
 * A YouTube video in a pop-up, playing straight away (privacy-enhanced
 * youtube-nocookie player, loaded only when opened). Esc or tapping outside closes it.
 */
export default function VideoDialog({ id, title = "Video", onClose }) {
    useEffect(() => {
        const onKey = (e) => e.key === "Escape" && onClose();
        window.addEventListener("keydown", onKey);
        return () => window.removeEventListener("keydown", onKey);
    }, [onClose]);

    return createPortal(
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-neutral-950/80 p-4 backdrop-blur-sm" onClick={onClose}>
            <div role="dialog" aria-modal="true" aria-label={title} onClick={(e) => e.stopPropagation()} className="relative w-full max-w-3xl">
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Close video"
                    className="absolute -top-11 right-0 rounded-full bg-white/10 p-2 text-white transition hover:bg-white/20"
                >
                    <LuX className="h-5 w-5" />
                </button>
                <div className="aspect-video overflow-hidden rounded-2xl bg-black shadow-2xl">
                    <iframe
                        src={`https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`}
                        title={title}
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowFullScreen
                        className="h-full w-full"
                    />
                </div>
            </div>
        </div>,
        document.body,
    );
}
