import { useEffect, useState } from 'react';

/**
 * Sticks to the top of the screen while the browser reports no connection.
 * navigator.onLine can't detect every dead network, so pages still handle
 * failed requests themselves - this just explains the common case up front.
 */
export default function OfflineBanner() {
    const [offline, setOffline] = useState(() => typeof navigator !== 'undefined' && navigator.onLine === false);

    useEffect(() => {
        const goOffline = () => setOffline(true);
        const goOnline = () => setOffline(false);

        window.addEventListener('offline', goOffline);
        window.addEventListener('online', goOnline);
        return () => {
            window.removeEventListener('offline', goOffline);
            window.removeEventListener('online', goOnline);
        };
    }, []);

    if (!offline) return null;

    return (
        <div role="status" className="fixed inset-x-0 top-0 z-30 bg-neutral-900 px-4 py-2 text-center text-xs font-medium text-white">
            You're offline — check your connection. This page will work again once you're back online.
        </div>
    );
}
