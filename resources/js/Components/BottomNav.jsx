import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CardsIcon, StoreIcon } from '@/Components/Icons';
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

/** Fixed mobile footer nav for the customer-facing pages: back to the last
 *  shop visited, and the cross-shop "My Cards" list. */
export default function BottomNav() {
    const { url } = usePage();
    const [lastShopSlug, setLastShopSlug] = useState(null);

    useEffect(() => {
        try {
            setLastShopSlug(window.localStorage.getItem(LAST_SHOP_SLUG_KEY));
        } catch {
            setLastShopSlug(null);
        }
    }, [url]);

    const shopHref = lastShopSlug ? `/s/${lastShopSlug}` : null;

    return (
        <nav
            className="fixed inset-x-0 bottom-0 z-10 flex border-t border-brand-border bg-brand-card"
            style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
        >
            <NavItem
                href={shopHref}
                disabled={!shopHref}
                active={Boolean(shopHref) && url.startsWith(`/s/${lastShopSlug}`)}
                icon={<StoreIcon className="h-5 w-5" />}
                label="My Card"
            />
            <NavItem href="/my-cards" active={url.startsWith('/my-cards')} icon={<CardsIcon className="h-5 w-5" />} label="My Cards" />
        </nav>
    );
}
