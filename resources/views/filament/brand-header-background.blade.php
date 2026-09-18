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
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
    }

    /* Topbar unificado (2026-09-18): logo, tenant switcher, avisos e menu
       consolidados numa única linha com cor consistente. Cores/tamanhos
       estão no template topbar/index.blade.php. */

    /* Espaco entre "Oravel" e o nome do tenant, dentro do logo. */
    .fi-oravel-brand-logo-row {
        gap: 1rem;
    }

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
        background: linear-gradient(to bottom right, #0f172a, #1a2438) !important;
    }

    .fi-sidebar-header {
        background: #0f172a !important;
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

    /* Sem opcao de ocultar o topbar no desktop -- some com os botoes de
       abrir/fechar a sidebar (o menu de navegacao real e' o topo). So'
       desktop: no celular esses botoes ainda sao a unica forma de abrir
       a gaveta com os grupos de navegacao (o menu horizontal e' lg:flex,
       nao aparece no celular). */
    @media (min-width: 1024px) {
        .fi-topbar-open-sidebar-btn,
        .fi-topbar-close-sidebar-btn {
            display: none !important;
        }
    }

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
