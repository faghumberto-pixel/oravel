@auth
    {{--
        Atalho estilo "leader key" (G depois de uma letra, igual GitHub/Linear):
        aperta G, tem ~1.2s pra apertar a segunda tecla, senao cancela. Ignora
        quando o foco ta num campo de digitacao (input/textarea/select/
        contenteditable ou qualquer coisa dentro de um formulario Livewire),
        pra nao atrapalhar quem ta digitando "g" normalmente.

        O mapa de teclas -> URL fica no lado do PHP (route()/::getUrl()) pra
        respeitar automaticamente o tenant atual e permissao de cada item --
        o mesmo botao de menu ja escondido por falta de permissao/plano nao
        ganha atalho funcional aqui.
    --}}
    <script>
        (function () {
            const shortcuts = {
                o: '{{ route('filament.admin.resources.maintenance-orders.index') }}',
                p: '{{ route('filament.admin.pages.maintenance-kanban') }}',
                a: '{{ route('filament.admin.resources.assets.index') }}',
                c: '{{ route('filament.admin.resources.clients.index') }}',
                m: '{{ route('filament.admin.pages.chat') }}',
                r: '{{ route('filament.admin.pages.relatorios') }}',
            };

            let leaderActive = false;
            let leaderTimeout = null;

            function isTypingContext(el) {
                if (!el) return false;
                const tag = el.tagName;
                return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
            }

            document.addEventListener('keydown', function (event) {
                if (event.metaKey || event.ctrlKey || event.altKey) return;
                if (isTypingContext(event.target)) return;

                const key = event.key.toLowerCase();

                if (leaderActive) {
                    leaderActive = false;
                    clearTimeout(leaderTimeout);

                    const url = shortcuts[key];
                    if (url) {
                        event.preventDefault();
                        window.location.href = url;
                    }
                    return;
                }

                if (key === 'g') {
                    leaderActive = true;
                    leaderTimeout = setTimeout(function () {
                        leaderActive = false;
                    }, 1200);
                }
            });
        })();
    </script>
@endauth
