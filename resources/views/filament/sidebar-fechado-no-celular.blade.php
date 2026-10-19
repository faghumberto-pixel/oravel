{{--
    No celular o menu lateral abria por cima da tela inicial (o Filament guarda "aberto" no navegador e o padrão é aberto),
    escondendo o conteúdo no primeiro acesso do cliente. Em tela estreita, começa fechado; o botão do topo abre quando precisar.
--}}
<script>
    document.addEventListener('alpine:initialized', function () {
        try {
            if (window.matchMedia('(max-width: 1023px)').matches && window.Alpine && Alpine.store('sidebar')) {
                Alpine.store('sidebar').close();
            }
        } catch (e) {}
    });
</script>
