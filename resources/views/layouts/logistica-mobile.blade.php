<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="theme-color" content="#0c1a2e">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Logística - {{ config('app.name', 'Oravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @include('partials.app-manifest')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Área de Logística (portaria/despacho de veículos): moldura própria, azul-marinho, separada das telas
         do técnico (checklist-mobile, zinc). Mesma moldura de celular no desktop. --}}
    <body class="h-screen font-sans antialiased bg-slate-950 text-slate-100 overscroll-none md:flex md:h-screen md:items-center md:justify-center md:bg-slate-900 md:py-6">
        <div class="relative h-full md:mx-auto md:h-[844px] md:w-[390px] md:overflow-hidden md:rounded-[2rem] md:border md:border-slate-800 md:shadow-2xl">
            {{ $slot }}
        </div>
    </body>
</html>
