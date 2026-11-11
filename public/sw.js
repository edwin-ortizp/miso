/**
 * Service worker de MiSo.
 *
 * Objetivo: que la aplicacion abra al instante desde la pantalla de inicio y
 * siga siendo navegable sin cobertura, sin llegar nunca a servir una version
 * caducada del bundle (que dejaria Livewire inservible tras un despliegue).
 */

const VERSION = 'life-tracker-v8';
const SHELL_CACHE = `${VERSION}-shell`;
const ASSET_CACHE = `${VERSION}-assets`;
const PAGE_CACHE = `${VERSION}-pages`;

// Bootstrap, sus iconos y la tipografia viajan dentro del bundle propio: no
// quedan dependencias de CDN que precachear.
const SHELL_ASSETS = [
    '/',
    '/offline',
    '/manifest.json',
];

const MAX_CACHED_PAGES = 30;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            // `addAll` falla entera si un solo recurso falla; se piden por
            // separado para que una ruta caida no impida la instalacion.
            .then((cache) => Promise.allSettled(SHELL_ASSETS.map((asset) => cache.add(asset))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

/** Recorta el cache de paginas para no crecer sin limite. */
async function trimPages() {
    const cache = await caches.open(PAGE_CACHE);
    const keys = await cache.keys();

    if (keys.length <= MAX_CACHED_PAGES) {
        return;
    }

    await Promise.all(keys.slice(0, keys.length - MAX_CACHED_PAGES).map((key) => cache.delete(key)));
}

/**
 * Navegacion: red primero para no mostrar datos viejos; si no hay conexion se
 * sirve la ultima version vista de esa misma pantalla, y si nunca se visito,
 * la pagina de sin conexion.
 */
async function handleNavigation(request) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(PAGE_CACHE);

            await cache.put(request, response.clone());
            await trimPages();
        }

        return response;
    } catch {
        return (await caches.match(request))
            ?? (await caches.match('/offline'))
            ?? (await caches.match('/'))
            ?? Response.error();
    }
}

/**
 * Assets con hash en el nombre: el contenido nunca cambia para una misma URL,
 * asi que se sirven desde cache y se refrescan por detras.
 */
async function handleHashedAsset(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached);

    return cached ?? network;
}

/**
 * `app.css` y `app.js` se sirven con `?v=<filemtime>`, asi que su URL cambia en
 * cada despliegue. Van a red primero: un bundle caducado deja la aplicacion sin
 * Livewire, que es peor que una espera.
 */
async function handleVersionedBundle(request) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(ASSET_CACHE);

            cache.put(request, response.clone());
        }

        return response;
    } catch {
        return (await caches.match(request)) ?? Response.error();
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    // Los logs requieren red y nunca deben persistir en el cache de la PWA.
    if (url.pathname === '/log-viewer' || url.pathname.startsWith('/log-viewer/')) {
        return;
    }

    if (url.origin !== self.location.origin) {
        return;
    }

    // Livewire habla por POST, pero sus rutas nunca deben cachearse.
    if (url.pathname.startsWith('/livewire')) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(request));

        return;
    }

    // Se comprueba antes que los assets con hash: `/js/app.js` vive bajo `/js/`
    // pero su nombre no lleva hash, se versiona por query.
    if (url.pathname === '/css/app.css' || url.pathname === '/js/app.js') {
        event.respondWith(handleVersionedBundle(request));

        return;
    }

    if (url.pathname.startsWith('/css/assets/') || url.pathname.startsWith('/js/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(handleHashedAsset(request));
    }
});

/**
 * CHG-068: aviso push del guardián (o de otra notificación). El servidor envía
 * JSON con title, body, url y tag; si llega sin datos se muestra un aviso genérico.
 */
self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data?.text() };
    }

    event.waitUntil(self.registration.showNotification(data.title || 'MiSo', {
        body: data.body || 'Tienes algo pendiente en MiSo.',
        icon: data.icon || '/icons/icon-192x192.png',
        badge: '/icons/icon-96x96.png',
        tag: data.tag || 'miso',
        // CHG-069: botones del aviso; los navegadores sin soporte los ignoran.
        actions: Array.isArray(data.actions) ? data.actions.slice(0, 2) : [],
        data: { url: data.url || '/?via=push', quickUrl: data.quick_url || null, checkinUrl: data.checkin_url || null },
    }));
});

/** Abre la pantalla del aviso; si MiSo ya está abierta, la reutiliza. */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data = event.notification.data || {};

    // CHG-069: "+1 vaso" registra en segundo plano con la URL firmada; si falla, abre Agua.
    if (event.action === 'quick-water' && data.quickUrl) {
        event.waitUntil(
            fetch(data.quickUrl, { method: 'POST', headers: { Accept: 'application/json' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('quick-water');
                    }
                })
                .catch(() => self.clients.openWindow('/water/daily?via=push'))
        );

        return;
    }

    const destination = event.action === 'checkin' && data.checkinUrl ? data.checkinUrl : (data.url || '/');
    const target = new URL(destination, self.location.origin);

    // Solo se navega dentro del propio origen.
    const url = target.origin === self.location.origin ? target.href : self.location.origin;

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            const client = clients.find((c) => c.url.startsWith(self.location.origin));

            if (client) {
                return client.focus().then((focused) => (focused.navigate ? focused.navigate(url) : focused));
            }

            return self.clients.openWindow(url);
        })
    );
});
