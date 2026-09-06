<style>
    /* Mesmo padrao usado no painel admin (ver filament.login-background) --
       so' afeta a pagina "simples" (login), nunca o painel autenticado.
       CSS puro via renderHook, nao mexe na view do login em si -- ver
       feedback_public_hot_vite_ghost_file_CRITICAL / lição de 2026-09-06
       sobre nao customizar templates de login de forma fragil. */
    .fi-simple-layout {
        background-image: linear-gradient(180deg, rgba(15, 15, 15, 0.55) 0%, rgba(15, 15, 15, 0.75) 100%), url('{{ asset('images/login-bg-cliente.jpg') }}');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .fi-simple-layout .fi-simple-main {
        background-color: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(4px);
    }

    :root[data-theme="dark"] .fi-simple-layout .fi-simple-main,
    .dark .fi-simple-layout .fi-simple-main {
        background-color: rgba(17, 24, 39, 0.85);
    }
</style>
