// Service worker: network-first untuk file statis, API & halaman PHP tidak di-cache.
const CACHE = 'budget-v1.0.0';
const ASSETS = [
  'css/app.css', 'js/app.js', 'js/core/state.js', 'js/core/data.js', 'js/core/helpers.js',
  'js/ui/toast.js', 'js/ui/sheet.js', 'js/ui/icons.js',
  'js/features/daily.js', 'js/features/monthly.js', 'js/features/upload.js', 'js/features/admin.js', 'js/features/account.js',
  'icons/icon.svg', 'icons/icon-192.png', 'manifest.webmanifest',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)).catch(() => {}));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))));
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin) return;
  if (url.pathname.includes('/api/') || url.pathname.endsWith('.php') || url.pathname.endsWith('/')) return;
  if (!/\.(css|js|svg|png|webmanifest)$/.test(url.pathname)) return;
  e.respondWith(
    fetch(e.request)
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy));
        return res;
      })
      .catch(() => caches.match(e.request, { ignoreSearch: true })),
  );
});
