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

    html .fi-sidebar .fi-sidebar-item.fi-active > a,
    html .fi-sidebar .fi-sidebar-item[aria-current="page"] > a,
    html .fi-sidebar .fi-sidebar-item.fi-active > button {
        background-color: {{ $tema['cor'] }} !important;
    }
</style>
@endif
