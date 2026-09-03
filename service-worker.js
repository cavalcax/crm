const CACHE_NAME = 'crm-vitor-muller-v3';
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

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', event => {
    // Only intercept GET requests
    if (event.request.method !== 'GET') {
        return;
    }

    // Network first approach for SaaS (fresh data is critical)
    event.respondWith(
        fetch(event.request)
            .catch(async () => {
                const cached = await caches.match(event.request);
                if (cached) {
                    return cached;
                }
                return new Response('Rede indisponível no momento.', {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: { 'Content-Type': 'text/plain; charset=utf-8' }
                });
            })
    );
});

// Push Event: Triggers native OS / browser notification
self.addEventListener('push', event => {
    const defaultIcon = new URL('assets/images/logo.png', self.location.origin).href;
    let data = {
        title: 'CRM Vitor Müller',
        body: 'Você tem uma nova notificação na agenda.',
        icon: defaultIcon,
        badge: defaultIcon,
        data: { url: 'admin/schedule.php' }
    };

    if (event.data) {
        try {
            const parsed = event.data.json();
            data = {
                title: parsed.title || data.title,
                body: parsed.body || data.body,
                icon: parsed.icon ? new URL(parsed.icon, self.location.origin).href : defaultIcon,
                badge: parsed.badge ? new URL(parsed.badge, self.location.origin).href : defaultIcon,
                data: parsed.data || data.data
            };
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: data.badge,
        vibrate: [200, 100, 200],
        tag: 'crm-notification-' + (data.data?.id || Date.now()),
        renotify: true,
        data: data.data
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

// Notification Click Event: Focus or open CRM page
self.addEventListener('notificationclick', event => {
    event.notification.close();

    const targetUrl = event.notification.data?.url || '/admin/schedule.php';
    const absoluteUrl = new URL(targetUrl, self.location.origin).href;

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            // Se já tiver uma aba aberta do CRM, foca nela e navega
            for (let client of windowClients) {
                if (client.url.includes('/admin/') && 'focus' in client) {
                    client.navigate(absoluteUrl);
                    return client.focus();
                }
            }
            // Caso contrário, abre uma nova janela
            if (clients.openWindow) {
                return clients.openWindow(absoluteUrl);
            }
        })
    );
});
