{{--
    Sidebar em modo "sanfona" (accordion) -- pedido do usuário 2026-09-24:
    "quando um menu estiver ativo, os outros precisam recolher". Sem isso,
    todos os grupos do menu (PMP, Manutenção, Financeiro...) ficam
    expandidos ao mesmo tempo o tempo todo (comportamento padrão do
    Filament quando nenhum grupo foi colapsado manualmente ainda).

    Reaproveita o próprio Alpine store 'sidebar' do Filament
    (vendor/filament/filament/resources/js/index.js) -- não substitui nada,
    só encapsula toggleCollapsedGroup() pra fechar os demais grupos quando
    um é aberto, e sincroniza automaticamente ao navegar (só o grupo do
    item ativo fica aberto).
--}}
<script>
    document.addEventListener('alpine:init', () => {
        const sidebar = window.Alpine.store('sidebar');
        if (!sidebar) {
            return;
        }

        function allGroupLabels() {
            return Array.from(document.querySelectorAll('[data-group-label]'))
                .map((el) => el.getAttribute('data-group-label'));
        }

        function activeGroupLabel() {
            const activeItem = document.querySelector(
                '.fi-sidebar-group .fi-sidebar-item.fi-active, .fi-sidebar-group .fi-sidebar-item[aria-current="page"]'
            );
            const group = activeItem?.closest('[data-group-label]');

            return group?.getAttribute('data-group-label') ?? null;
        }

        function collapseAllExcept(keepOpenLabel) {
            const collapsed = new Set(sidebar.collapsedGroups ?? []);

            allGroupLabels().forEach((label) => {
                if (label === keepOpenLabel) {
                    collapsed.delete(label);
                } else {
                    collapsed.add(label);
                }
            });

            sidebar.collapsedGroups = Array.from(collapsed);
        }

        const originalToggle = sidebar.toggleCollapsedGroup.bind(sidebar);

        sidebar.toggleCollapsedGroup = function (group) {
            const wasCollapsed = (this.collapsedGroups ?? []).includes(group);

            originalToggle(group);

            // Só fecha os outros quando o clique ABRIU o grupo (estava
            // fechado, agora não está) -- fechar manualmente o grupo aberto
            // continua funcionando normalmente, sem reabrir nada sozinho.
            if (wasCollapsed) {
                collapseAllExcept(group);
            }
        };

        function syncActiveGroupOnLoad() {
            const active = activeGroupLabel();
            if (active) {
                collapseAllExcept(active);
            }
        }

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(syncActiveGroupOnLoad);
        });

        requestAnimationFrame(syncActiveGroupOnLoad);
    });
</script>
