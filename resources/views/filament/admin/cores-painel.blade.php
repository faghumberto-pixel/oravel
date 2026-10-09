<style>
    /* Painel do app no tema CLARO (pedido do usuário 2026-10-08): mesmo modelo do Portal do
       Cliente -- menu e topo em #1d2133, centro em #fbfbfb, item ativo e botões na laranja da marca (#f86c24, como na imagem de referência) -- e a
       cor primária é a laranja da marca (o azul deixa de ser a base). No tema escuro nada muda. */
    :root { --oravel-bg: #fbfbfb; --oravel-border: #e7e8ee; }

    html:not(.dark) .fi-sidebar,
    html:not(.dark) .fi-sidebar-header,
    html:not(.dark) .fi-topbar-bar {
        background: #1d2133 !important;
        border-color: rgba(255, 255, 255, 0.08) !important;
        box-shadow: none !important;
    }

    html:not(.dark) .fi-body,
    html:not(.dark) .fi-main-ctn {
        background-color: #fbfbfb !important;
    }

    /* Texto e ícones do menu: claros sobre o fundo escuro */
    html:not(.dark) .fi-sidebar .fi-sidebar-item-label,
    html:not(.dark) .fi-sidebar .fi-sidebar-item-icon,
    html:not(.dark) .fi-sidebar .fi-sidebar-item a,
    html:not(.dark) .fi-sidebar .fi-sidebar-item button {
        color: #e5e7f2 !important;
        font-weight: 500 !important;
    }

    html:not(.dark) .fi-sidebar .fi-sidebar-group-label,
    html:not(.dark) .fi-sidebar .fi-sidebar-group-button svg,
    html:not(.dark) .fi-sidebar .fi-sidebar-group-icon {
        color: #9aa3c7 !important;
    }

    html:not(.dark) .fi-sidebar .fi-sidebar-item:not(.fi-active) > a:hover,
    html:not(.dark) .fi-sidebar .fi-sidebar-item:not(.fi-active) > button:hover {
        background-color: rgba(255, 255, 255, 0.07) !important;
    }

    html:not(.dark) .fi-sidebar .fi-sidebar-item.fi-active > a,
    html:not(.dark) .fi-sidebar .fi-sidebar-item[aria-current="page"] > a,
    html:not(.dark) .fi-sidebar .fi-sidebar-item.fi-active > button,
    html:not(.dark) .fi-sidebar .fi-sidebar-item.fi-active > a:hover {
        background-color: var(--oravel-primary) !important;
    }

    html:not(.dark) .fi-sidebar .fi-sidebar-group .fi-sidebar-item.fi-active .fi-sidebar-item-label,
    html:not(.dark) .fi-sidebar .fi-sidebar-group .fi-sidebar-item.fi-active .fi-sidebar-item-icon,
    html:not(.dark) .fi-sidebar .fi-sidebar-item.fi-active > a,
    html:not(.dark) .fi-sidebar .fi-sidebar-item[aria-current="page"] .fi-sidebar-item-label {
        color: #ffffff !important;
        font-weight: 700 !important;
    }

    /* Destaque de grupo/submenu: antes azul translúcido */
    html:not(.dark) .fi-sidebar .fi-sidebar-item:has(~ .fi-sidebar-item[aria-current]),
    html:not(.dark) .fi-sidebar .fi-sidebar-item.fi-active:has(~ .fi-sidebar-item[aria-current]) {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }

    html:not(.dark) .fi-sidebar .fi-sidebar-group + .fi-sidebar-group {
        box-shadow: 0 -1px 0 rgba(255, 255, 255, 0.08) !important;
    }

    html:not(.dark) .fi-sidebar .fi-sidebar-nav {
        scrollbar-color: rgba(255, 255, 255, 0.25) transparent;
    }

    /* Faixa de título das páginas: no lugar do gradiente azul */
    html:not(.dark) .fi-page-header,
    html:not(.dark) .fi-header {
        background: linear-gradient(135deg, #1d2133 0%, #262a40 60%, #32364f 100%) !important;
        box-shadow: 0 10px 30px rgba(29, 33, 51, 0.18) !important;
    }

    html:not(.dark) .fi-topbar .fi-icon-btn svg,
    html:not(.dark) .fi-topbar-item {
        color: #e5e7f2 !important;
    }

    /* A área rolável do menu tinha fundo próprio (claro): fica escura como o resto */
    html:not(.dark) .fi-sidebar .fi-sidebar-nav,
    html:not(.dark) .fi-sidebar .fi-sidebar-nav-groups,
    html:not(.dark) .fi-sidebar .fi-sidebar-group,
    html:not(.dark) .fi-sidebar .fi-sidebar-group-items {
        background: #1d2133 !important;
    }

    /* Telas com botões, abas e campos em azul/índigo fixo (classes Tailwind): laranja da marca */
    html:not(.dark) .bg-indigo-500,
    html:not(.dark) .bg-indigo-600,
    html:not(.dark) .bg-indigo-700,
    html:not(.dark) .bg-blue-600,
    html:not(.dark) .bg-blue-700,
    html:not(.dark) .hover\:bg-indigo-500:hover,
    html:not(.dark) .hover\:bg-indigo-700:hover,
    html:not(.dark) .hover\:bg-blue-700:hover {
        background-color: var(--oravel-primary) !important;
    }

    html:not(.dark) .focus\:border-indigo-500:focus,
    html:not(.dark) .focus\:border-blue-500:focus {
        border-color: var(--oravel-primary) !important;
    }

    html:not(.dark) .focus\:ring-indigo-500:focus,
    html:not(.dark) .focus\:ring-blue-500:focus {
        --tw-ring-color: var(--oravel-primary) !important;
    }

    html:not(.dark) .text-indigo-600,
    html:not(.dark) .text-indigo-700 {
        color: var(--oravel-primary) !important;
    }
</style>
