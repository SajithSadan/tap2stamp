import { useEffect, useRef, useState } from "react";

/** Next slide every this many ms. */
const INTERVAL = 5000;

/** After a swipe or tap, wait this long before sliding on by itself. */
const IDLE_AFTER_TOUCH = 8000;

/**
 * The shop's promotion banners (App\Services\MenuBanners) above the menu:
 * swipeable slides (CSS scroll snap), dots underneath, sliding on by itself
 * unless the reader is touching it or prefers reduced motion.
 */
export default function PromoCarousel({ banners }) {
    const trackRef = useRef(null);
    const touchedAt = useRef(0);
    const [index, setIndex] = useState(0);
    const count = banners.length;

    function goTo(i) {
        const track = trackRef.current;
        if (track) track.scrollTo({ left: i * track.clientWidth, behavior: "smooth" });
    }

    useEffect(() => {
        if (count < 2 || window.matchMedia?.("(prefers-reduced-motion: reduce)").matches) return;

        const timer = setInterval(() => {
            const track = trackRef.current;
            if (!track || Date.now() - touchedAt.current < IDLE_AFTER_TOUCH) return;
            goTo((Math.round(track.scrollLeft / track.clientWidth) + 1) % count);
        }, INTERVAL);

        return () => clearInterval(timer);
    }, [count]);

    // Banners added / removed in the editor's preview: stay on a slide that exists.
    useEffect(() => {
        if (index >= count && count > 0) goTo(count - 1);
    }, [count, index]);

    if (count === 0) return null;

    const touched = () => (touchedAt.current = Date.now());

    return (
        <div className="mx-auto max-w-xl px-4 pt-4" aria-roledescription="carousel" aria-label="Offers">
            <div
                ref={trackRef}
                onScroll={(e) => setIndex(Math.round(e.currentTarget.scrollLeft / e.currentTarget.clientWidth))}
                onPointerDown={touched}
                onWheel={touched}
                className="flex snap-x snap-mandatory overflow-x-auto rounded-brand border border-brand-border bg-brand-card [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                {banners.map((banner, i) => (
                    <img
                        key={banner.id}
                        src={banner.url}
                        alt={`Offer ${i + 1} of ${count}`}
                        loading={i === 0 ? "eager" : "lazy"}
                        draggable={false}
                        className="aspect-video w-full shrink-0 snap-center object-cover"
                    />
                ))}
            </div>
            {count > 1 && (
                <div className="mt-2.5 flex justify-center gap-1.5">
                    {banners.map((banner, i) => (
                        <button
                            key={banner.id}
                            type="button"
                            onClick={() => {
                                touched();
                                goTo(i);
                            }}
                            aria-label={`Show offer ${i + 1}`}
                            aria-current={i === index}
                            className={`h-1.5 rounded-full transition-all ${i === index ? "w-6 bg-brand-accent" : "w-1.5 bg-brand-muted/40"}`}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
