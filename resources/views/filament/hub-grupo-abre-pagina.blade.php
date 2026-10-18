{{--
    Menu lateral: ao ABRIR um grupo (clicando no nome ou na seta), o usuário vai direto à página principal do grupo, que traz os
    botões de todos os filhos. Clicar de novo para fechar só recolhe o grupo, e grupos sem página principal seguem como antes.
--}}
@if (auth()->check())
    <script>
        (function () {
            var mapa = @json(\App\Support\HubMenus::paginaPrincipalDeCadaGrupo(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            document.addEventListener('click', function (evento) {
                var botao = evento.target.closest('.fi-sidebar-group-button');
                var item = botao && botao.closest('li[data-group-label]');
                var url = item && mapa[item.getAttribute('data-group-label')];
                var menu = window.Alpine && window.Alpine.store('sidebar');

                if (!url || !menu || !menu.groupIsCollapsed(item.getAttribute('data-group-label'))) {
                    return; // sem página principal, ou o grupo está aberto e o clique só o fecha
                }

                var destino = new URL(url, window.location.href);

                if (destino.pathname.replace(/\/$/, '') !== window.location.pathname.replace(/\/$/, '')) {
                    window.location.href = destino.href;
                }
            }, true);
        })();
    </script>
@endif
