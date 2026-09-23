import axios from 'axios';
import { AnimatePresence, motion } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import { IoStar } from 'react-icons/io5';
import { LuChevronDown, LuCircleCheck } from 'react-icons/lu';
import IconBadge from '@/Components/IconBadge';

const COMMENT_MAX_HEIGHT = 200;

export default function RatingTile({ shopSlug, uuid, existingReview }) {
    const [open, setOpen] = useState(false);
    const [rating, setRating] = useState(existingReview?.rating ?? 0);
    const [hoverRating, setHoverRating] = useState(0);
    const [comment, setComment] = useState(existingReview?.comment ?? '');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [justSaved, setJustSaved] = useState(false);
    const commentRef = useRef(null);

    // Grow the textarea to fit its content as the user types, instead of a
    // fixed row count that forces manual dragging or internal scrolling.
    useEffect(() => {
        const el = commentRef.current;
        if (!el) return;

        el.style.height = 'auto';
        el.style.height = `${Math.min(el.scrollHeight, COMMENT_MAX_HEIGHT)}px`;
    }, [comment, open]);

    function submit() {
        if (rating < 1) {
            setError('Pick a star rating first.');
            return;
        }

        setSubmitting(true);
        setError(null);

        axios
            .post(`/s/${shopSlug}/card/${uuid}/review`, { rating, comment: comment.trim() || null })
            .then(() => {
                setJustSaved(true);
                setTimeout(() => setJustSaved(false), 2500);
            })
            .catch((err) => {
                setError(err.response?.data?.message ?? 'Something went wrong — try again.');
            })
            .finally(() => setSubmitting(false));
    }

    const displayRating = hoverRating || rating;

    return (
        <div className="mt-3 rounded-brand border border-brand-border bg-brand-card shadow-sm">
            <button
                onClick={() => setOpen((o) => !o)}
                className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm font-medium text-brand-text"
            >
                <IconBadge>
                    <IoStar className="h-5 w-5" />
                </IconBadge>
                <span className="flex-1">{existingReview ? 'Update your rating' : 'Rate your visit'}</span>
                <motion.span animate={{ rotate: open ? 180 : 0 }} className="text-brand-muted" aria-hidden="true">
                    <LuChevronDown className="h-5 w-5" />
                </motion.span>
            </button>

            <AnimatePresence initial={false}>
                {open && (
                    <motion.div
                        key="rating-content"
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.25, ease: 'easeInOut' }}
                        className="overflow-hidden"
                    >
                        <div className="px-4 pb-4">
                            <p className="text-xs text-brand-muted">Your feedback goes straight to the shop.</p>

                            <div className="mt-2 flex gap-1" role="radiogroup" aria-label="Rating out of 5 stars">
                                {[1, 2, 3, 4, 5].map((value) => (
                                    <motion.button
                                        key={value}
                                        type="button"
                                        role="radio"
                                        aria-checked={rating === value}
                                        aria-label={`${value} star${value > 1 ? 's' : ''}`}
                                        onClick={() => setRating(value)}
                                        onMouseEnter={() => setHoverRating(value)}
                                        onMouseLeave={() => setHoverRating(0)}
                                        whileTap={{ scale: 0.85 }}
                                        animate={rating === value ? { scale: [1, 1.3, 1] } : { scale: 1 }}
                                        transition={{ duration: 0.35 }}
                                        className="p-0.5"
                                    >
                                        <IoStar
                                            className="h-7 w-7 transition-colors"
                                            style={{ color: value <= displayRating ? 'var(--color-brand-accent)' : 'var(--color-brand-border)' }}
                                        />
                                    </motion.button>
                                ))}
                            </div>

                            <textarea
                                ref={commentRef}
                                value={comment}
                                onChange={(e) => setComment(e.target.value)}
                                placeholder="Anything you'd like to add? (optional)"
                                rows={2}
                                maxLength={1000}
                                className="mt-3 w-full resize-none overflow-y-auto rounded border border-brand-border px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
                                style={{ maxHeight: COMMENT_MAX_HEIGHT }}
                            />

                            {error && (
                                <motion.p initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="mt-2 text-xs text-red-600">
                                    {error}
                                </motion.p>
                            )}

                            <motion.button
                                whileTap={{ scale: 0.96 }}
                                onClick={submit}
                                disabled={submitting}
                                className="mt-3 rounded-brand bg-brand-accent px-4 py-2 text-xs font-semibold text-brand-accent-text disabled:opacity-50"
                            >
                                <AnimatePresence mode="wait" initial={false}>
                                    {justSaved ? (
                                        <motion.span
                                            key="saved"
                                            initial={{ opacity: 0, scale: 0.8 }}
                                            animate={{ opacity: 1, scale: 1 }}
                                            exit={{ opacity: 0 }}
                                            className="flex items-center gap-1.5"
                                        >
                                            <LuCircleCheck className="h-4 w-4" />
                                            Thanks for your feedback!
                                        </motion.span>
                                    ) : (
                                        <motion.span key="idle" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
                                            {submitting ? 'Saving…' : existingReview ? 'Update rating' : 'Submit rating'}
                                        </motion.span>
                                    )}
                                </AnimatePresence>
                            </motion.button>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
