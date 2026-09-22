import { Head, usePage } from '@inertiajs/react';

export default function Landing() {
    const { appName } = usePage().props;

    return (
        <>
            <Head title="Digital Loyalty & Social Hub" />
            <main className="flex min-h-screen flex-col items-center justify-center bg-brand-bg px-6 text-center text-brand-text">
                <h1 className="font-heading text-3xl font-semibold tracking-tight">{appName}</h1>
                <p className="mt-2 max-w-sm text-sm text-brand-muted">
                    Digital loyalty cards for high-street independents — no app, no passwords,
                    just a scan.
                </p>
            </main>
        </>
    );
}
