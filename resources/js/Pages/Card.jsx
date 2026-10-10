import { Head } from "@inertiajs/react";
import axios from "axios";
import { AnimatePresence, motion } from "framer-motion";
import Pusher from "pusher-js";
import QRCode from "qrcode";
import { createPortal } from "react-dom";
import { useEffect, useMemo, useRef, useState } from "react";
import BottomNav from "@/Components/BottomNav";
import OfflineBanner from "@/Components/OfflineBanner";
import RegistrationModal from "@/Components/RegistrationModal";
import RatingTile from "@/Components/RatingTile";
import { HiSparkles } from "react-icons/hi2";
import { FaInstagram } from "react-icons/fa6";
import { LuChevronRight, LuScan, LuWifi } from "react-icons/lu";
import { PiStorefrontFill } from "react-icons/pi";
import { StampIcon } from "@/lib/stampIcons";
import { headerTextStyle, headerTintStyle } from "@/lib/headerStyle";
import { CUSTOMER_UUID_KEY, LAST_SHOP_SLUG_KEY } from "@/lib/storage";
import StampGrid from "@/Components/StampGrid";
import { useDocumentTheme } from "@/lib/theme";

function copyToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text);
    }

    // Fallback for browsers/contexts without the async Clipboard API.
    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
        document.execCommand("copy");
    } finally {
        document.body.removeChild(textarea);
    }

    return Promise.resolve();
}

/** Section panels: solid, with a hairline border and no shadow. */
const SURFACE = "border border-brand-border bg-brand-card";

const GENERIC_STAMP_ICONS = ["check", "star", "heart", "sparkles", "gift"];

function HeaderIcon({ icon }) {
    return GENERIC_STAMP_ICONS.includes(icon) ? (
        <PiStorefrontFill
            className="h-8 w-8 text-brand-accent"
            aria-hidden="true"
        />
    ) : (
        <StampIcon
            icon={icon}
            className="h-8 w-8 text-brand-accent"
            aria-hidden="true"
        />
    );
}

function SkeletonCard({ surface }) {
    return (
        <div className={`animate-pulse rounded-2xl p-5 ${surface}`}>
            <div className="h-3 w-28 rounded bg-brand-border" />
            <div className="mt-3 h-2 w-full rounded-full bg-brand-border" />
            <div className="mt-4 grid grid-cols-6 gap-2">
                {Array.from({ length: 6 }, (_, i) => (
                    <div
                        key={i}
                        className="aspect-square rounded-full bg-brand-border"
                    />
                ))}
            </div>
        </div>
    );
}

function Celebration() {
    const particles = useMemo(
        () =>
            Array.from({ length: 12 }, (_, i) => {
                const angle = (i / 12) * Math.PI * 2;
                return {
                    id: i,
                    x: Math.cos(angle) * (50 + Math.random() * 40),
                    y: Math.sin(angle) * (50 + Math.random() * 40),
                    rotate: Math.random() * 360,
                };
            }),
        [],
    );

    return (
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
            {particles.map((p) => (
                <motion.span
                    key={p.id}
                    initial={{ opacity: 1, x: 0, y: 0, scale: 0.3, rotate: 0 }}
                    animate={{
                        opacity: 0,
                        x: p.x,
                        y: p.y,
                        scale: 1,
                        rotate: p.rotate,
                    }}
                    transition={{ duration: 0.9, ease: "easeOut" }}
                    className="absolute text-brand-accent-text"
                >
                    <HiSparkles className="h-3.5 w-3.5" />
                </motion.span>
            ))}
        </div>
    );
}

const SHOWER_COLORS = [
    "var(--color-brand-accent)",
    "#F5B400",
    "#F4775B",
    "#5B8DEF",
    "#FFFFFF",
];

function StampShower() {
    const pieces = useMemo(
        () =>
            Array.from({ length: 72 }, (_, id) => ({
                id,
                left: `${2 + Math.random() * 96}%`,
                delay: Math.random() * 1.05,
                duration: 2.35 + Math.random() * 1.15,
                drift: (Math.random() - 0.5) * 220,
                spin: (Math.random() - 0.5) * 1260,
                color: SHOWER_COLORS[id % SHOWER_COLORS.length],
                ribbon: id % 3 === 0,
                round: id % 7 === 0,
            })),
        [],
    );

    return (
        <div
            className="pointer-events-none fixed inset-0 z-30 overflow-hidden"
            aria-hidden="true"
        >
            {pieces.map((piece) => (
                <motion.span
                    key={piece.id}
                    initial={{
                        opacity: 0,
                        y: -40,
                        x: 0,
                        rotate: 0,
                        scale: 0.5,
                    }}
                    animate={{
                        opacity: [0, 1, 1, 1, 0],
                        y: "110vh",
                        x: piece.drift,
                        rotate: piece.spin,
                        scale: [0.5, 1.2, 0.9],
                    }}
                    transition={{
                        duration: piece.duration,
                        delay: piece.delay,
                        ease: "easeIn",
                    }}
                    className={`absolute top-0 ${piece.ribbon ? "h-10 w-2.5" : piece.round ? "h-3 w-3" : "h-4 w-2.5"} ${piece.round ? "rounded-full" : "rounded-sm"}`}
                    style={{ left: piece.left, backgroundColor: piece.color }}
                />
            ))}
            <motion.div
                initial={{ opacity: 0, scale: 0.25, y: 18, x: 0 }}
                animate={{
                    opacity: [0, 1, 1, 0],
                    scale: [0.25, 1.18, 1, 1],
                    y: [18, 0, 0, -8],
                    x: [0, -5, 5, -3, 3, 0],
                }}
                transition={{
                    duration: 1.25,
                    times: [0, 0.2, 0.72, 1],
                    ease: "easeOut",
                }}
                className="absolute left-1/2 top-[42%] flex -translate-x-1/2 items-center gap-2 rounded-full bg-brand-accent px-5 py-3 text-base font-bold text-brand-accent-text shadow-xl ring-4 ring-white/80"
            >
                <HiSparkles className="h-5 w-5" /> Stamp added!
            </motion.div>
        </div>
    );
}

/**
 * `preview`: a sample card from the server (admin / owner "open customer page").
 * In preview the page never reads or writes the saved customer, never asks to
 * sign up or join, and never posts anything.
 */
export default function Card({ shop, theme, preview = null, phoneCountries = [] }) {
    // The owner's chosen look (Dashboard → Theme), applied to this page only.
    useDocumentTheme(theme);
    const surface = SURFACE;

    const [card, setCard] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [pendingJoinUuid, setPendingJoinUuid] = useState(null);
    const [joiningShop, setJoiningShop] = useState(false);
    const [joinError, setJoinError] = useState(null);
    const [showReviewPrompt, setShowReviewPrompt] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [qrSrc, setQrSrc] = useState(null);
    // Which quick-action panel is open under the action row: 'rate', 'wifi' or none.
    const [panel, setPanel] = useState(null);
    const togglePanel = (name) =>
        setPanel((open) => (open === name ? null : name));
    const [copied, setCopied] = useState(false);
    const [celebrate, setCelebrate] = useState(false);
    const [stampShower, setStampShower] = useState(false);
    const [redeemedToast, setRedeemedToast] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const [qrOpen, setQrOpen] = useState(false);

    const prevStampsRef = useRef(0);
    const wasReadyRef = useRef(false);
    const audioCtxRef = useRef(null);
    const celebrateTimeoutRef = useRef(null);
    const stampShowerTimeoutRef = useRef(null);
    const reviewPromptTimeoutRef = useRef(null);
    const reviewedRef = useRef(false);

    useEffect(() => {
        const unlockAudio = () => {
            if (!audioCtxRef.current) {
                try {
                    const Ctx =
                        window.AudioContext || window.webkitAudioContext;
                    if (Ctx) audioCtxRef.current = new Ctx();
                } catch {
                    // Web Audio unsupported
                }
            } else if (audioCtxRef.current.state === "suspended") {
                audioCtxRef.current.resume();
            }
        };

        window.addEventListener("click", unlockAudio, { once: true });
        window.addEventListener("touchstart", unlockAudio, { once: true });
        return () => {
            window.removeEventListener("click", unlockAudio);
            window.removeEventListener("touchstart", unlockAudio);
        };
    }, []);

    useEffect(() => {
        if (!preview) window.localStorage.setItem(LAST_SHOP_SLUG_KEY, shop.slug);
    }, [shop.slug]);

    function loadCard() {
        if (preview) {
            setCard(preview);
            setLoading(false);
            return;
        }

        const uuid = window.localStorage.getItem(CUSTOMER_UUID_KEY);

        if (!uuid) {
            setShowModal(true);
            setLoading(false);
            return;
        }

        setLoading(true);
        setLoadError(false);

        axios
            .get(`/s/${shop.slug}/card/${uuid}`)
            .then(({ data }) => setCard(data))
            .catch((error) => {
                if (error.response?.status !== 404) {
                    // Network/server failures must not discard a valid saved identity.
                    setLoadError(true);
                    return;
                }

                // Ask for this shop's marketing preference before creating its card.
                setJoinError(null);
                setPendingJoinUuid(uuid);
            })
            .finally(() => setLoading(false));
    }

    function joinShop(marketingConsent) {
        if (!pendingJoinUuid || joiningShop) return;

        setJoiningShop(true);
        setJoinError(null);

        axios
            .post(`/s/${shop.slug}/card/${pendingJoinUuid}/join`, {
                marketing_consent: marketingConsent,
            })
            .then(({ data }) => {
                setCard(data);
                setPendingJoinUuid(null);
            })
            .catch((error) => {
                if (error.response?.status === 404) {
                    window.localStorage.removeItem(CUSTOMER_UUID_KEY);
                    setPendingJoinUuid(null);
                    setShowModal(true);
                } else {
                    setJoinError(
                        "Could not create your card right now. Please try again.",
                    );
                }
            })
            .finally(() => setJoiningShop(false));
    }

    useEffect(() => {
        loadCard();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [shop.slug]);

    useEffect(() => {
        if (!card) {
            setQrSrc(null);
            return;
        }

        QRCode.toDataURL(`TOKEN:${card.uuid}|SHOP:${card.shop_id}`, {
            margin: 1,
            width: 480,
        })
            .then(setQrSrc)
            .catch(() => setQrSrc(null));
    }, [card]);

    function triggerCelebration() {
        setCelebrate(true);
        if (celebrateTimeoutRef.current)
            clearTimeout(celebrateTimeoutRef.current);
        celebrateTimeoutRef.current = setTimeout(
            () => setCelebrate(false),
            1000,
        );
    }

    function celebrateStamp() {
        setStampShower(true);
        if (stampShowerTimeoutRef.current)
            clearTimeout(stampShowerTimeoutRef.current);
        stampShowerTimeoutRef.current = setTimeout(
            () => setStampShower(false),
            4550,
        );

        if (!shop.google_review_direct && !reviewedRef.current) {
            if (reviewPromptTimeoutRef.current)
                clearTimeout(reviewPromptTimeoutRef.current);
            reviewPromptTimeoutRef.current = setTimeout(() => {
                setShowReviewPrompt(true);
            }, 4650);
        }
    }

    useEffect(
        () => () => {
            if (stampShowerTimeoutRef.current)
                clearTimeout(stampShowerTimeoutRef.current);
            if (reviewPromptTimeoutRef.current)
                clearTimeout(reviewPromptTimeoutRef.current);
        },
        [],
    );

    // Detect the moment the card crosses over into "reward ready" to fire a
    // one-off celebration burst, without re-triggering on every re-render.
    useEffect(() => {
        if (!card) return;

        const ready = card.stamps >= card.max_stamps;

        if (ready && !wasReadyRef.current) {
            triggerCelebration();
            wasReadyRef.current = true;
        } else {
            wasReadyRef.current = ready;
        }
    }, [card?.stamps, card?.max_stamps]);

    useEffect(() => {
        if (card) {
            prevStampsRef.current = card.stamps;
            reviewedRef.current = Boolean(card.review);
        }
    }, [card?.stamps, card?.review]);

    function handleReviewSaved(review) {
        reviewedRef.current = true;
        if (reviewPromptTimeoutRef.current)
            clearTimeout(reviewPromptTimeoutRef.current);
        setCard((current) => (current ? { ...current, review } : current));
        setShowReviewPrompt(false);
    }

    // Two-tone chime via Web Audio.
    function playChime() {
        if (!audioCtxRef.current) {
            try {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (Ctx) audioCtxRef.current = new Ctx();
            } catch {
                return;
            }
        }

        const ctx = audioCtxRef.current;
        if (!ctx) return;
        if (ctx.state === "suspended") {
            ctx.resume();
        }

        const now = ctx.currentTime;
        [660, 880].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = freq;
            osc.connect(gain);
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(0.0001, now + i * 0.12);
            gain.gain.exponentialRampToValueAtTime(0.2, now + i * 0.12 + 0.02);
            gain.gain.exponentialRampToValueAtTime(
                0.0001,
                now + i * 0.12 + 0.35,
            );
            osc.start(now + i * 0.12);
            osc.stop(now + i * 0.12 + 0.35);
        });
    }

    // Real-time updates from staff scans (Stage 7). Resilience: if Pusher
    // isn't configured or the connection fails, the page still works via
    // the normal fetch-on-load path above - this is purely additive.
    useEffect(() => {
        if (!card?.uuid || preview) return;

        // Console diagnostics for live updates, all prefixed "[TaDa live]" (filter on it).
        // Only the start of the public key is shown, never anything secret.
        const log = (...args) => console.info("[TaDa live]", ...args);
        const key = import.meta.env.VITE_PUSHER_APP_KEY;
        const cluster = import.meta.env.VITE_PUSHER_APP_CLUSTER;
        log("config", { key: key ? `${key.slice(0, 6)}…` : "MISSING - built without VITE_PUSHER_APP_KEY", cluster: cluster || "MISSING" });
        if (!key) return;

        let pusher;
        let channel;
        const channelName = `card.${card.uuid}.${card.shop_id}`;

        try {
            pusher = new Pusher(key, { cluster });
            pusher.connection.bind("state_change", ({ previous, current }) => log(`connection: ${previous} → ${current}`));
            pusher.connection.bind("error", (error) => log("connection error", error));
            channel = pusher.subscribe(channelName);
            channel.bind("pusher:subscription_succeeded", () => log("subscribed to", channelName));
            channel.bind("pusher:subscription_error", (error) => log("subscription failed", channelName, error));

            channel.bind("card.updated", (data) => {
                log("card.updated received", data);
                setCard((prev) =>
                    prev
                        ? {
                              ...prev,
                              stamps: data.stamps,
                              max_stamps: data.max_stamps,
                          }
                        : prev,
                );
                triggerCelebration();
                playChime();
                if (data.action === "stamp_added") {
                    celebrateStamp();
                    if (navigator.vibrate)
                        navigator.vibrate([70, 45, 110, 35, 70]);
                } else if (navigator.vibrate) {
                    navigator.vibrate(60);
                }

                if (data.action === "reward_redeemed") {
                    setRedeemedToast(true);
                    setTimeout(() => setRedeemedToast(false), 3000);
                }
            });
        } catch (error) {
            // Connection failed - customer can still refresh to see updates.
            log("could not start live updates", error);
        }

        return () => {
            try {
                channel?.unbind_all();
                pusher?.unsubscribe(channelName);
                pusher?.disconnect();
            } catch {
                // Best-effort cleanup.
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [card?.uuid, card?.shop_id]);

    // Self-correct if a real-time event was missed while the tab/app was
    // backgrounded (Pusher connections can drop silently on mobile).
    useEffect(() => {
        function handleVisibility() {
            if (document.visibilityState !== "visible" || !card?.uuid || preview) return;

            axios
                .get(`/s/${shop.slug}/card/${card.uuid}`)
                .then(({ data }) => setCard(data))
                .catch(() => {});
        }

        document.addEventListener("visibilitychange", handleVisibility);
        return () =>
            document.removeEventListener("visibilitychange", handleVisibility);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [shop.slug, card?.uuid]);

    function handleRegister(name, phone, marketingConsent) {
        setSubmitting(true);
        setErrors({});

        axios
            .post(`/s/${shop.slug}/register`, {
                name,
                phone,
                marketing_consent: marketingConsent,
            })
            .then(({ data }) => {
                window.localStorage.setItem(CUSTOMER_UUID_KEY, data.uuid);
                setCard(data);
                setShowModal(false);
            })
            .catch((error) => {
                const status = error.response?.status;

                if (status === 422) {
                    setErrors(error.response.data.errors);
                } else if (status === 429) {
                    setErrors({
                        general:
                            "Too many tries. Please wait a minute and try again.",
                    });
                } else if (!error.response) {
                    setErrors({
                        general:
                            "Can't connect right now. Check your connection and try again.",
                    });
                } else {
                    setErrors({
                        general: "Something went wrong. Please try again.",
                    });
                }
            })
            .finally(() => setSubmitting(false));
    }

    function handleCopyPassword() {
        copyToClipboard(card.wifi_password).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    }

    const previousStamps = prevStampsRef.current;
    const rewardReady = card && card.stamps >= card.max_stamps;

    return (
        <>
            <Head title={shop.name} />
            <OfflineBanner />
            {preview && (
                <p className="fixed inset-x-0 top-3 z-20 mx-auto w-fit rounded-full bg-neutral-900 px-3 py-1 text-xs font-medium text-white">
                    Preview · sample card, nothing is saved
                </p>
            )}
            {stampShower && <StampShower />}

            <AnimatePresence>
                {pendingJoinUuid && (
                    <motion.div
                        className="fixed inset-0 z-40 flex items-center justify-center bg-black/60 px-5 py-8"
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                    >
                        <motion.div
                            role="alertdialog"
                            aria-modal="true"
                            aria-labelledby="shop-join-title"
                            aria-describedby="shop-join-description"
                            initial={{ opacity: 0, y: 12, scale: 0.97 }}
                            animate={{ opacity: 1, y: 0, scale: 1 }}
                            exit={{ opacity: 0, y: 8, scale: 0.98 }}
                            className="w-full max-w-sm rounded-2xl bg-brand-card p-6 text-center shadow-2xl"
                        >
                            <h2
                                id="shop-join-title"
                                className="font-heading text-xl font-semibold text-brand-text"
                            >
                                Your {shop.name} card is ready
                            </h2>
                            <p
                                id="shop-join-description"
                                className="mt-2 text-sm leading-relaxed text-brand-muted"
                            >
                                Would you like WhatsApp offers from{" "}
                                <span className="font-semibold text-brand-text">
                                    {shop.name}
                                </span>
                                ?
                            </p>
                            {joinError && (
                                <p
                                    role="alert"
                                    className="mt-3 text-sm text-red-600"
                                >
                                    {joinError}
                                </p>
                            )}
                            <div className="mt-6 grid gap-3">
                                <button
                                    type="button"
                                    disabled={joiningShop}
                                    onClick={() => joinShop(true)}
                                    className="flex min-h-12 w-full items-center justify-center rounded-full bg-brand-accent px-5 py-3 text-sm font-semibold text-white shadow-md transition hover:brightness-95 disabled:opacity-60"
                                >
                                    {joiningShop
                                        ? "Setting up your card…"
                                        : "Yes, send me offers"}
                                </button>
                                <button
                                    type="button"
                                    disabled={joiningShop}
                                    onClick={() => joinShop(false)}
                                    className="flex min-h-12 w-full items-center justify-center rounded-full border-2 border-brand-accent bg-white px-5 py-3 text-sm font-semibold text-brand-text transition hover:bg-brand-accent/5 disabled:opacity-60"
                                >
                                    No thanks, just create my card
                                </button>
                            </div>
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>

            <AnimatePresence>
                {redeemedToast && (
                    <motion.div
                        initial={{ opacity: 0, y: -20 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -20 }}
                        className="fixed inset-x-5 top-3 z-20 mx-auto max-w-sm rounded-brand bg-brand-accent px-4 py-3 text-center text-sm font-semibold text-brand-accent-text shadow-lg"
                    >
                        🎉 Reward redeemed — enjoy!
                    </motion.div>
                )}
            </AnimatePresence>

            <div className="min-h-screen bg-brand-bg pb-24">
                {/* Solid deep-brand band (or the banner photo under a solid tint) with the logo,
                    name and reward. The progress card overlaps its bottom edge. No motion. */}
                <header className="relative overflow-hidden bg-brand-deep">
                    {shop.banner_url && (
                        <>
                            <img
                                src={shop.banner_url}
                                alt=""
                                className="absolute inset-0 h-full w-full object-cover"
                            />
                            {/* Tint strength set by the owner (Theme → Banner & logo). */}
                            <div
                                className="absolute inset-0 bg-black"
                                style={headerTintStyle(shop.header_style)}
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/15 to-transparent" />
                        </>
                    )}

                    <div className="relative mx-auto flex max-w-sm items-center gap-3.5 px-4 pb-20 pt-24">
                        <span className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-white">
                            {shop.logo_url ? (
                                <img
                                    src={shop.logo_url}
                                    alt={`${shop.name} logo`}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                // No logo: the shop's stamp icon (a coffee cup, scissors...), or a
                                // storefront when the stamp icon is a generic one (tick, star...).
                                <HeaderIcon icon={shop.stamp_icon} />
                            )}
                        </span>
                        <div
                            className="min-w-0"
                            style={headerTextStyle(shop.header_style)}
                        >
                            <h1 className="line-clamp-2 font-heading text-2xl font-semibold leading-tight">
                                {shop.name}
                            </h1>
                            <p className="mt-0.5 truncate text-sm opacity-80">
                                {shop.reward_title}
                            </p>
                        </div>
                    </div>
                </header>

                <main className="relative -mt-12 space-y-3 px-4 [&>*]:mx-auto [&>*]:max-w-sm">
                    {loading && <SkeletonCard surface={surface} />}

                    {loadError && !loading && (
                        <div
                            className={`rounded-2xl p-5 text-center ${surface}`}
                        >
                            <p className="text-sm text-brand-muted">
                                We couldn't load your card just now.
                            </p>
                            <button
                                onClick={loadCard}
                                className="mt-3 rounded-brand bg-brand-accent px-4 py-2 text-sm font-semibold text-brand-accent-text"
                            >
                                Try again
                            </button>
                        </div>
                    )}

                    {card && (
                        <motion.section
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            className={`relative rounded-2xl p-5 ${surface}`}
                        >
                            {celebrate && <Celebration />}

                            <AnimatePresence>
                                {rewardReady && (
                                    <motion.p
                                        initial={{
                                            opacity: 0,
                                            height: 0,
                                            marginBottom: 0,
                                        }}
                                        animate={{
                                            opacity: 1,
                                            height: "auto",
                                            marginBottom: 12,
                                        }}
                                        exit={{
                                            opacity: 0,
                                            height: 0,
                                            marginBottom: 0,
                                        }}
                                        className="flex items-center gap-2 overflow-hidden rounded-brand bg-brand-accent px-3 py-2.5 text-xs font-semibold text-brand-accent-text"
                                    >
                                        <HiSparkles className="h-4 w-4 shrink-0" />
                                        Reward unlocked — show this screen to
                                        staff!
                                    </motion.p>
                                )}
                            </AnimatePresence>

                            <p className="text-sm text-brand-muted">
                                Your progress
                            </p>
                            <div className="mt-0.5 flex items-center justify-between gap-3">
                                <p className="tabular-nums text-brand-text">
                                    <span className="text-4xl font-bold tracking-tight">
                                        {card.stamps}
                                    </span>
                                    <span className="text-lg font-medium text-brand-muted">
                                        {" "}
                                        / {card.max_stamps}
                                    </span>
                                </p>
                                {!rewardReady && (
                                    <span className="rounded-full bg-brand-accent/10 px-3 py-1 text-[13px] font-medium text-brand-accent">
                                        {card.max_stamps - card.stamps} to go
                                    </span>
                                )}
                            </div>

                            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-brand-border">
                                <motion.div
                                    className="h-full rounded-full bg-brand-accent"
                                    initial={false}
                                    animate={{
                                        width: `${Math.min(100, (card.stamps / card.max_stamps) * 100)}%`,
                                    }}
                                    transition={{ duration: 0.5 }}
                                />
                            </div>

                            <StampGrid
                                className="mt-4"
                                total={card.max_stamps}
                                stamps={card.stamps}
                                previous={previousStamps}
                                icon={shop.stamp_icon}
                            />

                            {card.rewards_claimed > 0 && (
                                <p className="mt-3 text-center text-xs text-brand-muted">
                                    {card.rewards_claimed} rewards claimed
                                </p>
                            )}
                        </motion.section>
                    )}

                    {card && qrSrc && (
                        <section
                            className={`flex items-center gap-4 rounded-2xl p-5 ${surface}`}
                        >
                            <button
                                type="button"
                                onClick={() => setQrOpen(true)}
                                className="shrink-0"
                                aria-label="Enlarge QR code"
                            >
                                <img
                                    src={qrSrc}
                                    alt="Your loyalty card QR code"
                                    className="h-32 w-32"
                                />
                            </button>
                            <div className="min-w-0">
                                <p className="text-base font-semibold text-brand-text">
                                    Scan to collect
                                </p>
                                <p className="mt-1 text-sm leading-snug text-brand-muted">
                                    Show this code at the counter after each
                                    visit.
                                </p>
                                <button
                                    type="button"
                                    onClick={() => setQrOpen(true)}
                                    className="mt-2 flex items-center gap-1.5 text-sm font-medium text-brand-accent"
                                >
                                    <LuScan
                                        className="h-4 w-4"
                                        aria-hidden="true"
                                    />{" "}
                                    Tap to enlarge
                                </button>
                            </div>
                        </section>
                    )}

                    {/* Quick actions: only the ones the shop has set up. */}
                    {(shop.instagram_url || card) && (
                        <div className="space-y-2">
                            {card &&
                                (shop.google_review_direct &&
                                shop.google_review_url ? (
                                    <a
                                        href={shop.google_review_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className={`flex w-full items-center gap-3 rounded-2xl p-3.5 text-left transition active:scale-[0.99] ${surface}`}
                                    >
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-bg">
                                            <img
                                                src="/images/google-review-icon.png"
                                                alt=""
                                                className="h-7 w-7 object-contain"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-semibold text-brand-text">
                                                Review us on Google
                                            </span>
                                            <span className="mt-0.5 block text-sm text-brand-muted">
                                                Opens Google Reviews
                                            </span>
                                        </span>
                                        <LuChevronRight
                                            className="h-5 w-5 shrink-0 text-brand-muted"
                                            aria-hidden="true"
                                        />
                                    </a>
                                ) : (
                                    <button
                                        type="button"
                                        // Preview: shown for the look only - feedback needs a real card.
                                        onClick={() => !preview && setPanel((open) => open === "rate" ? null : "rate")}
                                        aria-disabled={preview ? true : undefined}
                                        aria-expanded={panel === "rate" || showReviewPrompt}
                                        className={`flex w-full items-center gap-3 rounded-2xl p-3.5 text-left transition active:scale-[0.99] ${surface} ${panel === "rate" ? "ring-2 ring-brand-accent" : ""}`}
                                    >
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-bg">
                                            <img src="/images/google-review-icon.png" alt="" className="h-7 w-7 object-contain" aria-hidden="true" />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block font-semibold text-brand-text">
                                                {card.review
                                                    ? "Update your feedback"
                                                    : "Share feedback"}
                                            </span>
                                            <span className="mt-0.5 block text-sm text-brand-muted">
                                                Tell us about your visit
                                            </span>
                                        </span>
                                        <LuChevronRight
                                            className="h-5 w-5 shrink-0 text-brand-muted"
                                            aria-hidden="true"
                                        />
                                    </button>
                                ))}
                            {shop.instagram_url && (
                                <a
                                    href={shop.instagram_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={`flex w-full items-center gap-3 rounded-2xl p-3.5 text-left transition active:scale-[0.99] ${surface}`}
                                >
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#E4405F]/10">
                                        <FaInstagram
                                            className="h-6 w-6 text-[#E4405F]"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold text-brand-text">
                                            Follow us on Instagram
                                        </span>
                                        <span className="mt-0.5 block text-sm text-brand-muted">
                                            See our latest updates
                                        </span>
                                    </span>
                                    <LuChevronRight
                                        className="h-5 w-5 shrink-0 text-brand-muted"
                                        aria-hidden="true"
                                    />
                                </a>
                            )}
                            {card?.wifi_ssid && (
                                <button
                                    type="button"
                                    onClick={() => togglePanel("wifi")}
                                    aria-expanded={panel === "wifi"}
                                    className={`flex w-full items-center gap-3 rounded-2xl p-3.5 text-left transition active:scale-[0.99] ${surface} ${panel === "wifi" ? "ring-2 ring-brand-accent" : ""}`}
                                >
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-bg">
                                        <LuWifi
                                            className="h-6 w-6 text-brand-text"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold text-brand-text">
                                            Guest Wi-Fi
                                        </span>
                                        <span className="mt-0.5 block text-sm text-brand-muted">
                                            Connect while you visit
                                        </span>
                                    </span>
                                    <LuChevronRight
                                        className="h-5 w-5 shrink-0 text-brand-muted"
                                        aria-hidden="true"
                                    />
                                </button>
                            )}
                        </div>
                    )}

                    {panel === "wifi" && card?.wifi_ssid && (
                        <div
                            className={`space-y-2 rounded-2xl p-4 text-sm ${surface}`}
                        >
                            <p className="flex justify-between gap-3">
                                <span className="text-brand-muted">
                                    Network
                                </span>
                                <span className="truncate font-medium text-brand-text">
                                    {card.wifi_ssid}
                                </span>
                            </p>
                            {card.wifi_password && (
                                <p className="flex items-center justify-between gap-3">
                                    <span className="text-brand-muted">
                                        Password
                                    </span>
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="truncate font-medium text-brand-text">
                                            {card.wifi_password}
                                        </span>
                                        <button
                                            type="button"
                                            onClick={handleCopyPassword}
                                            className="shrink-0 rounded-md bg-brand-accent px-2 py-1 text-xs font-semibold text-brand-accent-text"
                                        >
                                            {copied ? "Copied" : "Copy"}
                                        </button>
                                    </span>
                                </p>
                            )}
                        </div>
                    )}

                    {createPortal(
                        <AnimatePresence>
                            {(panel === "rate" || showReviewPrompt) && card && !preview && (
                                <motion.div
                                    className="fixed inset-0 z-100 flex min-h-dvh w-screen items-center justify-center bg-black/60 px-5 py-8"
                                    initial={{ opacity: 0 }}
                                    animate={{ opacity: 1 }}
                                    exit={{ opacity: 0 }}
                                    role="presentation"
                                    onClick={() => {
                                        setShowReviewPrompt(false);
                                        setPanel(null);
                                    }}
                                >
                                    <motion.div
                                        role="dialog"
                                        aria-modal="true"
                                        aria-labelledby="first-review-title"
                                        initial={{
                                            opacity: 0,
                                            y: 14,
                                            scale: 0.97,
                                        }}
                                        animate={{ opacity: 1, y: 0, scale: 1 }}
                                        exit={{ opacity: 0, y: 8, scale: 0.98 }}
                                        onClick={(event) =>
                                            event.stopPropagation()
                                        }
                                        className="w-full max-w-sm rounded-2xl bg-brand-card p-5 shadow-2xl"
                                    >
                                        <div className="mb-4 text-center">
                                            <h2
                                                id="first-review-title"
                                                className="font-heading text-lg font-semibold text-brand-text"
                                            >
                                                {showReviewPrompt
                                                    ? "How was your visit?"
                                                    : card.review
                                                      ? "Your feedback"
                                                      : "Share feedback"}
                                            </h2>
                                            <p className="mt-1 text-sm text-brand-muted">
                                                {showReviewPrompt
                                                    ? "Tell us about your experience. Your feedback goes to the shop."
                                                    : "Share your experience with the shop."}
                                            </p>
                                        </div>
                                        <RatingTile
                                            shopSlug={shop.slug}
                                            uuid={card.uuid}
                                            existingReview={card.review}
                                            googleReviewUrl={
                                                shop.google_review_url
                                            }
                                            surface=""
                                            onSaved={handleReviewSaved}
                                        />
                                        <button
                                            type="button"
                                            onClick={() => {
                                                setShowReviewPrompt(false);
                                                setPanel(null);
                                            }}
                                            className="mt-3 w-full py-2 text-sm font-medium text-brand-muted hover:text-brand-text"
                                        >
                                            Close
                                        </button>
                                    </motion.div>
                                </motion.div>
                            )}
                        </AnimatePresence>,
                        document.body,
                    )}
                </main>
            </div>

            {/* Full-screen QR for the staff scanner: plain white, big and easy to read. */}
            <AnimatePresence>
                {qrOpen && qrSrc && (
                    <motion.button
                        type="button"
                        onClick={() => setQrOpen(false)}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-30 flex flex-col items-center justify-center gap-4 bg-white px-8"
                        aria-label="Close QR code"
                    >
                        <img
                            src={qrSrc}
                            alt="Your loyalty card QR code"
                            className="w-full max-w-xs"
                        />
                        <p className="text-sm text-neutral-500">
                            Tap anywhere to close
                        </p>
                    </motion.button>
                )}
            </AnimatePresence>

            <AnimatePresence>
                {showModal && (
                    <RegistrationModal
                        key="registration-modal"
                        shopName={shop.name}
                        bannerUrl={shop.banner_url}
                        logoUrl={shop.logo_url}
                        signupIcon={shop.signup_icon}
                        rewardTitle={shop.reward_title}
                        submitting={submitting}
                        errors={errors}
                        onSubmit={handleRegister}
                        countries={phoneCountries}
                        shopCountry={shop.country}
                    />
                )}
            </AnimatePresence>

            {/* Hidden behind the sign-up modal it would only peek out from under. */}
            {/* Preview hides it too: its tabs lead to the saved customer's own cards. */}
            {!showModal && !preview && <BottomNav />}
        </>
    );
}
