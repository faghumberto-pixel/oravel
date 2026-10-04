/**
 * Registra o Service Worker (escopo /admin/) e guarda a propria pagina no
 * cache, para que as telas offline-first (horimetro, ponto) abram sem rede.
 * Sem isso a primeira visita nunca entra no cache: ela carregou antes do SW
 * assumir o controle da pagina.
 */
export async function registerOfflineShell() {
    if (!('serviceWorker' in navigator)) return;

    try {
        await navigator.serviceWorker.register('/service-worker.js', { scope: '/admin/' });
        await navigator.serviceWorker.ready;

        if (navigator.onLine && 'caches' in window) {
            const cache = await caches.open('oravel-api-v1');
            await cache.add(new Request(location.href, { credentials: 'same-origin' }));
        }
    } catch (error) {
        console.warn('[Offline] Nao foi possivel preparar o modo offline:', error);
    }
}
