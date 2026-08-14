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

    /* Espaco entre "Oravel" e o nome do tenant, dentro do logo. */
    .fi-oravel-brand-logo-row {
        gap: 1rem;
    }

    .fi-topbar .fi-icon-btn svg {
        color: #e5e7eb !important;
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
