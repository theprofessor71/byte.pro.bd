/* sw.js — CyberBlogs service worker.
   Static-asset cache only. NEVER caches: /admin/, /api/, /download.php,
   /search.php, /install.php or any POST — so auth and dynamic data stay live. */
'use strict';

const CACHE = 'cb-static-v2'; // bump on every CSS/JS change — assets are cache-first
const PAGES = 'cb-pages-v2';
const MAX_PAGES = 30; // LRU cap for offline-readable pages
const STATIC_ASSETS = [
    '/assets/css/style.css',
    '/assets/css/animations.css',
    '/assets/css/prism-theme.css',
    '/assets/js/app.js',
    '/assets/js/utils.js',
    '/assets/js/search.js',
    '/assets/js/effects.js',
    '/assets/js/comments.js',
    '/assets/icons/favicon.svg'
];
const NEVER_CACHE = /^\/(admin|api|config|includes|protected_uploads)(\/|$)|^\/(download|search|install|subscribe)\.php/;

self.addEventListener('install', (ev) => {
    ev.waitUntil(
        caches.open(CACHE)
            .then((c) => c.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (ev) => {
    ev.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== CACHE && k !== PAGES).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

/** Trim a cache to `max` entries, oldest-inserted first. */
function trimCache(name, max) {
    return caches.open(name).then((c) =>
        c.keys().then((keys) => {
            if (keys.length <= max) return;
            return Promise.all(keys.slice(0, keys.length - max).map((k) => c.delete(k)));
        })
    );
}

self.addEventListener('fetch', (ev) => {
    const req = ev.request;
    const url = new URL(req.url);

    // Only same-origin GETs; never the sensitive paths.
    // Normalize // → / first: Apache collapses duplicate slashes, so
    // //admin/... would serve admin HTML while dodging the regex.
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;
    const path = url.pathname.replace(/\/{2,}/g, '/');
    if (NEVER_CACHE.test(path)) return;

    // Static assets: cache-first
    if (path.startsWith('/assets/')) {
        ev.respondWith(
            caches.match(req).then((hit) => hit || fetch(req).then((res) => {
                if (res.ok) {
                    const clone = res.clone();
                    caches.open(CACHE).then((c) => c.put(req, clone));
                }
                return res;
            }))
        );
        return;
    }

    // Pages: network-first with cache fallback (offline reading), LRU-capped
    ev.respondWith(
        fetch(req).then((res) => {
            if (res.ok && res.type === 'basic') {
                const clone = res.clone();
                caches.open(PAGES)
                    .then((c) => c.put(req, clone))
                    .then(() => trimCache(PAGES, MAX_PAGES));
            }
            return res;
        }).catch(() => caches.match(req))
    );
});
