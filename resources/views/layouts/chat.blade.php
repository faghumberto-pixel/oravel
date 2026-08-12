<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
        <meta name="theme-color" content="#101010">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Oravel Chat</title>

        {{-- PWA: instalável como "Oravel Chat", separado do painel principal
             (scope próprio evita que esse manifesto/SW controlem /admin). --}}
        <link rel="manifest" href="{{ asset('manifest-chat.json') }}">
        <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Oravel Chat">
        <meta name="mobile-web-app-capable" content="yes">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/chat-app.js'])
    </head>
    <body class="font-sans antialiased overscroll-none" style="background-color:#e9edef; color:#111b21; margin:0;">
        {{ $slot }}
    </body>
</html>
