self.addEventListener('fetch', (event) => {
    // AQUI É ONDE O ERRO ESTÁ SENDO GERADO
    // Adicione uma condição para ignorar rotas do Livewire
    if (event.request.url.includes('/livewire/')) {
        return; // Isso fará com que o Service Worker ignore o Livewire e deixe o Laravel tratar
    }

    // O restante do seu código de cache...
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});