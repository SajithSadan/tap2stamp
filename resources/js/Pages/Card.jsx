import { Head } from '@inertiajs/react';
import axios from 'axios';
import { AnimatePresence, motion } from 'framer-motion';
import QRCode from 'qrcode';
import { useEffect, useMemo, useRef, useState } from 'react';
import BottomNav from '@/Components/BottomNav';
import RegistrationModal from '@/Components/RegistrationModal';
import RatingTile from '@/Components/RatingTile';
import { ArrowRightIcon, CameraIcon, ChevronDownIcon, SparkleIcon, StoreIcon, WifiIcon } from '@/Components/Icons';
import IconBadge from '@/Components/IconBadge';
import { CUSTOMER_UUID_KEY, LAST_SHOP_SLUG_KEY } from '@/lib/storage';

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

function Tile({ href, icon, children }) {
    return (
        <motion.a
            whileHover={{ scale: 1.015 }}
            whileTap={{ scale: 0.98 }}
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-3 rounded-brand border border-brand-border bg-brand-card px-4 py-3 text-sm font-medium text-brand-text shadow-sm transition-shadow hover:shadow-md"
        >
            <IconBadge>{icon}</IconBadge>
            <span className="flex-1">{children}</span>
            <ArrowRightIcon className="h-4 w-4 shrink-0 text-brand-muted" aria-hidden="true" />
        </motion.a>
    );
}

function SkeletonCard() {
    return (
        <div className="mt-5 animate-pulse rounded-brand border border-brand-border bg-brand-card p-5">
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
                    <SparkleIcon className="h-3.5 w-3.5" />
                </motion.span>
            ))}
        </div>
    );
}

export default function Card({ shop }) {
    const [card, setCard] = useState(null);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});
    const [qrSrc, setQrSrc] = useState(null);
    const [wifiOpen, setWifiOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const [celebrate, setCelebrate] = useState(false);

    const prevStampsRef = useRef(0);
    const wasReadyRef = useRef(false);

    useEffect(() => {
        window.localStorage.setItem(LAST_SHOP_SLUG_KEY, shop.slug);
    }, [shop.slug]);

    useEffect(() => {
        const uuid = window.localStorage.getItem(CUSTOMER_UUID_KEY);

        if (!uuid) {
            setShowModal(true);
            setLoading(false);
            return;
        }

        axios
            .get(`/s/${shop.slug}/card/${uuid}`)
            .then(({ data }) => setCard(data))
            .catch(() => {
                window.localStorage.removeItem(CUSTOMER_UUID_KEY);
                setShowModal(true);
            })
            .finally(() => setLoading(false));
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

    // Detect the moment the card crosses over into "reward ready" to fire a
    // one-off celebration burst, without re-triggering on every re-render.
    useEffect(() => {
        if (!card) return;

        const ready = card.stamps >= card.max_stamps;

        if (ready && !wasReadyRef.current) {
            setCelebrate(true);
            const timeout = setTimeout(() => setCelebrate(false), 1000);
            wasReadyRef.current = true;
            return () => clearTimeout(timeout);
        }

        wasReadyRef.current = ready;
    }, [card?.stamps, card?.max_stamps]);

    useEffect(() => {
        if (card) prevStampsRef.current = card.stamps;
    }, [card?.stamps]);

    function handleRegister(name, phone) {
        setSubmitting(true);
        setErrors({});

        axios
            .post(`/s/${shop.slug}/register`, { name, phone })
            .then(({ data }) => {
                window.localStorage.setItem(CUSTOMER_UUID_KEY, data.uuid);
                setCard(data);
                setShowModal(false);
            })
            .catch((error) => {
                if (error.response?.status === 422) {
                    setErrors(error.response.data.errors);
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
    const progressPct = card ? Math.min(100, (card.stamps / card.max_stamps) * 100) : 0;
    const rewardReady = card && card.stamps >= card.max_stamps;

    return (
        <>
            <Head title={shop.name} />
            <div className="relative min-h-screen overflow-hidden bg-brand-bg pb-24">
                <div className="pointer-events-none absolute -right-16 top-24 h-56 w-56 rounded-full bg-brand-accent/15 blur-3xl" />
                <div className="pointer-events-none absolute -left-20 top-96 h-64 w-64 rounded-full bg-brand-accent/10 blur-3xl" />

                {/* Default banner - a real logo/banner upload isn't built yet, so
                    this is a deliberate placeholder rather than empty space. */}
                <motion.div
                    initial={{ opacity: 0 }}
                    animate={{ opacity: 1 }}
                    transition={{ duration: 0.5 }}
                    className="h-28 w-full bg-gradient-to-br from-brand-accent to-brand-text sm:h-36"
                />

                <div className="relative mx-auto max-w-sm px-5">
                    <motion.div
                        initial={{ scale: 0, opacity: 0 }}
                        animate={{ scale: 1, opacity: 1 }}
                        transition={{ delay: 0.15, type: 'spring', stiffness: 260, damping: 20 }}
                        className="-mt-10 flex justify-center"
                    >
                        <motion.div
                            animate={{ y: [0, -4, 0] }}
                            transition={{ delay: 1, duration: 3, repeat: Infinity, ease: 'easeInOut' }}
                            className="flex h-20 w-20 items-center justify-center rounded-full border-4 border-brand-bg bg-brand-card shadow-md"
                        >
                            <StoreIcon className="h-9 w-9 text-brand-accent" />
                        </motion.div>
                    </motion.div>

                    <motion.div
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.25, duration: 0.4 }}
                        className="mt-3 text-center"
                    >
                        <h1 className="font-heading text-xl font-bold text-brand-text">{shop.name}</h1>
                        <p className="mt-1 text-sm text-brand-muted">{shop.reward_title}</p>
                    </motion.div>

                    {shop.instagram_url && (
                        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.35 }} className="mt-5">
                            <Tile href={shop.instagram_url} icon={<CameraIcon className="h-5 w-5" />}>
                                Follow us on Instagram
                            </Tile>
                        </motion.div>
                    )}

                    {loading && <SkeletonCard />}

                    {card && (
                        <motion.div
                            initial={{ opacity: 0, y: 12 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: 0.1 }}
                            className="mt-5 rounded-brand border border-brand-border bg-brand-card p-5 shadow-sm"
                        >
                            <AnimatePresence>
                                {rewardReady && (
                                    <motion.div
                                        initial={{ opacity: 0, scale: 0.9, height: 0, marginBottom: 0 }}
                                        animate={{ opacity: 1, scale: 1, height: 'auto', marginBottom: 16 }}
                                        exit={{ opacity: 0, scale: 0.9, height: 0, marginBottom: 0 }}
                                        className="relative flex items-center gap-2 overflow-hidden rounded-brand bg-brand-accent px-3 py-2.5 text-xs font-semibold text-brand-accent-text"
                                    >
                                        <SparkleIcon className="h-4 w-4 shrink-0" />
                                        Reward unlocked — show this screen to staff!
                                        {celebrate && <Celebration />}
                                    </motion.div>
                                )}
                            </AnimatePresence>

                            <div className="flex items-center justify-between">
                                <p className="text-sm font-semibold text-brand-text">
                                    {card.stamps} / {card.max_stamps} stamps
                                </p>
                                <p className="text-xs text-brand-muted">
                                    {card.rewards_claimed} reward{card.rewards_claimed === 1 ? '' : 's'} claimed
                                </p>
                            </div>

                            <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-brand-border">
                                <motion.div
                                    className="h-full rounded-full bg-brand-accent"
                                    initial={{ width: 0 }}
                                    animate={{ width: `${progressPct}%` }}
                                    transition={{ duration: 0.6, ease: 'easeOut' }}
                                />
                            </div>

                            <div className="mt-4 grid grid-cols-6 gap-2">
                                {Array.from({ length: card.max_stamps }, (_, i) => i + 1).map((i) => {
                                    const filled = i <= card.stamps;
                                    const isNew = filled && i > previousStamps;

                                    return (
                                        <motion.div
                                            key={i}
                                            initial={{ scale: 0, opacity: 0 }}
                                            animate={{ scale: 1, opacity: 1 }}
                                            transition={{ delay: i * 0.03, type: 'spring', stiffness: 400, damping: 20 }}
                                            className="flex aspect-square items-center justify-center rounded-full text-xs font-semibold"
                                            style={{
                                                background: filled ? 'var(--color-brand-accent)' : 'var(--color-brand-border)',
                                                color: filled ? 'var(--color-brand-accent-text)' : 'var(--color-brand-muted)',
                                            }}
                                        >
                                            <motion.span
                                                animate={isNew ? { scale: [0, 1.5, 1] } : { scale: 1 }}
                                                transition={{ duration: 0.5 }}
                                            >
                                                {filled && '✓'}
                                            </motion.span>
                                        </motion.div>
                                    );
                                })}
                            </div>

                            <AnimatePresence>
                                {qrSrc && (
                                    <motion.div
                                        initial={{ opacity: 0, scale: 0.9 }}
                                        animate={{ opacity: 1, scale: 1 }}
                                        exit={{ opacity: 0 }}
                                        className="mt-5 flex justify-center"
                                    >
                                        <div className="flex flex-col items-center">
                                            <img
                                                src={qrSrc}
                                                alt="Your loyalty card QR code"
                                                className="h-40 w-40 rounded-brand border border-brand-border bg-white p-2"
                                            />
                                            <p className="mt-2 text-xs text-brand-muted">Show this to staff to add a stamp</p>
                                        </div>
                                    </motion.div>
                                )}
                            </AnimatePresence>
                        </motion.div>
                    )}

                    {card && (
                        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.15 }}>
                            <RatingTile shopSlug={shop.slug} uuid={card.uuid} existingReview={card.review} />
                        </motion.div>
                    )}

                    {card?.wifi_ssid && (
                        <motion.div
                            initial={{ opacity: 0, y: 12 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: 0.2 }}
                            className="mt-3 rounded-brand border border-brand-border bg-brand-card shadow-sm"
                        >
                            <button
                                onClick={() => setWifiOpen((open) => !open)}
                                className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-medium text-brand-text"
                            >
                                <IconBadge>
                                    <WifiIcon className="h-5 w-5" />
                                </IconBadge>
                                <span className="flex-1">Free Wi-Fi</span>
                                <motion.span animate={{ rotate: wifiOpen ? 180 : 0 }} className="text-brand-muted" aria-hidden="true">
                                    <ChevronDownIcon className="h-5 w-5" />
                                </motion.span>
                            </button>
                            <AnimatePresence initial={false}>
                                {wifiOpen && (
                                    <motion.div
                                        key="wifi-content"
                                        initial={{ height: 0, opacity: 0 }}
                                        animate={{ height: 'auto', opacity: 1 }}
                                        exit={{ height: 0, opacity: 0 }}
                                        transition={{ duration: 0.25, ease: 'easeInOut' }}
                                        className="overflow-hidden"
                                    >
                                        <div className="px-4 pb-4 text-sm text-brand-muted">
                                            <p>
                                                Network: <span className="text-brand-text">{card.wifi_ssid}</span>
                                            </p>
                                            {card.wifi_password && (
                                                <>
                                                    <p className="mt-1">
                                                        Password: <span className="text-brand-text">{card.wifi_password}</span>
                                                    </p>
                                                    <motion.button
                                                        whileTap={{ scale: 0.95 }}
                                                        onClick={handleCopyPassword}
                                                        className="mt-3 rounded-brand bg-brand-accent px-3 py-1.5 text-xs font-semibold text-brand-accent-text"
                                                    >
                                                        {copied ? 'Copied!' : 'Copy password'}
                                                    </motion.button>
                                                </>
                                            )}
                                        </div>
                                    </motion.div>
                                )}
                            </AnimatePresence>
                        </motion.div>
                    )}
                </div>
            </div>

            <AnimatePresence>
                {showModal && (
                    <RegistrationModal
                        key="registration-modal"
                        shopName={shop.name}
                        rewardTitle={shop.reward_title}
                        submitting={submitting}
                        errors={errors}
                        onSubmit={handleRegister}
                    />
                )}
            </AnimatePresence>

            <BottomNav />
        </>
    );
}
