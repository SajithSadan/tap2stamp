import { Link } from '@inertiajs/react';
import { LuChevronLeft, LuChevronRight } from 'react-icons/lu';

// Small building blocks shared by the owner dashboard pages.

export const inputClass =
    'w-full min-w-0 rounded-xl border border-brand-border bg-brand-card px-3.5 py-2.5 text-sm text-brand-text outline-none transition focus:border-brand-accent focus:ring-4 focus:ring-brand-accent/10';

export const primaryButton =
    'inline-flex items-center justify-center gap-2 rounded-xl bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text shadow-sm transition-opacity hover:opacity-90 disabled:opacity-50';

export const secondaryButton =
    'inline-flex items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-card px-3.5 py-2 text-sm font-medium text-brand-text transition-colors hover:bg-brand-bg disabled:opacity-50';

export function Panel({ title, description, action, children, className = '', bodyClassName = 'p-5' }) {
    return (
        <section className={`min-w-0 rounded-2xl border border-brand-border bg-brand-card shadow-sm ${className}`}>
            {(title || action) && (
                <div className="flex flex-wrap items-start justify-between gap-3 border-b border-brand-border px-5 py-4">
                    <div>
                        {title && <h2 className="font-heading text-lg font-semibold text-brand-text">{title}</h2>}
                        {description && <p className="mt-0.5 text-sm text-brand-muted">{description}</p>}
                    </div>
                    {action}
                </div>
            )}
            <div className={bodyClassName}>{children}</div>
        </section>
    );
}

export function StatTile({ icon: Icon, label, value, hint }) {
    return (
        <div className="rounded-2xl border border-brand-border bg-brand-card p-4 shadow-sm sm:p-5">
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">{label}</p>
                {Icon && (
                    <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent">
                        <Icon className="h-4 w-4" />
                    </span>
                )}
            </div>
            <p className="mt-2 text-2xl font-bold tabular-nums text-brand-text sm:text-3xl">{value}</p>
            {hint && <p className="mt-1 text-xs text-brand-muted">{hint}</p>}
        </div>
    );
}

export function EmptyState({ icon: Icon, title, children }) {
    return (
        <div className="px-4 py-10 text-center">
            {Icon && <Icon className="mx-auto h-8 w-8 text-brand-muted/60" />}
            <p className="mt-3 text-sm font-semibold text-brand-text">{title}</p>
            {children && <p className="mx-auto mt-1 max-w-sm text-sm text-brand-muted">{children}</p>}
        </div>
    );
}

function PageLink({ url, only, children, label }) {
    const base = 'inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium';

    if (!url) {
        return <span className={`${base} text-brand-muted/40`}>{children}</span>;
    }

    return (
        <Link href={url} only={only} preserveScroll preserveState aria-label={label} className={`${base} text-brand-text hover:bg-brand-bg`}>
            {children}
        </Link>
    );
}

/** Prev/next controls for a Laravel paginator prop. */
export function Pagination({ paginator, only }) {
    if (paginator.last_page <= 1) return null;

    return (
        <div className="flex items-center justify-between border-t border-brand-border px-4 py-3">
            <PageLink url={paginator.prev_page_url} only={only} label="Previous page">
                <LuChevronLeft className="h-4 w-4" /> Prev
            </PageLink>
            <span className="text-xs text-brand-muted">
                Page {paginator.current_page} of {paginator.last_page}
            </span>
            <PageLink url={paginator.next_page_url} only={only} label="Next page">
                Next <LuChevronRight className="h-4 w-4" />
            </PageLink>
        </div>
    );
}

export function Avatar({ name }) {
    return (
        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-accent/10 text-sm font-semibold text-brand-accent">
            {name?.trim().charAt(0).toUpperCase() || '?'}
        </span>
    );
}

export function FieldError({ message }) {
    return message ? <p className="mt-1 text-xs text-red-600">{message}</p> : null;
}
