<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#09090b">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Oravel Colaborador')</title>
    <link rel="manifest" href="{{ route('colab.manifest') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-zinc-950 font-sans text-zinc-100 antialiased overscroll-none">
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col p-5">
        @yield('conteudo')
    </main>
    <script>
        // App aberto sem internet: o service worker guarda as telas visitadas (ver App\Support\Colaborador\ServiceWorker).
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => navigator.serviceWorker.register(@json(route('colab.sw', [], false))).catch(() => {}));
        }
    </script>
</body>
</html>
