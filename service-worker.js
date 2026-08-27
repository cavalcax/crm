const CACHE_NAME = 'crm-vitor-muller-v2';
const urlsToCache = [
    'admin/index.php',
    'admin/clients.php',
    'admin/schedule.php',
    'assets/images/logo.png'
];

self.addEventListener('install', event => {
    // Skip waiting to activate immediately
    self.skipWaiting();
});

self.addEventListener('fetch', event => {
    // Network first approach for SaaS (fresh data is critical)
    event.respondWith(
        fetch(event.request)
            .catch(() => {
                return caches.match(event.request);
            })
    );
});
