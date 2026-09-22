import { Head } from '@inertiajs/react';
import axios from 'axios';
import { AnimatePresence, motion } from 'framer-motion';
import QRCode from 'qrcode';
import { useEffect, useState } from 'react';
import BottomNav from '@/Components/BottomNav';
import { StoreIcon } from '@/Components/Icons';
import IconBadge from '@/Components/IconBadge';
import { CUSTOMER_UUID_KEY } from '@/lib/storage';

function SkeletonTile() {
    return (
        <div className="flex animate-pulse items-stretch gap-3 overflow-hidden rounded-2xl border border-brand-border bg-brand-card p-4">
            <div className="min-w-0 flex-1 space-y-3">
                <div className="flex items-center gap-2.5">
                    <div className="h-9 w-9 rounded-full bg-brand-border" />
                    <div className="flex-1 space-y-2">
                        <div className="h-3 w-2/3 rounded bg-brand-border" />
                        <div className="h-2 w-1/2 rounded bg-brand-border" />
                    </div>
                </div>
                <div className="flex gap-1.5">
                    {Array.from({ length: 6 }, (_, i) => (
                        <div key={i} className="h-5 w-5 rounded-full bg-brand-border" />
                    ))}
                </div>
            </div>
            <div className="h-16 w-16 shrink-0 rounded-lg bg-brand-border" />
        </div>
    );
}

function QrLightbox({ card, qrSrc, onClose }) {
    return (
        <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onClick={onClose}
            className="fixed inset-0 z-30 flex items-center justify-center bg-black/60 p-6"
        >
            <motion.div
                initial={{ opacity: 0, scale: 0.92 }}
                animate={{ opacity: 1, scale: 1 }}
                exit={{ opacity: 0, scale: 0.92 }}
                transition={{ type: 'spring', stiffness: 300, damping: 28 }}
                onClick={(e) => e.stopPropagation()}
                className="w-full max-w-xs rounded-2xl border border-brand-border bg-brand-card p-6 text-center shadow-xl"
            >
                <p className="text-sm font-bold text-brand-text">{card.shop_name}</p>
                <p className="mt-1 text-xs text-brand-muted">{card.reward_title}</p>

                {qrSrc ? (
                    <img
                        src={qrSrc}
                        alt={`QR code for ${card.shop_name}`}
                        className="mx-auto mt-4 h-56 w-56 rounded-brand border border-brand-border bg-white p-2"
                    />
                ) : (
                    <div className="mx-auto mt-4 h-56 w-56 animate-pulse rounded-brand bg-brand-border" />
                )}

                <p className="mt-3 text-xs text-brand-muted">Show this to staff to add a stamp</p>

                <button
                    onClick={onClose}
                    className="mt-4 rounded-brand bg-brand-accent px-4 py-2 text-xs font-semibold text-brand-accent-text"
                >
                    Close
                </button>
            </motion.div>
        </motion.div>
    );
}

function ShopCardTile({ uuid, card }) {
    const [qrSrc, setQrSrc] = useState(null);
    const [lightboxOpen, setLightboxOpen] = useState(false);

    useEffect(() => {
        QRCode.toDataURL(`TOKEN:${uuid}|SHOP:${card.shop_id}`, { margin: 1, width: 240 })
            .then(setQrSrc)
            .catch(() => setQrSrc(null));
    }, [uuid, card.shop_id]);

    const rewardReady = card.stamps >= card.max_stamps;

    return (
        <>
            <div className="relative flex items-stretch gap-3 overflow-hidden rounded-2xl border border-brand-border bg-brand-card p-4 shadow-sm">
                {/* Spine stripe - a plain white rounded rectangle didn't read as
                    a "card" the way a physical loyalty card does. */}
                <div className="absolute inset-y-0 left-0 w-1.5 bg-brand-accent" />

                <div className="min-w-0 flex-1 pl-2">
                    <div className="flex items-center gap-2.5">
                        <IconBadge>
                            <StoreIcon className="h-4 w-4" />
                        </IconBadge>
                        <div className="min-w-0">
                            <p className="truncate text-sm font-bold text-brand-text">{card.shop_name}</p>
                            <p className="truncate text-xs text-brand-muted">{card.reward_title}</p>
                        </div>
                    </div>

                    <div className="mt-3 flex flex-wrap gap-1.5">
                        {Array.from({ length: card.max_stamps }, (_, i) => i + 1).map((i) => {
                            const filled = i <= card.stamps;

                            return (
                                <motion.span
                                    key={i}
                                    initial={{ scale: 0 }}
                                    animate={{ scale: 1 }}
                                    transition={{ delay: i * 0.02 }}
                                    className="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
                                    style={{
                                        background: filled ? 'var(--color-brand-accent)' : 'var(--color-brand-border)',
                                        color: filled ? 'var(--color-brand-accent-text)' : 'var(--color-brand-muted)',
                                    }}
                                >
                                    {filled && '✓'}
                                </motion.span>
                            );
                        })}
                    </div>

                    <p className="mt-2 text-xs font-medium text-brand-muted">
                        {card.stamps}/{card.max_stamps} stamps
                        {rewardReady && <span className="ml-1 font-semibold text-brand-accent">· Reward ready!</span>}
                    </p>
                </div>

                {/* Perforated stub, like a ticket - separates the QR from the
                    card body instead of hiding it behind an accordion toggle. */}
                <button
                    onClick={() => setLightboxOpen(true)}
                    className="flex shrink-0 flex-col items-center justify-center gap-1 border-l border-dashed border-brand-border pl-3"
                >
                    {qrSrc ? (
                        <img
                            src={qrSrc}
                            alt={`QR code for ${card.shop_name}`}
                            className="h-16 w-16 rounded-lg border border-brand-border bg-white p-1"
                        />
                    ) : (
                        <div className="h-16 w-16 animate-pulse rounded-lg bg-brand-border" />
                    )}
                    <span className="text-[10px] font-medium text-brand-muted">Tap to scan</span>
                </button>
            </div>

            <AnimatePresence>{lightboxOpen && <QrLightbox card={card} qrSrc={qrSrc} onClose={() => setLightboxOpen(false)} />}</AnimatePresence>
        </>
    );
}

export default function MyCards() {
    const [uuid, setUuid] = useState(null);
    const [cards, setCards] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const storedUuid = window.localStorage.getItem(CUSTOMER_UUID_KEY);

        if (!storedUuid) {
            setLoading(false);
            return;
        }

        setUuid(storedUuid);

        axios
            .get(`/my-cards/${storedUuid}`)
            .then(({ data }) => setCards(data.cards))
            .catch(() => setCards([]))
            .finally(() => setLoading(false));
    }, []);

    const isEmpty = !loading && (!uuid || cards?.length === 0);

    return (
        <>
            <Head title="My Cards" />
            <div className="min-h-screen bg-brand-bg pb-24">
                <div className="mx-auto max-w-sm px-5 pt-8">
                    <h1 className="font-heading text-xl font-bold text-brand-text">My Cards</h1>
                    <p className="mt-1 text-sm text-brand-muted">All your loyalty cards in one place.</p>

                    <div className="mt-5 space-y-3">
                        {loading && (
                            <>
                                <SkeletonTile />
                                <SkeletonTile />
                            </>
                        )}

                        {isEmpty && (
                            <div className="rounded-brand border border-dashed border-brand-border bg-brand-card p-6 text-center">
                                <p className="text-sm text-brand-muted">
                                    No cards yet. Scan a shop's QR code or open their card link to get started.
                                </p>
                            </div>
                        )}

                        {cards?.map((card, index) => (
                            <motion.div
                                key={card.shop_id}
                                initial={{ opacity: 0, y: 10 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: index * 0.05 }}
                            >
                                <ShopCardTile uuid={uuid} card={card} />
                            </motion.div>
                        ))}
                    </div>
                </div>
            </div>

            <BottomNav />
        </>
    );
}
