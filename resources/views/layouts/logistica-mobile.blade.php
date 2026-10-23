<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="theme-color" content="#2563eb">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Logística - {{ config('app.name', 'Oravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @include('partials.app-manifest')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Área de Logística (portaria/despacho de veículos): moldura própria, azul-marinho, separada das telas
         do técnico (checklist-mobile, zinc). Mesma moldura de celular no desktop. --}}
    <body class="h-screen font-sans antialiased bg-zinc-50 text-zinc-900 overscroll-none md:flex md:h-screen md:items-center md:justify-center md:bg-zinc-200 md:py-6">
        <div class="relative flex h-full flex-col md:mx-auto md:h-[844px] md:w-[390px] md:overflow-hidden md:rounded-[2rem] md:border md:border-zinc-300 md:shadow-2xl">
            <x-barra-oravel />
            <div class="relative min-h-0 flex-1 overflow-y-auto">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
