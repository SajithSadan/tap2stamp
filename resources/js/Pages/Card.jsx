import { Head } from '@inertiajs/react';
import axios from 'axios';
import { AnimatePresence, motion } from 'framer-motion';
import Pusher from 'pusher-js';
import QRCode from 'qrcode';
import { useEffect, useMemo, useRef, useState } from 'react';
import BottomNav from '@/Components/BottomNav';
import OfflineBanner from '@/Components/OfflineBanner';
import RegistrationModal from '@/Components/RegistrationModal';
import RatingTile from '@/Components/RatingTile';
import { HiSparkles } from 'react-icons/hi2';
import { FaInstagram } from 'react-icons/fa6';
import { IoStar } from 'react-icons/io5';
import { LuStore, LuWifi } from 'react-icons/lu';
import { PiGiftFill } from 'react-icons/pi';
import { CUSTOMER_UUID_KEY, LAST_SHOP_SLUG_KEY } from '@/lib/storage';
import StampGrid from '@/Components/StampGrid';
import { useDocumentTheme } from '@/lib/theme';

function copyToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text);
    }

    // Fallback for browsers/contexts without the async Clipboard API.
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
        document.execCommand('copy');
    } finally {
        document.body.removeChild(textarea);
    }

    return Promise.resolve();
}

/** Section panels: solid, with a hairline border and no shadow. */
const SURFACE = 'border border-brand-border bg-brand-card';

/** A quick-action button (Follow / Review / Wi-Fi): icon + one word, equal widths in a row. */
const actionClass = 'flex items-center justify-center gap-2 rounded-brand py-2.5 text-sm font-semibold text-brand-text transition active:scale-[0.98]';

function SkeletonCard({ surface }) {
    return (
        <div className={`animate-pulse rounded-brand p-4 ${surface}`}>
            <div className="h-3 w-28 rounded bg-brand-border" />
            <div className="mt-3 h-2 w-full rounded-full bg-brand-border" />
            <div className="mt-4 grid grid-cols-6 gap-2">
                {Array.from({ length: 6 }, (_, i) => (
                    <div key={i} className="aspect-square rounded-full bg-brand-border" />
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
                    animate={{ opacity: 0, x: p.x, y: p.y, scale: 1, rotate: p.rotate }}
                    transition={{ duration: 0.9, ease: 'easeOut' }}
                    className="absolute text-brand-accent-text"
                >
                    <HiSparkles className="h-3.5 w-3.5" />
                </motion.span>
            ))}
        </div>
    );
}

export default function Card({ shop, theme }) {
    // The owner's chosen look (Dashboard → Theme), applied to this page only.
    useDocumentTheme(theme);
    const surface = SURFACE;

    const [card, setCard] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [qrSrc, setQrSrc] = useState(null);
    // Which quick-action panel is open under the action row: 'rate', 'wifi' or none.
    const [panel, setPanel] = useState(null);
    const togglePanel = (name) => setPanel((open) => (open === name ? null : name));
    const [copied, setCopied] = useState(false);
    const [celebrate, setCelebrate] = useState(false);
    const [redeemedToast, setRedeemedToast] = useState(false);
    const [loadError, setLoadError] = useState(false);

    const prevStampsRef = useRef(0);
    const wasReadyRef = useRef(false);
    const audioCtxRef = useRef(null);
    const celebrateTimeoutRef = useRef(null);

    useEffect(() => {
        const unlockAudio = () => {
            if (!audioCtxRef.current) {
                try {
                    const Ctx = window.AudioContext || window.webkitAudioContext;
                    if (Ctx) audioCtxRef.current = new Ctx();
                } catch {
                    // Web Audio unsupported
                }
            } else if (audioCtxRef.current.state === 'suspended') {
                audioCtxRef.current.resume();
            }
        };

        window.addEventListener('click', unlockAudio, { once: true });
        window.addEventListener('touchstart', unlockAudio, { once: true });
        return () => {
            window.removeEventListener('click', unlockAudio);
            window.removeEventListener('touchstart', unlockAudio);
        };
    }, []);

    useEffect(() => {
        window.localStorage.setItem(LAST_SHOP_SLUG_KEY, shop.slug);
    }, [shop.slug]);

    function loadCard() {
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
                // Only a 404 means "not registered here" - anything else
                // (offline, rate limited, server error) is temporary, and
                // forgetting the uuid then would orphan the customer's cards.
                if (error.response?.status === 404) {
                    window.localStorage.removeItem(CUSTOMER_UUID_KEY);
                    setShowModal(true);
                } else {
                    setLoadError(true);
                }
            })
            .finally(() => setLoading(false));
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

        QRCode.toDataURL(`TOKEN:${card.uuid}|SHOP:${card.shop_id}`, { margin: 1, width: 240 })
            .then(setQrSrc)
            .catch(() => setQrSrc(null));
    }, [card]);

    function triggerCelebration() {
        setCelebrate(true);
        if (celebrateTimeoutRef.current) clearTimeout(celebrateTimeoutRef.current);
        celebrateTimeoutRef.current = setTimeout(() => setCelebrate(false), 1000);
    }

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
        if (card) prevStampsRef.current = card.stamps;
    }, [card?.stamps]);

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
        if (ctx.state === 'suspended') {
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
            gain.gain.exponentialRampToValueAtTime(0.0001, now + i * 0.12 + 0.35);
            osc.start(now + i * 0.12);
            osc.stop(now + i * 0.12 + 0.35);
        });
    }

    // Real-time updates from staff scans (Stage 7). Resilience: if Pusher
    // isn't configured or the connection fails, the page still works via
    // the normal fetch-on-load path above - this is purely additive.
    useEffect(() => {
        if (!card?.uuid) return;

        const key = import.meta.env.VITE_PUSHER_APP_KEY;
        if (!key) return;

        let pusher;
        let channel;
        const channelName = `card.${card.uuid}.${card.shop_id}`;

        try {
            pusher = new Pusher(key, { cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER });
            channel = pusher.subscribe(channelName);

            channel.bind('card.updated', (data) => {
                setCard((prev) => (prev ? { ...prev, stamps: data.stamps, max_stamps: data.max_stamps } : prev));
                triggerCelebration();
                playChime();
                if (navigator.vibrate) navigator.vibrate(60);

                if (data.action === 'reward_redeemed') {
                    setRedeemedToast(true);
                    setTimeout(() => setRedeemedToast(false), 3000);
                }
            });
        } catch {
            // Connection failed - customer can still refresh to see updates.
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
            if (document.visibilityState !== 'visible' || !card?.uuid) return;

            axios
                .get(`/s/${shop.slug}/card/${card.uuid}`)
                .then(({ data }) => setCard(data))
                .catch(() => {});
        }

        document.addEventListener('visibilitychange', handleVisibility);
        return () => document.removeEventListener('visibilitychange', handleVisibility);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [shop.slug, card?.uuid]);

    function handleRegister(name, phone, marketingConsent) {
        setSubmitting(true);
        setErrors({});

        axios
            .post(`/s/${shop.slug}/register`, { name, phone, marketing_consent: marketingConsent })
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
                    setErrors({ general: 'Too many tries. Please wait a minute and try again.' });
                } else if (!error.response) {
                    setErrors({ general: "Can't connect right now. Check your connection and try again." });
                } else {
                    setErrors({ general: 'Something went wrong. Please try again.' });
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
                {/* Banner photo (Theme → Banner & logo), else the theme gradient, with the logo,
                    name and reward inside it on a dark fade. No motion. */}
                <header className="relative overflow-hidden bg-brand-deep">
                    {shop.banner_url ? (
                        <>
                            <img src={shop.banner_url} alt="" className="absolute inset-0 h-full w-full object-cover" />
                            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/35 to-black/5" />
                        </>
                    ) : (
                        <div className="absolute inset-0 bg-gradient-to-br from-brand-accent to-brand-deep" />
                    )}

                    <div className="relative mx-auto flex max-w-sm items-end gap-3 px-4 pb-8 pt-24 sm:pt-28">
                        <span className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white ring-2 ring-white/90">
                            {shop.logo_url ? (
                                <img src={shop.logo_url} alt={`${shop.name} logo`} className="h-full w-full object-cover" />
                            ) : (
                                <LuStore className="h-7 w-7 text-brand-accent" />
                            )}
                        </span>
                        <div className="min-w-0">
                            <h1 className="line-clamp-2 font-heading text-xl font-bold leading-tight text-white">{shop.name}</h1>
                            <p className="mt-0.5 flex items-center gap-1.5 text-sm text-white/85">
                                <PiGiftFill className="h-4 w-4 shrink-0" aria-hidden="true" />
                                <span className="truncate">{shop.reward_title}</span>
                            </p>
                        </div>
                    </div>
                </header>

                {/* Content sheet over the header's bottom edge; every section evenly spaced. */}
                <main className="relative -mt-4 space-y-3 rounded-t-3xl bg-brand-bg px-4 pt-4 [&>*]:mx-auto [&>*]:max-w-sm">
                    {/* Quick actions: only the ones the shop has set up. */}
                    {(shop.instagram_url || card) && (
                        <div className="grid auto-cols-fr grid-flow-col gap-2">
                            {shop.instagram_url && (
                                <a href={shop.instagram_url} target="_blank" rel="noopener noreferrer" className={`${actionClass} ${surface}`}>
                                    <FaInstagram className="h-[18px] w-[18px] text-[#E4405F]" aria-hidden="true" /> Follow
                                </a>
                            )}
                            {/* One rating: saved to the shop, then optionally posted on Google too. */}
                            {card && (
                                <button
                                    type="button"
                                    onClick={() => togglePanel('rate')}
                                    aria-expanded={panel === 'rate'}
                                    className={`${actionClass} ${surface} ${panel === 'rate' ? 'ring-2 ring-brand-accent' : ''}`}
                                >
                                    <IoStar className="h-[18px] w-[18px] text-[#F5B400]" aria-hidden="true" /> {card.review ? 'Rated' : 'Rate'}
                                </button>
                            )}
                            {card?.wifi_ssid && (
                                <button
                                    type="button"
                                    onClick={() => togglePanel('wifi')}
                                    aria-expanded={panel === 'wifi'}
                                    className={`${actionClass} ${surface} ${panel === 'wifi' ? 'ring-2 ring-brand-accent' : ''}`}
                                >
                                    <LuWifi className="h-[18px] w-[18px] text-brand-accent" aria-hidden="true" /> Wi-Fi
                                </button>
                            )}
                        </div>
                    )}

                    {panel === 'rate' && card && (
                        <RatingTile shopSlug={shop.slug} uuid={card.uuid} existingReview={card.review} googleReviewUrl={shop.google_review_url} surface={surface} />
                    )}

                    {panel === 'wifi' && card?.wifi_ssid && (
                        <div className={`space-y-2 rounded-brand p-3 text-sm ${surface}`}>
                            <p className="flex justify-between gap-3">
                                <span className="text-brand-muted">Network</span>
                                <span className="truncate font-medium text-brand-text">{card.wifi_ssid}</span>
                            </p>
                            {card.wifi_password && (
                                <p className="flex items-center justify-between gap-3">
                                    <span className="text-brand-muted">Password</span>
                                    <span className="flex min-w-0 items-center gap-2">
                                        <span className="truncate font-medium text-brand-text">{card.wifi_password}</span>
                                        <button
                                            type="button"
                                            onClick={handleCopyPassword}
                                            className="shrink-0 rounded-md bg-brand-accent px-2 py-1 text-xs font-semibold text-brand-accent-text"
                                        >
                                            {copied ? 'Copied' : 'Copy'}
                                        </button>
                                    </span>
                                </p>
                            )}
                        </div>
                    )}

                    {loading && <SkeletonCard surface={surface} />}

                    {loadError && !loading && (
                        <div className={`rounded-brand p-4 text-center ${surface}`}>
                            <p className="text-sm text-brand-muted">We couldn't load your card just now.</p>
                            <button onClick={loadCard} className="mt-3 rounded-brand bg-brand-accent px-4 py-2 text-sm font-semibold text-brand-accent-text">
                                Try again
                            </button>
                        </div>
                    )}

                    {card && (
                        <motion.section
                            initial={{ opacity: 0, y: 8 }}
                            animate={{ opacity: 1, y: 0 }}
                            className={`relative rounded-brand p-4 ${surface}`}
                        >
                            {celebrate && <Celebration />}

                            <AnimatePresence>
                                {rewardReady && (
                                    <motion.p
                                        initial={{ opacity: 0, height: 0, marginBottom: 0 }}
                                        animate={{ opacity: 1, height: 'auto', marginBottom: 12 }}
                                        exit={{ opacity: 0, height: 0, marginBottom: 0 }}
                                        className="flex items-center gap-2 overflow-hidden rounded-brand bg-brand-accent px-3 py-2.5 text-xs font-semibold text-brand-accent-text"
                                    >
                                        <HiSparkles className="h-4 w-4 shrink-0" />
                                        Reward unlocked — show this screen to staff!
                                    </motion.p>
                                )}
                            </AnimatePresence>

                            <p className="flex items-baseline justify-between">
                                <span className="font-heading text-2xl font-bold tabular-nums text-brand-text">
                                    {card.stamps}
                                    <span className="text-base font-semibold text-brand-muted">/{card.max_stamps} stamps</span>
                                </span>
                                {card.rewards_claimed > 0 && <span className="text-xs text-brand-muted">{card.rewards_claimed} claimed</span>}
                            </p>

                            <StampGrid className="mt-3" total={card.max_stamps} stamps={card.stamps} previous={previousStamps} icon={shop.stamp_icon} />

                            {qrSrc && (
                                <>
                                    <img
                                        src={qrSrc}
                                        alt="Your loyalty card QR code"
                                        className="mx-auto mt-4 h-40 w-40 rounded-brand border border-brand-border bg-white p-2"
                                    />
                                    <p className="mt-1.5 text-center text-xs text-brand-muted">Show to staff</p>
                                </>
                            )}
                        </motion.section>
                    )}
                </main>
            </div>

            <AnimatePresence>
                {showModal && (
                    <RegistrationModal
                        key="registration-modal"
                        shopName={shop.name}
                        bannerUrl={shop.banner_url}
                        logoUrl={shop.logo_url}
                        signupIcon={shop.signup_icon}
                        submitting={submitting}
                        errors={errors}
                        onSubmit={handleRegister}
                    />
                )}
            </AnimatePresence>

            {/* Hidden behind the sign-up modal it would only peek out from under. */}
            {!showModal && <BottomNav />}
        </>
    );
}
