<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="theme-color" content="#09090b">

        <title>Ponto Eletrônico - {{ config('app.name', 'Oravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased bg-zinc-50 text-zinc-900 overscroll-none md:bg-white md:flex md:min-h-screen md:items-center md:justify-center md:py-6">
        <div class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center px-6 text-center md:h-[844px] md:min-h-0 md:w-[390px] md:overflow-y-auto md:rounded-[2rem] md:border md:border-zinc-200 md:shadow-2xl">
            <span class="mb-4 text-4xl">🗂️</span>
            <h1 class="mb-2 text-lg font-bold text-zinc-900">Sem Ficha de RH ativa</h1>
            <p class="text-sm text-zinc-600">
                Sua conta ainda não tem uma Ficha de RH ativada, então não é possível bater ponto.
                Peça pro RH ativar em <strong>Colaboradores → Ativar ficha de RH</strong>.
            </p>
            <a href="{{ request()->attributes->get('colab') ? route('colab.home') : route('filament.admin.pages.app-colaborador') }}" class="mt-6 rounded-xl bg-zinc-100 px-5 py-3 text-sm font-bold text-zinc-900 active:bg-zinc-200">
                ← Voltar
            </a>
        </div>
    </body>
</html>
