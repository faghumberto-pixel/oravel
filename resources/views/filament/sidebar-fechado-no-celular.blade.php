{{--
    No celular o menu lateral abria por cima da tela inicial (o Filament guarda "aberto" no navegador e o padrão é aberto),
    escondendo o conteúdo no primeiro acesso do cliente. Em tela estreita, começa fechado; o botão do topo abre quando precisar.
    Como o Filament grava o estado no navegador, o fechamento automático é marcado: ao voltar para tela larga (janela maximizada),
    o menu é reaberto, para que o fechamento do celular não "grude" no desktop.
--}}
<script>
    document.addEventListener('alpine:initialized', function () {
        try {
            if (!window.Alpine || !Alpine.store('sidebar')) { return; }
            var chave = 'oravel.menuFechadoAuto';

            if (window.matchMedia('(max-width: 1023px)').matches) {
                if (Alpine.store('sidebar').isOpen) {
                    Alpine.store('sidebar').close();
                    localStorage.setItem(chave, '1');
                }
            } else if (localStorage.getItem(chave) === '1') {
                localStorage.removeItem(chave);
                Alpine.store('sidebar').open();
            }
        } catch (e) {}
    });
</script>
