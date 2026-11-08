<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Checklist do equipamento - {{ config('app.name', 'Oravel') }}</title>

        @include('partials.app-manifest')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- Estilo proprio e autonomo: nao depende de classes do build do Tailwind. --}}
        <style>
            body { margin: 0; background: #f1f5f9; }
        </style>
    </head>
    <body>
        {{ $slot }}
    </body>
</html>
