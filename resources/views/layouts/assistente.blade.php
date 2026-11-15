<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#ea580c">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Oravel Mobile</title>

        {{-- PWA: instalavel como "Oravel Mobile", scope proprio (/assistente). --}}
        <link rel="manifest" href="{{ asset('manifest-assistente.json') }}">
        <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Oravel Mobile">
        <meta name="mobile-web-app-capable" content="yes">

        <script>
            // Tailwind darkMode: 'class' -- segue o tema do aparelho.
            try { if (matchMedia('(prefers-color-scheme: dark)').matches) document.documentElement.classList.add('dark'); } catch (e) {}
        </script>
        @vite(['resources/css/app.css'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-white dark:bg-gray-900 text-gray-900 dark:text-white overscroll-none">
        @yield('content')
        @livewireScripts
    </body>
</html>
