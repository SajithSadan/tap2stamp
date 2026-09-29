import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { LuCheck, LuChevronLeft, LuChevronRight, LuCircleAlert, LuCircleCheck, LuCopy, LuX } from 'react-icons/lu';

// Small building blocks shared by the owner dashboard and admin pages.

/**
 * Sidebar surface: the theme's deep colour tinted with its accent, with a
 * faint accent glow at the top (bg-brand-nav in app.css). The mobile tab bar
 * uses the same base colour, bg-brand-deep-soft.
 */
export const navSurface = 'bg-brand-nav text-white shadow-[inset_-1px_0_0_rgb(255_255_255/0.06)]';

/** A sidebar link on navSurface: muted white, active = translucent mint pill. */
export function sideLinkClass(active) {
    return `flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
        active ? 'bg-brand-accent/15 text-white ring-1 ring-inset ring-brand-accent/25 [&>svg]:text-brand-accent' : 'text-white/65 hover:bg-white/[0.06] hover:text-white'
    }`;
}

/** A mobile bottom-tab link on navSurface. */
export function tabLinkClass(active) {
    return `flex flex-col items-center gap-1 py-2.5 text-[10px] font-medium transition-colors ${active ? 'text-brand-accent' : 'text-white/60 hover:text-white'}`;
}

/** How long a success message stays before fading out (hovering pauses it). */
const STATUS_TTL_MS = 5000;
const STATUS_FADE_MS = 300;

/** Flashed one-line success message (flash.status): fades out by itself after a few seconds, or on ✕. */
export function StatusBanner() {
    const { flash } = usePage().props;
    // 'shown' -> 'leaving' (fading) -> 'gone'.
    const [phase, setPhase] = useState('shown');
    const [paused, setPaused] = useState(false);

    // Every new server response brings a new flash object, so a repeated
    // message (e.g. saving twice) shows again and restarts the timer.
    useEffect(() => setPhase('shown'), [flash]);

    useEffect(() => {
        if (!flash?.status || paused || phase !== 'shown') return undefined;
        const timer = setTimeout(() => setPhase('leaving'), STATUS_TTL_MS);
        return () => clearTimeout(timer);
    }, [flash, paused, phase]);

    useEffect(() => {
        if (phase !== 'leaving') return undefined;
        const timer = setTimeout(() => setPhase('gone'), STATUS_FADE_MS);
        return () => clearTimeout(timer);
    }, [phase]);

    if (!flash?.status || phase === 'gone') return null;

    return (
        <div
            role="status"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            style={{ transitionDuration: `${STATUS_FADE_MS}ms` }}
            className={`mb-6 flex items-start gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-brand-text transition-opacity ${
                phase === 'leaving' ? 'opacity-0' : 'opacity-100'
            }`}
        >
            <LuCircleCheck className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
            <p className="min-w-0 flex-1 break-words">{flash.status}</p>
            <button type="button" onClick={() => setPhase('gone')} aria-label="Dismiss" className="-m-1 rounded-lg p-1 text-brand-muted hover:bg-brand-bg">
                <LuX className="h-4 w-4" />
            </button>
        </div>
    );
}

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

/** `compact`: a little smaller all round (less padding, smaller number and icon). */
export function StatTile({ icon: Icon, label, value, hint, compact = false }) {
    return (
        <div className={`rounded-2xl border border-brand-border bg-brand-card shadow-sm ${compact ? 'p-3.5 sm:px-4 sm:py-3.5' : 'p-4 sm:p-5'}`}>
            <div className="flex items-center justify-between gap-2">
                <p className="text-xs font-medium uppercase tracking-wide text-brand-muted">{label}</p>
                {Icon && (
                    <span className={`flex items-center justify-center rounded-lg bg-brand-accent/10 text-brand-accent ${compact ? 'h-7 w-7' : 'h-8 w-8'}`}>
                        <Icon className={compact ? 'h-3.5 w-3.5' : 'h-4 w-4'} />
                    </span>
                )}
            </div>
            <p className={`font-bold tabular-nums text-brand-text ${compact ? 'mt-1 text-xl sm:text-2xl' : 'mt-2 text-2xl sm:text-3xl'}`}>{value}</p>
            {hint && <p className={`text-xs text-brand-muted ${compact ? 'mt-0.5' : 'mt-1'}`}>{hint}</p>}
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

/** Icon button that copies `text` to the clipboard, with a brief tick. */
export function CopyButton({ text, label = 'Copy' }) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch {
            // Clipboard blocked (e.g. non-HTTPS) - the text is still selectable.
        }
    }

    return (
        <button
            type="button"
            onClick={copy}
            aria-label={label}
            title={label}
            className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-brand-muted transition-colors hover:bg-brand-bg hover:text-brand-text"
        >
            {copied ? <LuCheck className="h-4 w-4 text-emerald-600" /> : <LuCopy className="h-4 w-4" />}
        </button>
    );
}

/** On/off switch with a label; `label`/`description` are optional for a bare switch. */
export function Switch({ checked, onChange, disabled, label, description }) {
    const control = (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            aria-label={label ? undefined : 'Toggle'}
            disabled={disabled}
            onClick={() => onChange(!checked)}
            className={`relative mt-0.5 h-6 w-11 shrink-0 rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${checked ? 'bg-brand-accent' : 'bg-brand-border'}`}
        >
            <span className={`absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${checked ? 'translate-x-5' : ''}`} />
        </button>
    );

    if (!label) return control;

    return (
        <label className="flex cursor-pointer items-start justify-between gap-4">
            <span>
                <span className="block text-sm font-medium text-brand-text">{label}</span>
                {description && <span className="mt-0.5 block text-xs text-brand-muted">{description}</span>}
            </span>
            {control}
        </label>
    );
}

export function FieldError({ id, message }) {
    return message ? (
        <p id={id} role="alert" className="mt-1.5 flex animate-field-error items-start gap-1.5 text-xs font-medium text-red-600">
            <LuCircleAlert className="mt-px h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            {message}
        </p>
    ) : null;
}
