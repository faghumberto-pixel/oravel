/**
 * Service Worker para Oravel Técnico de Campo
 * Estratégia: cache-first para assets, network-first com fallback para dados
 */

const CACHE_NAME = 'oravel-tech-v1';
const API_CACHE = 'oravel-api-v1';
const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/offline.html',
];

// Install: pré-cache assets críticos
self.addEventListener('install', event => {
    console.log('[SW] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(STATIC_ASSETS).catch(err => {
                console.log('[SW] Cache add failed:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Activate: limpa caches velhos
self.addEventListener('activate', event => {
    console.log('[SW] Activating...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME && cacheName !== API_CACHE) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch: estratégia cache-first para assets, network-first para API
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // API calls: network-first com fallback local
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(networkFirstStrategy(request));
        return;
    }

    // Assets estáticos: cache-first
    if (request.method === 'GET' && (
        url.pathname.match(/\.(js|css|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot)$/i) ||
        url.pathname.startsWith('/images/') ||
        url.pathname.startsWith('/fonts/')
    )) {
        event.respondWith(cacheFirstStrategy(request));
        return;
    }

    // Tudo mais: network-first
    event.respondWith(networkFirstStrategy(request));
});

// Background Sync: sincroniza fila de pendências
self.addEventListener('sync', event => {
    if (event.tag === 'sync-wizard-data') {
        event.waitUntil(syncPendingData());
    }
});

/**
 * Estratégia cache-first
 */
function cacheFirstStrategy(request) {
    return caches.match(request).then(response => {
        if (response) return response;

        return fetch(request).then(response => {
            // Não cachear respostas não-OK
            if (!response || response.status !== 200) {
                return response;
            }

            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
                cache.put(request, responseToCache);
            });

            return response;
        }).catch(err => {
            console.log('[SW] Fetch failed:', err);
            return caches.match(request) || new Response('Offline', { status: 503 });
        });
    });
}

/**
 * Estratégia network-first
 */
function networkFirstStrategy(request) {
    return fetch(request).then(response => {
        // Cache de sucesso em API_CACHE
        if (response && response.status === 200) {
            const responseToCache = response.clone();
            caches.open(API_CACHE).then(cache => {
                cache.put(request, responseToCache);
            });
        }
        return response;
    }).catch(err => {
        console.log('[SW] Network fetch failed:', err);
        // Fallback: tenta cache local
        return caches.match(request).then(cachedResponse => {
            if (cachedResponse) {
                console.log('[SW] Returning cached response');
                return cachedResponse;
            }
            return new Response('Offline', { status: 503 });
        });
    });
}

/**
 * Sincroniza fila de pendências com o servidor
 * (será chamado quando conexão voltar, via Background Sync API)
 */
async function syncPendingData() {
    try {
        // Aqui será implementada a lógica de sincronização
        // Por enquanto, apenas log
        console.log('[SW] Sync triggered');
    } catch (err) {
        console.error('[SW] Sync failed:', err);
        throw err; // Retry pelo Sistema de Background Sync
    }
}
