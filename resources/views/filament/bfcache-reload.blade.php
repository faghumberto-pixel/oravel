{{--
    Corrige o 419 "Page Expired" ao usar o botao Voltar do navegador: quando
    a pagina volta do bfcache (bfcache = snapshot congelado em JS, sem nova
    requisicao ao servidor), o token CSRF embutido no HTML congelado pode
    estar desatualizado em relacao a sessao atual -- o primeiro clique num
    componente Livewire (POST /livewire/update) falha com 419. Forcar um
    reload real busca a pagina de novo com token fresco.
--}}
<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
