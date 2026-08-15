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

        @vite(['resources/css/app.css', 'resources/js/time-clock-offline.js'])
    </head>
    <body class="font-sans antialiased bg-zinc-950 text-zinc-100 overscroll-none">
    <div class="mx-auto flex min-h-screen max-w-md flex-col" x-data="timeClockOffline('{{ $employee->id }}')" x-init="init()">
        <header class="flex items-center gap-3 px-5 pb-2 pt-6">
            <div class="flex flex-1 items-center justify-between">
                <h1 class="text-xs font-bold tracking-widest text-zinc-400">PONTO ELETRÔNICO</h1>
                <span class="text-xs font-bold tracking-wide text-zinc-300">{{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
            </div>
        </header>

        {{-- Colaborador --}}
        <div class="px-5 pb-2">
            <div class="flex items-center gap-2 rounded-xl bg-zinc-900 px-3 py-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-orange-500/20 text-xs font-bold text-orange-400">
                    {{ strtoupper(substr($employee->name, 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold text-zinc-100">{{ $employee->name }}</p>
                    <p class="text-[10px] text-zinc-500">{{ $employee->role_title ?? 'Colaborador' }}</p>
                </div>
            </div>
        </div>

        {{-- Indicador de conexão / fila pendente --}}
        <div class="px-5 pb-2">
            <div
                class="flex items-center justify-between rounded-xl px-3 py-2 text-[11px] font-semibold"
                :class="isOnline ? 'bg-zinc-900 text-zinc-400' : 'bg-amber-950/40 text-amber-400'"
            >
                <span x-show="isOnline">
                    <span x-show="!syncing">🟢 Online</span>
                    <span x-show="syncing">🔄 Sincronizando...</span>
                </span>
                <span x-show="!isOnline">● Sem conexão — a batida fica salva no aparelho</span>

                <span x-show="pendingCount > 0" class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-400" x-text="pendingCount + ' pendente(s)'"></span>
            </div>
        </div>

        <main class="flex-1 space-y-3 px-5 pb-10 pt-4">
            <div x-show="saved" x-cloak class="rounded-xl bg-emerald-900/30 px-3 py-2 text-xs font-bold text-emerald-400">
                ✓ Batida registrada <span x-show="isOnline">e sincronizada</span><span x-show="!isOnline">— será enviada quando a conexão voltar</span>
            </div>

            <div class="rounded-2xl bg-zinc-900 p-4 text-center">
                <p class="text-[11px] text-zinc-500" x-text="new Date().toLocaleString('pt-BR')"></p>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <button
                    type="button"
                    @click="register('entrada')"
                    class="min-h-[4rem] w-full rounded-2xl bg-emerald-600 text-sm font-bold text-white active:bg-emerald-700"
                >
                    Entrada
                </button>
                <button
                    type="button"
                    @click="register('inicio_intervalo')"
                    class="min-h-[4rem] w-full rounded-2xl bg-amber-600 text-sm font-bold text-white active:bg-amber-700"
                >
                    Início do Intervalo
                </button>
                <button
                    type="button"
                    @click="register('fim_intervalo')"
                    class="min-h-[4rem] w-full rounded-2xl bg-amber-600 text-sm font-bold text-white active:bg-amber-700"
                >
                    Fim do Intervalo
                </button>
                <button
                    type="button"
                    @click="register('saida')"
                    class="min-h-[4rem] w-full rounded-2xl bg-red-600 text-sm font-bold text-white active:bg-red-700"
                >
                    Saída
                </button>
            </div>
        </main>
    </div>
    </body>
</html>
