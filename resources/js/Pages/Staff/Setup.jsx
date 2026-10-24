import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { LuCircleCheck, LuStore } from 'react-icons/lu';
import { STAFF_TOKEN_KEY } from '@/lib/storage';

export default function Setup({ valid, blocked = false, token, shopName, deviceName }) {
    const [redirecting, setRedirecting] = useState(false);

    useEffect(() => {
        if (!valid || !token) return;

        // The token only ever exists in the URL and here - never stored
        // server-side beyond its hash, never put in a cookie.
        window.localStorage.setItem(STAFF_TOKEN_KEY, token);
        setRedirecting(true);

        const timeout = setTimeout(() => router.visit('/staff'), 1200);
        return () => clearTimeout(timeout);
    }, [valid, token]);

    return (
        <>
            <Head title="Location setup" />
            <div className="flex min-h-screen items-center justify-center bg-brand-bg px-5">
                <div className="w-full max-w-sm rounded-brand border border-brand-border bg-brand-card p-6 text-center shadow-sm">
                    {valid ? (
                        <>
                            <LuCircleCheck className="mx-auto h-10 w-10 text-brand-accent" />
                            <h1 className="mt-3 font-heading text-lg font-bold text-brand-text">Location connected</h1>
                            <p className="mt-1 text-sm text-brand-muted">
                                {deviceName} is now set up for <span className="text-brand-text">{shopName}</span>.
                            </p>
                            <p className="mt-3 text-xs text-brand-muted">{redirecting ? 'Opening the scanner…' : 'Setting up…'}</p>
                        </>
                    ) : blocked ? (
                        <>
                            <LuStore className="mx-auto h-10 w-10 text-brand-muted" />
                            <h1 className="mt-3 font-heading text-lg font-bold text-brand-text">{shopName} is paused</h1>
                            <p className="mt-1 text-sm text-brand-muted">This shop's account is paused, so locations can't be set up right now. Please ask the shop owner.</p>
                        </>
                    ) : (
                        <>
                            <LuStore className="mx-auto h-10 w-10 text-brand-muted" />
                            <h1 className="mt-3 font-heading text-lg font-bold text-brand-text">This link isn't valid</h1>
                            <p className="mt-1 text-sm text-brand-muted">
                                It may have already been used, or the location was removed. Ask the shop owner for a new setup link.
                            </p>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}
