<style>
    /* Central no mesmo padrão do app (pedido do usuário 2026-10-08): paleta neutra escura da imagem
       de referência -- menu #17191f, topo #1e1f26, fundo #15161b, cards #202128 -- e laranja da marca
       como destaque. A Central é sempre escura. Vale sobre o tema compilado (theme.css). */
    html.dark .fi-sidebar,
    html.dark .fi-sidebar-header,
    html.dark .fi-sidebar .fi-sidebar-nav {
        background: #17191f !important;
        border-color: rgba(255, 255, 255, 0.07) !important;
        box-shadow: none !important;
    }

    html.dark .fi-topbar-bar,
    html.dark .fi-topbar > nav {
        background: #1e1f26 !important;
        border-color: rgba(255, 255, 255, 0.07) !important;
        box-shadow: none !important;
    }

    html.dark .fi-body,
    html.dark .fi-layout {
        background-color: #15161b !important;
    }

    html.dark .fi-main {
        background-color: #1a1b21 !important;
    }

    html.dark .fi-sidebar .fi-sidebar-item-label,
    html.dark .fi-sidebar .fi-sidebar-item-icon {
        color: #d9dbe6 !important;
    }

    html.dark .fi-sidebar .fi-sidebar-group-label,
    html.dark .fi-sidebar .fi-sidebar-group-button svg {
        color: #8d91a5 !important;
    }

    html.dark .fi-sidebar .fi-sidebar-item:not(.fi-active) .fi-sidebar-item-button:hover {
        background-color: rgba(255, 255, 255, 0.06) !important;
    }

    html.dark .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-button,
    html.dark .fi-sidebar .fi-sidebar-item.fi-active > a {
        background-color: #f86c24 !important;
    }

    html.dark .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-icon,
    html.dark .fi-sidebar .fi-sidebar-item.fi-active .fi-sidebar-item-label {
        color: #ffffff !important;
        font-weight: 700 !important;
    }

    html.dark .fi-topbar .fi-icon-btn svg,
    html.dark .fi-topbar-item {
        color: #d9dbe6 !important;
    }
</style>
