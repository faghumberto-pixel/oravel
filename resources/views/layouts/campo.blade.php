<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f172a">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Oravel Campo')</title>
    <link rel="manifest" href="{{ asset('manifest-campo.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icon.png') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-900 font-sans text-white antialiased">
    <x-barra-oravel />
    <main class="mx-auto flex min-h-screen w-full max-w-md flex-col px-4 pb-8 pt-6">
        @yield('conteudo')
    </main>
</body>
</html>
