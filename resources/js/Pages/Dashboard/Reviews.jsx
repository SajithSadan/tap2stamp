import { IoStar } from 'react-icons/io5';
import { LuMessageSquare } from 'react-icons/lu';
import OwnerLayout from '@/Components/Dashboard/OwnerLayout';
import { Avatar, EmptyState, Pagination, Panel } from '@/Components/Dashboard/Ui';

function Stars({ rating, className = 'h-4 w-4' }) {
    return (
        <span className="flex items-center gap-0.5" aria-label={`${rating} out of 5 stars`}>
            {[1, 2, 3, 4, 5].map((n) => (
                <IoStar key={n} className={`${className} ${n <= Math.round(rating) ? 'text-amber-400' : 'text-brand-border'}`} aria-hidden="true" />
            ))}
        </span>
    );
}

export default function Reviews({ shop, summary, reviews }) {
    const maxCount = Math.max(1, ...summary.distribution.map((d) => d.count));

    return (
        <OwnerLayout shop={shop} title="Reviews" description="Ratings and comments customers left from their card.">
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <Panel title="Summary">
                    {summary.count === 0 ? (
                        <p className="text-sm text-brand-muted">No ratings yet.</p>
                    ) : (
                        <>
                            <div className="flex items-end gap-3">
                                <p className="text-4xl font-bold tabular-nums text-brand-text">{summary.average.toFixed(1)}</p>
                                <div className="pb-1">
                                    <Stars rating={summary.average} />
                                    <p className="mt-1 text-xs text-brand-muted">
                                        {summary.count} {summary.count === 1 ? 'rating' : 'ratings'}
                                    </p>
                                </div>
                            </div>

                            <ul className="mt-5 space-y-2">
                                {summary.distribution.map((row) => (
                                    <li key={row.stars} className="flex items-center gap-3 text-xs">
                                        <span className="w-8 shrink-0 tabular-nums text-brand-muted">{row.stars} ★</span>
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-brand-border/60">
                                            <div
                                                className="h-full rounded-full bg-brand-accent"
                                                style={{ width: `${(row.count / maxCount) * 100}%` }}
                                            />
                                        </div>
                                        <span className="w-6 shrink-0 text-right tabular-nums text-brand-text">{row.count}</span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </Panel>

                <Panel className="lg:col-span-2" title="Latest reviews" bodyClassName="">
                    {reviews.data.length === 0 ? (
                        <EmptyState icon={LuMessageSquare} title="No reviews yet">
                            Customers can rate their visit from their loyalty card.
                        </EmptyState>
                    ) : (
                        <>
                            <ul className="divide-y divide-brand-border">
                                {reviews.data.map((review) => (
                                    <li key={review.id} className="flex gap-3 px-5 py-4">
                                        <Avatar name={review.customer_name} />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                                <p className="text-sm font-medium text-brand-text">{review.customer_name}</p>
                                                <p className="text-xs text-brand-muted">{review.date}</p>
                                            </div>
                                            <div className="mt-1">
                                                <Stars rating={review.rating} className="h-3.5 w-3.5" />
                                            </div>
                                            {review.comment ? (
                                                <p className="mt-2 whitespace-pre-line text-sm text-brand-text">{review.comment}</p>
                                            ) : (
                                                <p className="mt-2 text-xs italic text-brand-muted">No comment</p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <Pagination paginator={reviews} only={['reviews']} />
                        </>
                    )}
                </Panel>
            </div>
        </OwnerLayout>
    );
}
