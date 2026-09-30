{{--
    Sidebar com largura ajustável pelo mouse (pedido do usuário 2026-09-30).
    O Filament define --sidebar-width em :root (layout/base.blade.php) e tanto
    o sidebar quanto o conteúdo usam essa variável; então basta sobrescrevê-la
    no <html> durante o arrasto. Largura persistida em localStorage.
--}}
<style>
    .oravel-sidebar-resizer {
        position: fixed; top: 0; bottom: 0; z-index: 40; width: 8px;
        margin-left: -4px; cursor: col-resize; display: none;
        background: transparent; transition: background .15s;
    }
    .oravel-sidebar-resizer:hover, .oravel-sidebar-resizer.is-dragging { background: rgba(59, 130, 246, .45); }
    @media (min-width: 1024px) { .oravel-sidebar-resizer.is-visible { display: block; } }
    body.oravel-resizing { cursor: col-resize; user-select: none; }
    body.oravel-resizing .fi-main-ctn, body.oravel-resizing .fi-sidebar { transition: none !important; }
</style>
<script>
    (function () {
        const KEY = 'oravel.sidebarWidth', MIN = 200, MAX = 560;
        const root = document.documentElement;
        const clamp = (w) => Math.min(MAX, Math.max(MIN, Math.round(w)));

        try {
            const saved = parseInt(localStorage.getItem(KEY), 10);
            if (saved) root.style.setProperty('--sidebar-width', clamp(saved) + 'px');
        } catch (e) {}

        function init() {
            const sidebar = document.querySelector('.fi-sidebar');
            if (!sidebar || document.querySelector('.oravel-sidebar-resizer')) return;

            const handle = document.createElement('div');
            handle.className = 'oravel-sidebar-resizer';
            handle.title = 'Arraste para ajustar a largura (duplo clique restaura)';
            document.body.appendChild(handle);

            const place = () => {
                const r = sidebar.getBoundingClientRect();
                const open = r.width > 0 && r.right > 0 && getComputedStyle(sidebar).display !== 'none'
                    && r.width > 120; // fechado/colapsado em ícones: sem alça
                handle.classList.toggle('is-visible', open);
                if (open) handle.style.left = r.right + 'px';
            };
            place();
            new ResizeObserver(place).observe(sidebar);
            window.addEventListener('resize', place);
            setInterval(place, 500); // acompanha a animação de abrir/fechar

            let dragging = false;
            handle.addEventListener('pointerdown', (e) => {
                dragging = true;
                handle.setPointerCapture(e.pointerId);
                handle.classList.add('is-dragging');
                document.body.classList.add('oravel-resizing');
                e.preventDefault();
            });
            handle.addEventListener('pointermove', (e) => {
                if (!dragging) return;
                const rtl = document.dir === 'rtl';
                const w = clamp(rtl ? window.innerWidth - e.clientX : e.clientX);
                root.style.setProperty('--sidebar-width', w + 'px');
                place();
            });
            const stop = () => {
                if (!dragging) return;
                dragging = false;
                handle.classList.remove('is-dragging');
                document.body.classList.remove('oravel-resizing');
                try {
                    localStorage.setItem(KEY, parseInt(getComputedStyle(root).getPropertyValue('--sidebar-width'), 10));
                } catch (e) {}
            };
            handle.addEventListener('pointerup', stop);
            handle.addEventListener('pointercancel', stop);
            handle.addEventListener('dblclick', () => {
                root.style.removeProperty('--sidebar-width');
                try { localStorage.removeItem(KEY); } catch (e) {}
                place();
            });
        }

        document.addEventListener('DOMContentLoaded', init);
        document.addEventListener('livewire:navigated', init);
    })();
</script>
