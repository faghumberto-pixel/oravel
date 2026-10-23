<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#2563eb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Oravel Colaborador')</title>
    <meta name="colab-base" content="{{ rtrim(route('colab.home', [], false), '/') }}">
    <link rel="manifest" href="{{ route('colab.manifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Faixa de "sem conexao / aguardando envio" (colab-offline.js) fica acima da barra de icones --}}
    @unless (View::hasSection('sem_nav') || ! auth()->check())
        <style>:root{--co-bar-bottom:5rem}</style>
    @endunless
</head>
<body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased overscroll-none">
    {{-- Padrao visual dos apps de campo: cabecalho azul (so nas telas que informam @section('tela')) --}}
    @hasSection('tela')
        <header class="mx-auto w-full max-w-md rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-7 pt-6 text-white">
            @hasSection('voltar')
                <a href="@yield('voltar')" class="mb-3 inline-block text-sm font-semibold text-blue-100">← @yield('voltar_rotulo', 'Voltar')</a>
            @endif
            <h1 class="text-2xl font-bold">@yield('tela')</h1>
            @hasSection('tela_sub')
                <p class="mt-1 text-sm text-blue-100">@yield('tela_sub')</p>
            @endif
        </header>
    @endif

    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col p-5 @unless (View::hasSection('sem_nav') || ! auth()->check()) pb-28 @endunless">
        @yield('conteudo')
    </main>

    @unless (View::hasSection('sem_nav') || ! auth()->check())
        <x-colab.bottom-nav />
    @endunless

    {{-- Trabalho offline: pacote de dados, fila de envio e telas de consulta/execução (versão no endereço = cache novo a cada mudança) --}}
    <script src="/js/colab-offline.js?v={{ @filemtime(public_path('js/colab-offline.js')) }}" defer></script>
    <script>
        // App aberto sem internet: o service worker guarda as telas visitadas (ver App\Support\Colaborador\ServiceWorker).
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@json(route('colab.sw', [], false))).catch(() => {}));
        }
    </script>
</body>
</html>
