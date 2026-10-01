import axios from "axios";
import { motion } from "framer-motion";
import { useEffect, useRef, useState } from "react";
import { FcGoogle } from "react-icons/fc";
import { IoStar } from "react-icons/io5";
import { LuCircleCheck } from "react-icons/lu";

// The customer's rating of the shop, opened from the "Rate" button on the card
// page. It's saved to the shop (reviews table). Then, if the shop has a Google
// review link, the customer can post it on Google too: Google only accepts
// reviews from the customer's own account, so we copy their comment and open
// the shop's Google review page for them to paste it. Offered after EVERY
// rating, good or bad - only sending happy customers to Google ("review
// gating") breaks Google's rules.

const COMMENT_MAX_HEIGHT = 160;

function copy(text) {
    return (
        navigator.clipboard?.writeText(text).catch(() => {}) ??
        Promise.resolve()
    );
}

export default function RatingTile({
    shopSlug,
    uuid,
    existingReview,
    googleReviewUrl = null,
    surface = "border border-brand-border bg-brand-card",
    onSaved,
}) {
    const [rating, setRating] = useState(existingReview?.rating ?? 0);
    const [hoverRating, setHoverRating] = useState(0);
    const [comment, setComment] = useState(existingReview?.comment ?? "");
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);
    const [saved, setSaved] = useState(false);
    const commentRef = useRef(null);

    // Grow the comment box with its text instead of scrolling inside a fixed height.
    useEffect(() => {
        const el = commentRef.current;
        if (!el) return;
        el.style.height = "auto";
        el.style.height = `${Math.min(el.scrollHeight, COMMENT_MAX_HEIGHT)}px`;
    }, [comment, saved]);

    function submit() {
        if (rating < 1) {
            setError("Pick a star rating first.");
            return;
        }

        setSubmitting(true);
        setError(null);

        axios
            .post(`/s/${shopSlug}/card/${uuid}/review`, {
                rating,
                comment: comment.trim() || null,
            })
            .then(({ data }) => {
                setSaved(true);
                onSaved?.(data);
            })
            .catch((err) =>
                setError(
                    err.response?.data?.message ??
                        "Something went wrong — try again.",
                ),
            )
            .finally(() => setSubmitting(false));
    }

    function postOnGoogle() {
        // Opened in the tap itself so it isn't blocked; the copy finishes alongside.
        if (comment.trim()) copy(comment.trim());
        window.open(googleReviewUrl, "_blank", "noopener");
    }

    if (saved) {
        return (
            <div className={`rounded-2xl p-4 text-center ${surface}`}>
                <p className="flex items-center justify-center gap-1.5 text-sm font-semibold text-brand-text">
                    <LuCircleCheck className="h-4 w-4 text-brand-accent" />{" "}
                    Thanks for your feedback!
                </p>
                {rating <= 3 && (
                    <p className="mt-2 text-sm leading-relaxed text-brand-muted">
                        We’re sorry your experience wasn’t what it should have
                        been. Your feedback will be shared with the shop team so
                        they can work to make things right.
                    </p>
                )}
                {googleReviewUrl && rating > 3 && (
                    <>
                        <button
                            type="button"
                            onClick={postOnGoogle}
                            className="mt-3 inline-flex items-center gap-2 rounded-brand border border-brand-border bg-white px-4 py-2 text-sm font-semibold text-neutral-800"
                        >
                            <FcGoogle
                                className="h-[18px] w-[18px]"
                                aria-hidden="true"
                            />{" "}
                            Share on Google too
                        </button>
                        {comment.trim() && (
                            <p className="mt-1.5 text-xs text-brand-muted">
                                Your comment is copied — just paste it.
                            </p>
                        )}
                    </>
                )}
                <button
                    type="button"
                    onClick={() => setSaved(false)}
                    className="mt-2 block w-full text-xs text-brand-muted underline-offset-2 hover:underline"
                >
                    Edit rating
                </button>
            </div>
        );
    }

    const displayRating = hoverRating || rating;

    return (
        <div className={`rounded-2xl p-4 ${surface}`}>
            <div
                className="flex justify-center gap-1"
                role="radiogroup"
                aria-label="Rating out of 5 stars"
            >
                {[1, 2, 3, 4, 5].map((value) => (
                    <motion.button
                        key={value}
                        type="button"
                        role="radio"
                        aria-checked={rating === value}
                        aria-label={`${value} star${value > 1 ? "s" : ""}`}
                        onClick={() => setRating(value)}
                        onMouseEnter={() => setHoverRating(value)}
                        onMouseLeave={() => setHoverRating(0)}
                        whileTap={{ scale: 0.85 }}
                        className="p-0.5"
                    >
                        <IoStar
                            className="h-8 w-8 transition-colors"
                            style={{
                                color:
                                    value <= displayRating
                                        ? "var(--color-brand-accent)"
                                        : "var(--color-brand-border)",
                            }}
                        />
                    </motion.button>
                ))}
            </div>

            <textarea
                ref={commentRef}
                value={comment}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Add a comment (optional)"
                rows={2}
                maxLength={1000}
                aria-label="Comment"
                className="mt-3 w-full resize-none overflow-y-auto rounded-brand border border-brand-border bg-brand-card px-3 py-2 text-sm text-brand-text outline-none focus:border-brand-accent"
                style={{ maxHeight: COMMENT_MAX_HEIGHT }}
            />

            {error && <p className="mt-1.5 text-xs text-red-600">{error}</p>}

            <button
                type="button"
                onClick={submit}
                disabled={submitting}
                className="mt-2 w-full rounded-brand bg-brand-accent py-2.5 text-sm font-semibold text-brand-accent-text disabled:opacity-50"
            >
                {submitting
                    ? "Sending…"
                    : existingReview
                      ? "Update feedback"
                      : "Send feedback"}
            </button>
        </div>
    );
}
