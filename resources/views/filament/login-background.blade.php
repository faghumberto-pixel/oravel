<style>
    /* So afeta paginas "simples" (login, esqueci senha, etc.) -- nunca o painel autenticado. */
    .fi-simple-layout {
        background-image: linear-gradient(180deg, rgba(15, 15, 15, 0.55) 0%, rgba(15, 15, 15, 0.75) 100%), url('{{ asset('images/login-bg.jpg') }}');
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
