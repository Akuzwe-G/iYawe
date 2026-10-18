const CACHE_NAME = 'iyawe-static-d8b3099a8b32bbb6';
const OFFLINE = '/offline.html?v=d8b3099a8b32bbb6';
const STATIC_ASSETS = ["/build/assets/app-Bu3mjW52.js","/build/assets/app-C6xoTL8Q.css","/build/assets/fonts-DMNua-9i.css","/build/assets/montserrat-400-normal-BLhwKU8k.woff2","/build/assets/montserrat-400-normal-xItZbAXg.woff","/build/assets/montserrat-500-normal-DRFEGfly.woff2","/build/assets/montserrat-500-normal-Dok2oTci.woff","/build/assets/montserrat-600-normal-CdhFl4lI.woff","/build/assets/montserrat-600-normal-UVxSCcoG.woff2","/build/assets/montserrat-700-normal-BWkrl476.woff","/build/assets/montserrat-700-normal-BdjcYUrC.woff2","/pwa/icons/icon-192.png?v=d8b3099a8b32bbb6","/pwa/icons/icon-512.png?v=d8b3099a8b32bbb6","/pwa/icons/icon-180.png?v=d8b3099a8b32bbb6","/pwa/icons/maskable-512.png?v=d8b3099a8b32bbb6","/offline.html?v=d8b3099a8b32bbb6"];
const ALLOWED = new Set(STATIC_ASSETS);
async function safeAsset(url) {
    const response = await fetch(new Request(url, {credentials:'omit', cache:'no-store'}));
    if (!response.ok || response.redirected || response.type !== 'basic') throw new Error('Static asset unavailable');
    const type = response.headers.get('Content-Type') || '';
    const pathname = new URL(url, self.location.origin).pathname;
    const expected = pathname.endsWith('.html') ? /text\/html/i : pathname.endsWith('.js') ? /javascript/i : pathname.endsWith('.css') ? /text\/css/i : /(?:image\/(?:png|svg)|font\/|application\/(?:font|octet-stream))/i;
    if (!expected.test(type)) throw new Error('Unexpected static asset type');
    return response;
}
self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE_NAME);
        await Promise.all(STATIC_ASSETS.map(async url => cache.put(url, await safeAsset(url))));
    })());
});
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) if (key.startsWith('iyawe-static-') && key !== CACHE_NAME) await caches.delete(key);
        await self.clients.claim();
    })());
});
self.addEventListener('message', event => { if (event.data?.type === 'ACTIVATE_UPDATE') self.skipWaiting(); });
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request, {cache:'no-store'}).catch(async () => (await caches.open(CACHE_NAME)).match(OFFLINE)));
        return;
    }
    if (!ALLOWED.has(url.pathname + url.search)) return;
    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME);
        const stored = await cache.match(url.pathname + url.search);
        if (stored) return stored;
        const response = await safeAsset(url.pathname + url.search);
        await cache.put(url.pathname + url.search, response.clone());
        return response;
    })());
});
