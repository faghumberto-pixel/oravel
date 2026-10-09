<style>
    /* Portal do Cliente (pedido do usuário 2026-10-08): menu lateral e barra
       superior em #1d2133, área central em #fbfbfb, item ativo em #3c3f69 --
       cores tiradas da imagem de referência. Só vale no portal-cliente. */
    .fi-sidebar,
    .fi-sidebar-header,
    .fi-topbar-bar,
    .fi-topbar > nav {
        background: #1d2133 !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
    }

    .fi-body,
    .fi-main-ctn {
        background-color: #fbfbfb !important;
    }

    /* Texto e ícones do menu sempre claros sobre o fundo escuro */
    .fi-sidebar-item-label,
    .fi-sidebar-item-icon,
    .fi-sidebar-item a,
    .fi-sidebar-item button {
        color: #e5e7f2 !important;
    }

    .fi-sidebar-group-label {
        color: #9aa3c7 !important;
    }

    .fi-sidebar-item > a:hover,
    .fi-sidebar-item > button:hover {
        background-color: rgba(255, 255, 255, 0.07) !important;
    }

    .fi-sidebar-item.fi-active > a,
    .fi-sidebar-item[aria-current="page"] > a,
    .fi-sidebar-item.fi-active > button {
        background-color: #3c3f69 !important;
    }

    .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    .fi-sidebar-item.fi-active .fi-sidebar-item-icon,
    .fi-sidebar-item[aria-current="page"] .fi-sidebar-item-label,
    .fi-sidebar-item[aria-current="page"] .fi-sidebar-item-icon {
        color: #ffffff !important;
        font-weight: 600;
    }

    .fi-topbar .fi-icon-btn svg,
    .fi-topbar-item {
        color: #e5e7f2 !important;
    }
</style>
