import { Head, Link } from '@inertiajs/react';
import { LuStore, LuWalletCards } from 'react-icons/lu';
import BottomNav from '@/Components/BottomNav';

/**
 * A blocked shop's card page / menu (EnsureShopActive). Only this shop's
 * card is off - the customer's cards at other shops still work, so point
 * them there rather than to a dead end.
 */
export default function ShopInactive({ shopName }) {
    return (
        <>
            <Head title={shopName} />
            <main className="flex min-h-dvh flex-col items-center justify-center bg-brand-bg px-6 pb-24 text-center text-brand-text">
                <span className="flex h-20 w-20 items-center justify-center rounded-3xl bg-brand-border/60 text-brand-muted">
                    <LuStore className="h-10 w-10" />
                </span>

                <h1 className="mt-6 font-heading text-2xl font-bold">{shopName}</h1>
                <p className="mt-2 max-w-sm text-sm text-brand-muted">
                    This loyalty card isn't active at the moment, so stamps can't be collected here. Your cards at other shops still work.
                </p>

                <Link href="/my-cards" className="mt-8 inline-flex items-center gap-2 rounded-brand bg-brand-accent px-5 py-2.5 text-sm font-semibold text-brand-accent-text">
                    <LuWalletCards className="h-4 w-4" /> My Cards
                </Link>
            </main>
            <BottomNav />
        </>
    );
}
