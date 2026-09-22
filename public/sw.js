// Minimal PWA shell cache for /staff - deliberately does NOT cache the HTML
// page itself or anything under /api/, so the scanner always sees fresh
// auth state and stamp data. Only speeds up reloads of static build assets.
const CACHE_NAME = 'staff-scanner-shell-v1';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET') return;
    if (url.pathname.startsWith('/api/')) return;

    const isStaticAsset = url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/');
    if (!isStaticAsset) return;

    event.respondWith(
        caches.open(CACHE_NAME).then((cache) =>
            cache.match(event.request).then(
                (cached) =>
                    cached ||
                    fetch(event.request).then((response) => {
                        cache.put(event.request, response.clone());
                        return response;
                    }),
            ),
        ),
    );
});
