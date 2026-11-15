@php($t = \App\Support\Assistente\Areas::tema($area ?? 'prospects'))
<!DOCTYPE html>
<html lang="pt-BR" style="color-scheme: light;">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="{{ $t['cor'] }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        {{-- Livewire descobre de qual app veio pelo Referer (sessao propria por app): precisa ir completo no mesmo site. --}}
        <meta name="referrer" content="same-origin">

        <title>{{ $t['nome'] }}</title>

        {{-- PWA: instalavel como "Oravel Mobile", scope proprio (/assistente). --}}
        <link rel="manifest" href="{{ asset($t['manifesto']) }}">
        <link rel="icon" type="image/png" href="{{ asset($t['icone']) }}">
        <link rel="apple-touch-icon" href="{{ asset($t['icone']) }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ $t['nome'] }}">
        <meta name="mobile-web-app-capable" content="yes">

        {{-- So tema claro: sem a classe "dark" e com color-scheme light, o Android nao
             escurece a tela por conta propria (campos ficavam com letra branca em fundo branco). --}}
        <meta name="color-scheme" content="light">
        @vite(['resources/css/app.css'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-white dark:bg-gray-900 text-gray-900 dark:text-white overscroll-none">
        @yield('content')
        @livewireScripts
    </body>
</html>
