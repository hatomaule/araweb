self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (error) {
        payload = {title: 'KosKosan', body: event.data ? event.data.text() : 'Ada notifikasi baru.'};
    }
    const title = String(payload.title || 'KosKosan');
    const body = String(payload.body || 'Ada notifikasi baru.');
    const url = new URL(String(payload.url || 'index.php?page=notifications'), self.registration.scope);
    if (url.origin !== self.location.origin) url.href = new URL('index.php?page=notifications', self.registration.scope).href;
    event.waitUntil(self.clients.matchAll({type: 'window', includeUncontrolled: true}).then((clients) => {
        const visibleClient = clients.find((client) => client.visibilityState === 'visible');
        if (visibleClient) {
            visibleClient.postMessage({
                type: 'push-notification',
                notification: {id: payload.id, title, body, url: url.href},
            });
            return;
        }
        return self.registration.showNotification(title, {
            body,
            icon: 'public-icon.svg',
            badge: 'public-icon.svg',
            data: {url: url.href},
            tag: `koskosan-${Number(payload.id) || Date.now()}`,
        });
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(event.notification.data?.url || 'index.php?page=notifications', self.registration.scope);
    if (target.origin !== self.location.origin) return;
    event.waitUntil(self.clients.matchAll({type: 'window', includeUncontrolled: true}).then((clients) => {
        const existing = clients.find((client) => new URL(client.url).origin === self.location.origin);
        if (existing) {
            existing.navigate(target.href);
            return existing.focus();
        }
        return self.clients.openWindow(target.href);
    }));
});
