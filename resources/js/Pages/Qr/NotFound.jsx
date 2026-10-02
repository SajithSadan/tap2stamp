import { Head, Link, usePage } from '@inertiajs/react';
import { LuScanLine } from 'react-icons/lu';

/**
 * Shown when a scanned sticker has no destination yet (code is set) or the
 * code doesn't exist at all (code is null).
 */
export default function NotFound({ code }) {
    // An admin lands here after scanning an unknown or deleted sticker: send them back to the QR codes, not the home page.
    const isAdmin = usePage().props.auth?.user?.role === 'admin';

    return (
        <>
            <Head title="Nothing found" />
            <main className="flex min-h-dvh flex-col items-center justify-center bg-brand-bg px-6 text-center text-brand-text">
                <span className="flex h-20 w-20 items-center justify-center rounded-3xl bg-brand-accent/10 text-brand-accent">
                    <LuScanLine className="h-10 w-10" />
                </span>

                <h1 className="mt-6 font-heading text-2xl font-bold">Nothing found here</h1>

                <p className="mt-2 max-w-sm text-sm text-brand-muted">
                    {code
                        ? "This QR code isn't linked to anything yet. Please check back soon."
                        : "We couldn't find this QR code. It may have been mistyped or is no longer in use."}
                </p>

                {code && (
                    <p className="mt-5 rounded-xl border border-brand-border bg-brand-card px-4 py-2 font-mono text-lg font-bold tracking-widest">{code}</p>
                )}

                {isAdmin ? (
                    <Link href="/admin/qr-codes" className="mt-8 rounded-brand bg-brand-accent px-5 py-2.5 text-sm font-semibold text-brand-accent-text">
                        Back to QR codes
                    </Link>
                ) : (
                    <a href="/" className="mt-8 rounded-brand bg-brand-accent px-5 py-2.5 text-sm font-semibold text-brand-accent-text">
                        Go to home
                    </a>
                )}
            </main>
        </>
    );
}
