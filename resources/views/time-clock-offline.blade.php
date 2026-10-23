<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="theme-color" content="#09090b">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Ponto Eletrônico - {{ config('app.name', 'Oravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @include('partials.app-manifest')
        @vite(['resources/css/app.css', 'resources/js/time-clock-offline.js'])
    </head>
    {{-- Mobile-first (max-w-md) -- em telas largas o body vira moldura
         neutra e o conteudo fica centralizado com formato de celular, em
         vez de esticar. --}}
    <body class="font-sans antialiased bg-zinc-50 text-zinc-900 overscroll-none md:bg-white md:flex md:min-h-screen md:items-center md:justify-center md:py-6">
    <div class="mx-auto flex min-h-screen max-w-md flex-col md:h-[844px] md:min-h-0 md:w-[390px] md:overflow-y-auto md:rounded-[2rem] md:border md:border-zinc-200 md:shadow-2xl" x-data="timeClockOffline('{{ $employee->id }}')" x-init="init()">
        <x-barra-oravel />
        <header class="mb-4 flex items-center gap-3 rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
            <a href="{{ request()->attributes->get('colab') ? route('colab.home') : route('filament.admin.pages.app-colaborador') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-white/40 text-white active:bg-white/25" aria-label="Voltar">←</a>
            <div class="flex flex-1 items-center justify-between">
                <h1 class="text-xs font-bold tracking-widest text-blue-100">PONTO ELETRÔNICO</h1>
                <span class="text-xs font-bold tracking-wide text-white">{{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
            </div>
        </header>

        {{-- Colaborador --}}
        <div class="px-5 pb-2">
            <div class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 border border-zinc-200 shadow-sm">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                    {{ strtoupper(substr($employee->name, 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold text-zinc-900">{{ $employee->name }}</p>
                    <p class="text-[10px] text-zinc-600">{{ $employee->role_title ?? 'Colaborador' }}</p>
                </div>
            </div>
        </div>

        {{-- Indicador de conexão / fila pendente --}}
        <div class="px-5 pb-2">
            <div
                class="flex items-center justify-between rounded-xl px-3 py-2 text-[11px] font-semibold"
                :class="isOnline ? 'bg-white text-zinc-600' : 'bg-amber-50 text-amber-700'"
            >
                <span x-show="isOnline">
                    <span x-show="!syncing">🟢 Online</span>
                    <span x-show="syncing">🔄 Sincronizando...</span>
                </span>
                <span x-show="!isOnline">● Sem conexão — a batida fica salva no aparelho</span>

                <span x-show="pendingCount > 0" class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700" x-text="pendingCount + ' pendente(s)'"></span>
            </div>
        </div>

        <main class="flex-1 space-y-3 px-5 pb-10 pt-4">
            <div x-show="saved" x-cloak class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-700">
                ✓ Batida registrada <span x-show="isOnline">e sincronizada</span><span x-show="!isOnline">— será enviada quando a conexão voltar</span>
            </div>

            <div class="rounded-xl bg-white p-4 text-center border border-zinc-200 shadow-sm">
                <p class="text-[11px] text-zinc-600" x-text="new Date().toLocaleString('pt-BR')"></p>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <button
                    type="button"
                    @click="register('entrada')"
                    class="min-h-[4rem] w-full rounded-xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-700"
                >
                    Entrada
                </button>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        @click="register('inicio_pausa')"
                        class="min-h-[4rem] w-full rounded-xl bg-amber-600 text-sm font-bold text-white active:bg-amber-700"
                    >
                        Início da Pausa
                    </button>
                    <button
                        type="button"
                        @click="register('fim_pausa')"
                        class="min-h-[4rem] w-full rounded-xl bg-amber-600 text-sm font-bold text-white active:bg-amber-700"
                    >
                        Fim da Pausa
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        @click="register('inicio_almoco')"
                        class="min-h-[4rem] w-full rounded-xl bg-sky-600 text-sm font-bold text-white active:bg-sky-700"
                    >
                        Início do Almoço
                    </button>
                    <button
                        type="button"
                        @click="register('fim_almoco')"
                        class="min-h-[4rem] w-full rounded-xl bg-sky-600 text-sm font-bold text-white active:bg-sky-700"
                    >
                        Fim do Almoço
                    </button>
                </div>
                <button
                    type="button"
                    @click="register('saida')"
                    class="min-h-[4rem] w-full rounded-xl bg-red-600 text-sm font-bold text-white active:bg-red-700"
                >
                    Saída
                </button>
            </div>
        </main>
    </div>
    </body>
</html>
