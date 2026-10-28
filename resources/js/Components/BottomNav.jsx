import { Link, usePage } from '@inertiajs/react';
import { lazy, Suspense, useEffect, useState } from 'react';
import { LuScanLine, LuStamp, LuWalletCards } from 'react-icons/lu';
import { LAST_SHOP_SLUG_KEY } from '@/lib/storage';

function NavItem({ href, icon, label, active, disabled }) {
    if (disabled) {
        return (
            <span className="flex flex-1 flex-col items-center gap-1 py-2 text-brand-muted/40">
                {icon}
                <span className="text-[11px] font-medium">{label}</span>
            </span>
        );
    }

    return (
        <Link
            href={href}
            className={`flex flex-1 flex-col items-center gap-1 py-2 transition-colors ${active ? 'text-brand-accent' : 'text-brand-muted'}`}
        >
            {icon}
            <span className="text-[11px] font-medium">{label}</span>
        </Link>
    );
}

// The camera library is only downloaded when a customer actually taps Scan.
const ShopQrScanner = lazy(() => import('@/Components/ShopQrScanner'));

/** Fixed mobile footer nav for the customer-facing pages: "Stamp card" (back
 *  to the last shop visited) and "Wallet" (the cross-shop My Cards list). With
 *  `scan` (a shop with Quick scan), a raised Scan button between them opens the
 *  camera to scan the shop's QR for a stamp. */
export default function BottomNav({ scan = false }) {
    const { url } = usePage();
    const [lastShopSlug, setLastShopSlug] = useState(null);
    const [scanning, setScanning] = useState(false);

    useEffect(() => {
        try {
            setLastShopSlug(window.localStorage.getItem(LAST_SHOP_SLUG_KEY));
        } catch {
            setLastShopSlug(null);
        }
    }, [url]);

    const shopHref = lastShopSlug ? `/s/${lastShopSlug}` : null;

    return (
        <>
            <nav
                className="fixed inset-x-0 bottom-0 z-10 flex border-t border-brand-border bg-brand-card"
                style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
            >
                <NavItem
                    href={shopHref}
                    disabled={!shopHref}
                    active={Boolean(shopHref) && url.startsWith(`/s/${lastShopSlug}`)}
                    icon={<LuStamp className="h-5 w-5" />}
                    label="Stamp card"
                />
                {scan && (
                    <div className="flex flex-1 justify-center">
                        <button
                            type="button"
                            onClick={() => setScanning(true)}
                            className="-mt-5 flex flex-col items-center gap-1"
                            aria-label="Scan the shop's QR code to collect a stamp"
                        >
                            <span className="flex h-14 w-14 items-center justify-center rounded-full bg-brand-accent text-brand-accent-text ring-4 ring-brand-card">
                                <LuScanLine className="h-6 w-6" />
                            </span>
                            <span className="text-[11px] font-medium text-brand-text">Scan</span>
                        </button>
                    </div>
                )}
                <NavItem href="/my-cards" active={url.startsWith('/my-cards')} icon={<LuWalletCards className="h-5 w-5" />} label="Wallet" />
            </nav>

            {scanning && (
                <Suspense fallback={null}>
                    <ShopQrScanner onClose={() => setScanning(false)} />
                </Suspense>
            )}
        </>
    );
}
