<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="theme-color" content="#09090b">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Checklist Digital - {{ config('app.name', 'Oravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Desenhado mobile-first (max-w-md nas telas internas) -- em telas
         largas (notebook/desktop), o body vira uma moldura neutra ao redor
         em vez de esticar o conteudo, e a tela de celular fica centralizada
         com sombra/borda pra deixar claro que e' uma area de celular, nao
         a pagina inteira. --}}
    <body class="h-screen font-sans antialiased bg-zinc-950 text-zinc-100 overscroll-none md:flex md:h-screen md:items-center md:justify-center md:bg-zinc-900 md:py-6">
        <div class="relative h-full md:mx-auto md:h-[844px] md:w-[390px] md:overflow-hidden md:rounded-[2rem] md:border md:border-zinc-800 md:shadow-2xl">
            {{ $slot }}
        </div>
    </body>
</html>
