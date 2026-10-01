{{--
    Sessão/token expirado (HTTP 419) em requisições do Livewire: o padrão do Livewire
    abre um confirm() em INGLÊS ("This page has expired. Would you like to refresh
    the page?") e, a cada atualização automática (sino de notificações, widgets), a
    janela volta a aparecer em abas que ficaram abertas. Aqui o 419 é tratado de forma
    silenciosa: recarrega a página uma vez (se a sessão ainda vale, tudo volta ao
    normal; se acabou, cai na tela de login). A trava de 15 s evita laço de
    recarregamento: se o 419 persistir logo após recarregar, avisa em português.
    oravel-session-recovery
--}}
<script>
    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status !== 419) {
                    return;
                }

                preventDefault();

                const KEY = 'oravel.lw419.reloadedAt';
                let last = 0;
                try { last = parseInt(sessionStorage.getItem(KEY), 10) || 0; } catch (e) {}

                if (Date.now() - last > 15000) {
                    try { sessionStorage.setItem(KEY, String(Date.now())); } catch (e) {}
                    window.location.reload();
                } else if (!window.__oravelSessionWarned) {
                    window.__oravelSessionWarned = true;
                    alert('Sua sessão expirou ou foi encerrada em outra aba. Entre novamente para continuar.');
                }
            });
        });
    });
</script>
