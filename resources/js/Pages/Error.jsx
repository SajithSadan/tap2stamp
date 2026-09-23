import { Head } from '@inertiajs/react';

const messages = {
    403: ['Not allowed', "You don't have access to this page."],
    404: ['Page not found', "We couldn't find that page. Check the link or QR code and try again."],
    419: ['Page expired', 'This page sat open for a while. Go back, refresh, and try again.'],
    429: ['Too many tries', 'Please wait a minute, then try again.'],
    500: ['Something went wrong', "That's on us, not you. Please try again in a moment."],
    503: ['Back soon', "We're doing some quick maintenance. Please try again shortly."],
};

export default function Error({ status }) {
    const [title, description] = messages[status] ?? messages[500];

    return (
        <>
            <Head title={title} />
            <main className="flex min-h-screen flex-col items-center justify-center bg-brand-bg px-6 text-center text-brand-text">
                <p className="font-heading text-5xl font-bold text-brand-accent">{status}</p>
                <h1 className="mt-3 font-heading text-xl font-bold">{title}</h1>
                <p className="mt-2 max-w-sm text-sm text-brand-muted">{description}</p>
                <a
                    href="/"
                    className="mt-6 rounded-brand bg-brand-accent px-4 py-2.5 text-sm font-semibold text-brand-accent-text"
                >
                    Go to home
                </a>
            </main>
        </>
    );
}
