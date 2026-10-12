<?php

namespace App\Support\Colaborador;

/**
 * Service worker do app do colaborador: push + app aberto SEM internet.
 *
 * Páginas (HTML): rede primeiro (6 s) e guarda a última cópia; sem rede, abre a cópia guardada ou a tela "sem conexão".
 * Arquivos estáticos (css/js/imagens): cache primeiro. Nunca mexe em POST, /livewire, /api e /push.
 * Mude VERSAO para forçar todo mundo a baixar tudo de novo.
 */
class ServiceWorker
{
    public const VERSAO = 'v1';

    public static function js(): string
    {
        $versao = self::VERSAO;

        return <<<JS
const VERSAO = '{$versao}';
const PAGINAS = 'oravel-colab-paginas-' + VERSAO;
const ESTATICOS = 'oravel-colab-estaticos-' + VERSAO;
const BASE = new URL('./', self.registration.scope).pathname;           // '/' em produção, '/colaborador/' no DEV
const OFFLINE = BASE + 'offline';
const NUNCA = [/\\/livewire\\//, /\\/api\\//, /\\/push\\//, /\\/sw\\.js$/];

self.addEventListener('install', (event) => {
    event.waitUntil(Promise.allSettled([
        // A tela "sem conexão" fica entre os estáticos: sobrevive à limpeza de páginas feita ao sair da conta.
        caches.open(ESTATICOS).then((c) => Promise.allSettled([OFFLINE, '/vendor/jsqr.js', '/icon.png', '/icon-512.png'].map((u) => c.add(u)))),
    ]).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(caches.keys()
        .then((nomes) => Promise.all(nomes.filter((n) => n.startsWith('oravel-colab-') && n !== PAGINAS && n !== ESTATICOS).map((n) => caches.delete(n))))
        .then(() => self.clients.claim()));
});

function comTimeout(promessa, ms) {
    return new Promise((ok, erro) => { const t = setTimeout(() => erro(new Error('timeout')), ms); promessa.then((r) => { clearTimeout(t); ok(r); }, (e) => { clearTimeout(t); erro(e); }); });
}

async function pagina(req) {
    const cache = await caches.open(PAGINAS);
    try {
        const resp = await comTimeout(fetch(req), 6000);
        if (resp.ok && resp.type === 'basic' && !resp.redirected) { cache.put(req.url, resp.clone()); }
        return resp;
    } catch (e) {
        const guardada = await cache.match(req.url);
        if (guardada) { return guardada; }
        if (req.mode === 'navigate') {
            const off = await caches.match(OFFLINE);
            if (off) { return off; }
        }
        return new Response('Sem conexão', { status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
    }
}

async function estatico(req) {
    const cache = await caches.open(ESTATICOS);
    const guardado = await cache.match(req.url);
    if (guardado) { return guardado; }
    const resp = await fetch(req);
    if (resp.ok && resp.type === 'basic') { cache.put(req.url, resp.clone()); }
    return resp;
}

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') { return; }
    const url = new URL(req.url);
    if (url.origin !== self.location.origin || NUNCA.some((r) => r.test(url.pathname))) { return; }

    const html = req.mode === 'navigate' || (req.headers.get('accept') || '').includes('text/html');
    if (html) { event.respondWith(pagina(req)); return; }
    if (/\\.(css|js|png|jpe?g|svg|webp|woff2?|ico|webmanifest|json)$/.test(url.pathname) || url.pathname.includes('/build/')) { event.respondWith(estatico(req)); }
});

self.addEventListener('message', (event) => {
    if (event.data === 'limpar') { event.waitUntil(caches.keys().then((n) => Promise.all(n.filter((x) => x.startsWith('oravel-colab-paginas-')).map((x) => caches.delete(x))))); }
});

self.addEventListener('push', (event) => {
    let d = {};
    try { d = event.data ? event.data.json() : {}; } catch (e) { d = { title: 'Oravel', body: event.data ? event.data.text() : '' }; }
    event.waitUntil(self.registration.showNotification(d.title || 'Oravel', {
        body: d.body || '', icon: '/icon.png', badge: '/icon.png', data: { url: d.url || BASE },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || BASE;
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((lista) => {
        for (const c of lista) { if ('focus' in c) { c.navigate(url); return c.focus(); } }
        return self.clients.openWindow(url);
    }));
});
JS;
    }
}
