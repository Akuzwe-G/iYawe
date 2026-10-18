const CACHE_NAME = 'iyawe-static-__VERSION__';
const OFFLINE = '/offline.html?v=__VERSION__';
const STATIC_ASSETS = __ASSETS__;
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
