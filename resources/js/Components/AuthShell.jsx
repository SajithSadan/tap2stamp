import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { FcGoogle } from 'react-icons/fc';
import { LuEye, LuEyeOff, LuGift, LuSmartphone, LuStar } from 'react-icons/lu';
import { FieldError } from '@/Components/Dashboard/Ui';

// Log in / sign up / shop setup share this shell so they match the
// tap2stamp landing page: navy brand panel beside the form on desktop, the
// form alone on phones.

export const authInputClass =
    'w-full min-w-0 rounded-xl border border-brand-border bg-brand-card px-4 py-3 text-sm text-brand-text outline-none transition placeholder:text-brand-muted/70 focus:border-brand-accent focus:ring-4 focus:ring-brand-accent/15';

export const authButtonClass =
    'inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-accent px-5 py-3 text-sm font-semibold text-brand-accent-text transition hover:brightness-95 disabled:opacity-50';

export function Wordmark({ className = '' }) {
    return (
        <span className={`font-heading font-bold tracking-tight ${className}`}>
            tap<span className="text-brand-accent">2</span>stamp
        </span>
    );
}

// While a field has an error, its control gets a red outline (plain inputs,
// or the bordered box around composite ones like SlugInput).
const invalidClass =
    '[&_:is(input,textarea)]:border-red-400 [&_:is(input,textarea):focus]:ring-red-500/15 [&>div]:border-red-400 [&>div:focus-within]:ring-red-500/15';

export function AuthField({ id, label, error, hint, children }) {
    return (
        <div className={error ? invalidClass : undefined}>
            <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-brand-text">
                {label}
            </label>
            {children}
            {hint && !error && <p className="mt-1 text-xs text-brand-muted">{hint}</p>}
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}

export function PasswordInput({ id, value, onChange, autoComplete, placeholder }) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <input
                id={id}
                type={visible ? 'text' : 'password'}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                autoComplete={autoComplete}
                placeholder={placeholder}
                className={`${authInputClass} pr-11`}
            />
            <button
                type="button"
                onClick={() => setVisible((v) => !v)}
                aria-label={visible ? 'Hide password' : 'Show password'}
                className="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-brand-muted hover:text-brand-text"
            >
                {visible ? <LuEyeOff className="h-4 w-4" /> : <LuEye className="h-4 w-4" />}
            </button>
        </div>
    );
}

/** A plain link, not an Inertia visit: it leaves the app for Google's consent screen. */
export function GoogleButton({ label }) {
    return (
        <a
            href="/auth/google/redirect"
            className="flex w-full items-center justify-center gap-3 rounded-full border border-brand-border bg-white px-5 py-3 text-sm font-semibold text-neutral-800 shadow-sm transition hover:bg-neutral-50"
        >
            <FcGoogle className="h-5 w-5" />
            {label}
        </a>
    );
}

export function OrDivider() {
    return (
        <div className="flex items-center gap-3 text-xs font-medium uppercase tracking-wide text-brand-muted">
            <span className="h-px flex-1 bg-brand-border" />
            or
            <span className="h-px flex-1 bg-brand-border" />
        </div>
    );
}

const POINTS = [
    { icon: LuSmartphone, title: 'No app, no plastic', text: 'Customers collect stamps on their own phone.' },
    { icon: LuGift, title: 'Rewards that bring them back', text: 'Set your own stamps and reward in a minute.' },
    { icon: LuStar, title: 'Reviews and insights', text: 'See who visits, how often, and what they think.' },
];

export default function AuthShell({ title, heading, subheading, children, footer }) {
    return (
        <>
            <Head title={title} />

            <div className="flex min-h-dvh bg-brand-bg">
                {/* Brand panel (desktop) - navy, like the landing page's feature band. */}
                <aside className="relative hidden w-[44%] max-w-xl flex-col justify-between overflow-hidden bg-brand-deep p-12 text-white lg:flex">
                    <div className="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-accent/20 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-brand-accent/10 blur-3xl" />

                    <Wordmark className="relative text-2xl" />

                    <div className="relative">
                        <p className="text-sm font-semibold text-brand-accent">Digital loyalty card</p>
                        <h2 className="mt-3 font-heading text-4xl font-bold leading-tight">Turn every visit into a reason to come back.</h2>

                        <ul className="mt-10 space-y-6">
                            {POINTS.map(({ icon: Icon, title: pointTitle, text }) => (
                                <li key={pointTitle} className="flex gap-4">
                                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-brand-accent">
                                        <Icon className="h-5 w-5" />
                                    </span>
                                    <div>
                                        <p className="font-semibold">{pointTitle}</p>
                                        <p className="mt-0.5 text-sm text-white/70">{text}</p>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative text-xs text-white/50">Made for UK high-street independents.</p>
                </aside>

                {/* Form */}
                <main className="flex flex-1 items-center justify-center px-5 py-10 sm:px-8">
                    <div className="w-full max-w-md">
                        <Wordmark className="text-2xl text-brand-text lg:hidden" />

                        <h1 className="mt-8 font-heading text-3xl font-bold text-brand-text lg:mt-0">{heading}</h1>
                        {subheading && <p className="mt-2 text-sm text-brand-muted">{subheading}</p>}

                        <div className="mt-8">{children}</div>

                        {footer && <div className="mt-8 text-center text-sm text-brand-muted">{footer}</div>}
                    </div>
                </main>
            </div>
        </>
    );
}
