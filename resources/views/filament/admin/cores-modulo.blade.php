@php($tema = \App\Support\Pacotes::tema(\App\Support\Tenancy::current()))
@if ($tema)
{{-- Cor do módulo contratado (desktop): menu lateral, topo e destaque na cor do produto, como nos apps do site. Locadora e empresas com
     mais de um produto ficam no visual padrão (este bloco nem é impresso). --}}
<style data-tema="{{ $tema['codigo'] }}">
    :root { --oravel-primary: {{ $tema['cor'] }}; --oravel-menu: {{ $tema['menu'] }}; --oravel-topo: {{ $tema['topo'] }}; }

    html:not(.dark) .fi-sidebar, html.dark .fi-sidebar,
    html:not(.dark) .fi-sidebar-header, html.dark .fi-sidebar-header {
        background: linear-gradient(180deg, {{ $tema['menu'] }} 0%, {{ $tema['menu_fim'] }} 100%) !important;
        border-color: rgba(255, 255, 255, 0.10) !important;
    }

    /* O Filament pinta o miolo do menu à parte (mesma especificidade da regra de cores-painel); deixa o degradê da sidebar aparecer. */
    html:not(.dark) .fi-sidebar .fi-sidebar-nav, html.dark .fi-sidebar .fi-sidebar-nav,
    html:not(.dark) .fi-sidebar .fi-sidebar-nav-groups, html.dark .fi-sidebar .fi-sidebar-nav-groups,
    html:not(.dark) .fi-sidebar .fi-sidebar-group, html.dark .fi-sidebar .fi-sidebar-group,
    html:not(.dark) .fi-sidebar .fi-sidebar-group-items, html.dark .fi-sidebar .fi-sidebar-group-items {
        background: transparent !important;
    }

    html:not(.dark) .fi-topbar-bar, html.dark .fi-topbar-bar,
    html:not(.dark) .fi-topbar, html.dark .fi-topbar {
        background: {{ $tema['topo'] }} !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
    }

    html:not(.dark) .fi-page-header, html.dark .fi-page-header,
    html:not(.dark) .fi-header, html.dark .fi-header {
        background: linear-gradient(135deg, {{ $tema['topo'] }} 0%, {{ $tema['menu'] }} 100%) !important;
        box-shadow: 0 10px 30px {{ $tema['menu'] }}33 !important;
    }

    /* Letras brancas no menu lateral e no topo (pedido do usuário, 19/10/2026): itens, grupos, ícones, busca e botões. */
    html:not(.dark) .fi-sidebar .fi-sidebar-item-label, html.dark .fi-sidebar .fi-sidebar-item-label,
    html:not(.dark) .fi-sidebar .fi-sidebar-item-icon, html.dark .fi-sidebar .fi-sidebar-item-icon,
    html:not(.dark) .fi-sidebar .fi-sidebar-item a, html.dark .fi-sidebar .fi-sidebar-item a,
    html:not(.dark) .fi-sidebar .fi-sidebar-item button, html.dark .fi-sidebar .fi-sidebar-item button,
    html:not(.dark) .fi-sidebar .fi-sidebar-group-label, html.dark .fi-sidebar .fi-sidebar-group-label,
    html:not(.dark) .fi-sidebar .fi-sidebar-group-button svg, html.dark .fi-sidebar .fi-sidebar-group-button svg,
    html:not(.dark) .fi-sidebar .fi-sidebar-group-icon, html.dark .fi-sidebar .fi-sidebar-group-icon,
    html:not(.dark) .fi-sidebar .fi-icon-btn, html.dark .fi-sidebar .fi-icon-btn,
    html:not(.dark) .fi-sidebar .fi-icon-btn svg, html.dark .fi-sidebar .fi-icon-btn svg,
    html:not(.dark) .fi-topbar .fi-icon-btn svg, html.dark .fi-topbar .fi-icon-btn svg,
    html:not(.dark) .fi-topbar-item, html.dark .fi-topbar-item,
    html:not(.dark) .fi-topbar .fi-global-search-field svg, html.dark .fi-topbar .fi-global-search-field svg,
    html:not(.dark) .fi-topbar .fi-global-search-field input, html.dark .fi-topbar .fi-global-search-field input {
        color: #ffffff !important;
    }
    html .fi-topbar .fi-global-search-field input::placeholder { color: #ffffff !important; opacity: 0.85; }

    html .fi-sidebar .fi-sidebar-item.fi-active > a,
    html .fi-sidebar .fi-sidebar-item[aria-current="page"] > a,
    html .fi-sidebar .fi-sidebar-item.fi-active > button {
        background-color: {{ $tema['cor'] }} !important;
    }
</style>
@endif
