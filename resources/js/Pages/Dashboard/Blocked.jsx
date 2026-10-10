import { Head, Link } from '@inertiajs/react';
import { LuLogOut, LuPause } from 'react-icons/lu';
import { secondaryButton } from '@/Components/Dashboard/Ui';

/** The owner's dashboard while the admin has blocked their shop (EnsureOwnerHasShop). */
export default function Blocked({ shopName }) {
    return (
        <>
            <Head title="Shop paused" />
            <main className="flex min-h-dvh flex-col items-center justify-center bg-brand-bg px-6 text-center text-brand-text">
                <span className="flex h-20 w-20 items-center justify-center rounded-3xl bg-amber-500/10 text-amber-700">
                    <LuPause className="h-10 w-10" />
                </span>

                <h1 className="mt-6 font-heading text-2xl font-bold">{shopName} is paused</h1>
                <p className="mt-2 max-w-sm text-sm text-brand-muted">
                    Your loyalty card, menu and staff app are switched off for now, and customers can't collect stamps. Nothing has been
                    deleted. Please get in touch with TaDa Tap to switch it back on.
                </p>

                <Link href="/logout" method="post" as="button" className={`${secondaryButton} mt-8`}>
                    <LuLogOut className="h-4 w-4" /> Log out
                </Link>
            </main>
        </>
    );
}
