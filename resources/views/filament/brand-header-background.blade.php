<style>
    /* Tokens do artefato "Central de Artefatos" (2026-07-25), adaptados
       pro painel Filament. Mesma logica de :root[data-theme] usada nos
       artefatos, mas aqui seguindo o dark mode do proprio Filament (classe
       .dark na <html>, ver base.blade.php). */
    :root {
        --oravel-bg: #faf7f2;
        --oravel-surface: #ffffff;
        --oravel-border: #e8e0d4;
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

    /* Topbar (linha 1, some ao rolar) e menu (linha 2, sticky) agora sao
       2 elementos HTML de verdade -- ver
       resources/views/vendor/filament-panels/components/topbar/index.blade.php.
       Cores/tamanhos deles ja estao no template; aqui so' fica o que
       ainda precisa ser CSS mesmo (cores de texto/icone que nao tem
       variante dark: propria, cards, etc). */

    /* Espacamento de verdade entre Logo+Tenant / Relogio / Avisos --
       precisa ser CSS puro (gap, nao classe Tailwind tipo "gap-x-10"):
       o Vite nao builda neste ambiente, entao classes novas que nao
       existiam no CSS compilado ANTES desta sessao nao tem efeito nenhum
       -- essa era a causa real do "tudo colado" reportado varias vezes. */
    .fi-oravel-topbar-row1 {
        padding-top: 0.625rem;
        padding-bottom: 0.625rem;
        gap: 2.5rem;
    }

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
