// Este service worker nao e' mais registrado por nenhum codigo do app (a
// chamada navigator.serviceWorker.register(...) foi removida em algum
// momento) -- mas navegadores que ja tinham a versao antiga instalada
// continuam rodando ela pra sempre, interceptando fetch() de tudo,
// inclusive chamadas do Livewire, sem que um refresh normal resolva.
// Este arquivo agora e' um "kill switch": na proxima checagem de update
// (automatica, a cada navegacao), o navegador baixa esta versao, ve que
// e' diferente, instala, e ela se autodesinstala e recarrega as abas
// controladas -- sem precisar de acao manual no DevTools.
self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        self.registration.unregister().then(() => {
            return self.clients.matchAll({type: 'window'});
        }).then((clients) => {
            clients.forEach((client) => client.navigate(client.url));
        })
    );
});
