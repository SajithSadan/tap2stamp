import { LuCheck, LuExternalLink, LuTruck } from 'react-icons/lu';

/**
 * Order received → Processing → Dispatched → Delivered, each with the date
 * it was reached. Steps run left-to-right on wider screens, top-to-bottom on
 * phones. `order` is an Order::summary() from the server.
 */
export default function OrderTracker({ order }) {
    const current = order.steps.findLastIndex((s) => s.date);

    return (
        <div>
            <ol className="flex flex-col gap-3 sm:flex-row sm:gap-0">
                {order.steps.map((step, i) => {
                    const done = i <= current;

                    return (
                        <li key={step.key} className="relative flex items-center gap-3 sm:flex-1 sm:flex-col sm:items-start sm:gap-2">
                            {/* Connector to the next step (desktop only). */}
                            {i < order.steps.length - 1 && (
                                <span
                                    aria-hidden="true"
                                    className={`absolute left-7 right-0 top-3.5 hidden h-0.5 sm:block ${i < current ? 'bg-brand-accent' : 'bg-brand-border'}`}
                                />
                            )}
                            <span
                                className={`relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                                    done ? 'bg-brand-accent text-brand-accent-text' : 'bg-brand-bg text-brand-muted ring-1 ring-inset ring-brand-border'
                                } ${i === current ? 'ring-4 ring-brand-accent/20' : ''}`}
                            >
                                {done ? <LuCheck className="h-4 w-4" /> : i + 1}
                            </span>
                            <span className="min-w-0 sm:pr-3">
                                <span className={`block text-sm font-medium ${done ? 'text-brand-text' : 'text-brand-muted'}`}>{step.label}</span>
                                <span className="block text-xs text-brand-muted">{step.date ?? (i === current + 1 ? 'Next' : '')}</span>
                            </span>
                        </li>
                    );
                })}
            </ol>

            {(order.courier || order.tracking_number || order.tracking_url) && (
                <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl bg-brand-bg px-4 py-3 text-sm">
                    <LuTruck className="h-4 w-4 shrink-0 text-brand-muted" />
                    <span className="text-brand-text">
                        {order.courier ?? 'Courier'}
                        {order.tracking_number && <span className="text-brand-muted"> · {order.tracking_number}</span>}
                    </span>
                    {order.tracking_url && (
                        <a
                            href={order.tracking_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 font-semibold text-brand-accent"
                        >
                            Track parcel <LuExternalLink className="h-3.5 w-3.5" />
                        </a>
                    )}
                </div>
            )}
        </div>
    );
}
