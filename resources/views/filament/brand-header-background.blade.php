<style>
    /* Tokens do artefato "Central de Artefatos" (2026-07-25), adaptados
       pro painel Filament. Mesma logica de :root[data-theme] usada nos
       artefatos, mas aqui seguindo o dark mode do proprio Filament (classe
       .dark na <html>, ver base.blade.php). */
    /* Tema padrao (2026-09-18, pedido do usuario): cinza bem clarinho no
       lugar do creme antigo -- so' afeta o FUNDO DO CONTEUDO (.fi-body).
       Sidebar/topbar sao sempre azul-escuro, ver .fi-sidebar/.fi-topbar
       mais abaixo, independente do modo claro/escuro ativo. */
    :root {
        --oravel-bg: #f4f5f7;
        --oravel-surface: #ffffff;
        --oravel-border: #e5e7eb;
        /* Fonte única (config('oravel.brand'), 2026-09-23): mesmos valores
           usados pelo lado PHP (AdminPanelProvider::panel(), Color::hex())
           -- antes eram hex crus repetidos aqui, em theme.css e no
           provider, cada um por conta própria. */
        --oravel-primary: {{ config('oravel.brand.primary') }};
        --oravel-sidebar-from: {{ config('oravel.brand.sidebar_from') }};
        --oravel-sidebar-to: {{ config('oravel.brand.sidebar_to') }};
    }

    html.dark {
        --oravel-bg: #0a0e17;
        --oravel-surface: #121826;
        --oravel-border: #232c40;
    }

    /* Fundo da area de conteudo (era bg-gray-50/dark:bg-gray-950 padrao do
       Filament) -- creme claro / navy bem escuro no lugar do cinza neutro. */
    .fi-body {
        background-color: var(--oravel-bg) !important;
    }

    /* Fonte igual a dos artefatos -- aplicado via CSS direto (nao no
       tailwind.config.js) porque o Vite nao builda aqui (Node 18 instalado,
       Vite 7 exige 20.19+/22.12+ -- ver memoria do projeto sobre isso). */
    .fi-body {
        font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif !important;
    }

    /* Topbar unificado (2026-09-18): logo, tenant switcher, avisos e menu
       consolidados numa única linha com cor consistente. Cores/tamanhos
       estão no template topbar/index.blade.php. */

    /* Espaco entre "Oravel" e o nome do tenant, dentro do logo. */
    .fi-oravel-brand-logo-row {
        gap: 1rem;
    }

    @if (filament()->getId() === 'admin')
    /* Paleta da Academia (02/10/2026): faixa superior (cabecalho da sidebar
       + topbar) no MESMO gradiente navy, contínuo (background-attachment:
       fixed => os dois mostram a mesma fatia de um gradiente só). Corpo da
       sidebar branco, claro; no modo escuro vira cinza-escuro. */
    :root { --oravel-bg: #f3f6fb; --oravel-border: #e4e8f0; }
    .fi-topbar-bar,
    .fi-sidebar-header {
        background: linear-gradient(90deg, var(--oravel-sidebar-from), var(--oravel-sidebar-to)) fixed !important;
        border: 0 !important;
        box-shadow: 0 1px 0 rgba(255,255,255,.08) inset !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }
    .fi-topbar-item { color: #dbe7ff !important; }
    .fi-topbar-item.fi-active { color: #fff !important; }
    .fi-topbar .fi-icon-btn svg { color: #dbe7ff !important; }
    .fi-sidebar { background: #fff !important; border-right: 1px solid var(--oravel-border); }
    html.dark .fi-sidebar { background: #0f172a !important; border-right-color: #232c40; }
    .fi-sidebar-nav { scrollbar-color: #cbd5e1 transparent; scrollbar-width: thin; }
    .fi-sidebar-nav::-webkit-scrollbar { width: 6px; }
    .fi-sidebar-nav::-webkit-scrollbar-track { background: transparent; }
    .fi-sidebar-nav::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 9999px; }
    @else
    .fi-topbar-item {
        color: #e5e7eb !important;
    }

    .fi-topbar-item.fi-active {
        color: #fff !important;
    }

    .fi-topbar .fi-icon-btn svg {
        color: #e5e7eb !important;
    }

    /* Sidebar sempre azul-escuro (2026-09-18, pedido do usuario), tanto no
       tema claro quanto no escuro -- mesmo tom do topbar (gradiente
       #0f172a -> #1a2438, ver topbar/index.blade.php). A classe "dark"
       fixa no <aside> (ver override de sidebar/index.blade.php) ja cuida
       de textos/icones ficarem na variante clara (text-gray-200 etc, que
       sao dark:); aqui so' falta o FUNDO em si, que por padrao segue
       .fi-body (transparente no desktop) e ficaria cinza-claro junto com
       o resto do conteudo sem isso. */
    .fi-sidebar {
        background: linear-gradient(to bottom right, var(--oravel-sidebar-from), var(--oravel-sidebar-to)) !important;
    }

    .fi-sidebar-header {
        background: var(--oravel-sidebar-from) !important;
    }

    /* Barra de rolagem do sidebar (2026-09-18, pedido do usuario): cinza
       translucido em vez do padrao do navegador (que fica claro/destoante
       em cima do fundo azul-escuro fixo). Firefox via scrollbar-color,
       Chrome/Edge/Safari via os pseudo-elementos -webkit-scrollbar*. */
    .fi-sidebar-nav {
        scrollbar-color: rgba(255, 255, 255, 0.18) transparent;
        scrollbar-width: thin;
    }

    .fi-sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .fi-sidebar-nav::-webkit-scrollbar-track {
        background: transparent;
    }

    .fi-sidebar-nav::-webkit-scrollbar-thumb {
        background-color: rgba(255, 255, 255, 0.18);
        border-radius: 9999px;
    }

    .fi-sidebar-nav::-webkit-scrollbar-thumb:hover {
        background-color: rgba(255, 255, 255, 0.3);
    }
    @endif

    @if (filament()->getId() === 'admin')
    /* Sem opcao de ocultar o topbar no desktop -- some com os botoes de
       abrir/fechar a sidebar (o menu de navegacao real e' o topo). So'
       desktop: no celular esses botoes ainda sao a unica forma de abrir
       a gaveta com os grupos de navegacao (o menu horizontal e' lg:flex,
       nao aparece no celular). Restrito ao painel 'admin' (2026-09-25,
       achado no portal-cliente que passou a reaproveitar esta partial):
       o admin tem o menu horizontal no topbar como alternativa real ao
       botao de abrir a sidebar, o portal-cliente NAO tem -- esconder o
       botao la' deixava a sidebar sem NENHUM jeito de reabrir quando
       fechada (inclusive por um estado "fechada" herdado do admin via
       localStorage, compartilhado no mesmo dominio). */
    @media (min-width: 1024px) {
        .fi-topbar-open-sidebar-btn,
        .fi-topbar-close-sidebar-btn {
            display: none !important;
        }
    }
    @endif

    /* Cards/paineis (Section do Filament, compartilhado por forms e
       infolists, + widgets e tabelas) -- borda e sombra suaves como nos
       cards do artefato, cantos mais arredondados. */
    .fi-section,
    .fi-wi-widget,
    .fi-ta-ctn {
        border-color: var(--oravel-border) !important;
        border-radius: 0.875rem !important;
        box-shadow: 0 1px 2px rgba(28, 24, 21, 0.04), 0 16px 32px -12px rgba(28, 24, 21, 0.12) !important;
    }

    html.dark .fi-section,
    html.dark .fi-wi-widget,
    html.dark .fi-ta-ctn {
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4), 0 16px 40px -12px rgba(0, 0, 0, 0.5) !important;
    }
</style>
