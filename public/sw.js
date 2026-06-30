/*
 | Forvalter service worker (PWA Phase 1).
 |
 | Goal: make the app installable + launch fast, WITHOUT interfering with
 | Livewire. Strategy:
 |   - Only ever handle same-origin GET requests.
 |   - Livewire (/livewire/*), uploads and any non-GET → not touched (network).
 |   - Built assets (/build/*) → cache-first (instant launch, revalidated).
 |   - Page navigations → network-first, falling back to cache, then an
 |     offline page. So you always get fresh data online, and a graceful
 |     screen when there's no signal.
 */
const CACHE = 'forvalter-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
            await self.clients.claim();
        })()
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    const url = new URL(req.url);

    // Leave everything that isn't a same-origin GET to the network: this covers
    // Livewire round-trips, form POSTs, the bilag drop, logout, downloads, etc.
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/livewire')) return;

    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(req));
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith(networkFirst(req));
    }
});

async function cacheFirst(req) {
    const cache = await caches.open(CACHE);
    const cached = await cache.match(req);
    if (cached) return cached;
    try {
        const res = await fetch(req);
        if (res.ok) cache.put(req, res.clone());
        return res;
    } catch (e) {
        return cached || Response.error();
    }
}

async function networkFirst(req) {
    const cache = await caches.open(CACHE);
    try {
        const res = await fetch(req);
        if (res.ok) cache.put(req, res.clone());
        return res;
    } catch (e) {
        const cached = await cache.match(req);
        return cached || (await cache.match(OFFLINE_URL));
    }
}
