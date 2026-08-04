<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="theme-color" content="#09090b">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Registro de Horímetro - {{ config('app.name', 'Oravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/hour-meter-offline.js'])
    </head>
    <body class="font-sans antialiased bg-zinc-950 text-zinc-100 overscroll-none">
    <div class="mx-auto flex min-h-screen max-w-md flex-col" x-data="hourMeterOffline()" x-init="init()">
        <header class="flex items-center gap-3 px-5 pb-2 pt-6">
            <a
                href="{{ route('filament.admin.pages.technician-daily-tasks') }}"
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-zinc-700 text-zinc-300 active:bg-zinc-800"
                aria-label="Voltar"
            >
                ←
            </a>
            <div class="flex flex-1 items-center justify-between">
                <h1 class="text-xs font-bold tracking-widest text-zinc-400">REGISTRO DE HORÍMETRO</h1>
                <span class="text-xs font-bold tracking-wide text-zinc-300">{{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
            </div>
        </header>

        {{-- Técnico autenticado --}}
        <div class="px-5 pb-2">
            <div class="flex items-center gap-2 rounded-xl bg-zinc-900 px-3 py-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-orange-500/20 text-xs font-bold text-orange-400">
                    {{ strtoupper(substr($technicianName, 0, 1)) }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-xs font-semibold text-zinc-100">{{ $technicianName }}</p>
                    <p class="text-[10px] text-zinc-500">Técnico responsável pelo apontamento</p>
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
                <span x-show="!isOnline">● Sem conexão — os apontamentos ficam salvos no aparelho</span>

                <span x-show="pendingCount > 0" class="rounded-full bg-amber-500/20 px-2 py-0.5 text-amber-400" x-text="pendingCount + ' pendente(s)'"></span>
            </div>
        </div>

        <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-28">
            <template x-if="!asset">
                <div class="space-y-3">
                    {{-- Busca (contra o cache local -- funciona offline) --}}
                    <div class="rounded-2xl bg-zinc-900 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Buscar Equipamento</h3>
                        <p class="mt-1 text-[11px] text-zinc-500">Patrimônio, nome ou tag — busca funciona mesmo offline.</p>

                        <input
                            type="text"
                            x-model="query"
                            @input="search()"
                            placeholder="Ex: PAT-0001, Guindaste..."
                            class="mt-3 w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-orange-500"
                            autofocus
                        />

                        <p class="mt-2 text-[10px] text-zinc-600" x-show="assets.length === 0">
                            Nenhum equipamento em cache ainda — conecte à internet uma vez para pré-carregar a lista.
                        </p>
                    </div>

                    <template x-if="searchResults.length > 0">
                        <div class="rounded-2xl bg-zinc-900 p-4">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">
                                <span x-text="searchResults.length"></span> encontrados — toque para abrir
                            </h3>
                            <div class="mt-3 space-y-2">
                                <template x-for="result in searchResults" :key="result.id">
                                    <button
                                        type="button"
                                        @click="selectAsset(result.id)"
                                        class="min-h-[3rem] w-full rounded-xl border border-zinc-700 p-3 text-left active:bg-zinc-800"
                                    >
                                        <span class="block text-sm font-bold text-white" x-text="result.name"></span>
                                        <span class="block text-[11px] text-zinc-500">
                                            Patrimônio: <span x-text="result.patrimonio || '—'"></span>
                                            · Tag: <span x-text="result.tag || '—'"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <template x-if="asset">
                <div class="space-y-3">
                    {{-- Cabeçalho do equipamento --}}
                    <div class="rounded-2xl bg-zinc-900 p-4">
                        <h2 class="text-xl font-extrabold leading-tight text-white" x-text="asset.name"></h2>
                        <p class="mt-1 text-sm font-medium text-zinc-400">
                            PAT: <span x-text="asset.patrimonio || '—'"></span> · TAG: <span x-text="asset.tag || '—'"></span>
                        </p>
                        <p class="mt-1 text-[11px] text-zinc-500">
                            Cliente: <span x-text="asset.client_name || 'Disponível — sem cliente vinculado'"></span>
                        </p>
                        <button
                            type="button"
                            @click="clearAsset()"
                            class="mt-3 flex min-h-[2.75rem] w-full items-center justify-center rounded-xl border border-zinc-700 text-xs font-bold text-zinc-200 active:bg-zinc-800"
                        >
                            Trocar Equipamento
                        </button>
                    </div>

                    <div x-show="saved" x-cloak class="rounded-xl bg-emerald-900/30 px-3 py-2 text-xs font-bold text-emerald-400">
                        ✓ Apontamento salvo no aparelho <span x-show="isOnline">e sincronizado</span><span x-show="!isOnline">— será enviado quando a conexão voltar</span>
                    </div>

                    {{-- Form de apontamento --}}
                    <div class="rounded-2xl bg-zinc-900 p-4">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Apontamento de Horímetro</h3>

                        {{-- Último horímetro -- somente leitura, base de comparação --}}
                        <div class="mt-3 rounded-xl border border-zinc-800 bg-zinc-950/50 p-3">
                            <p class="text-[11px] text-zinc-500">Último horímetro registrado</p>
                            <template x-if="lastReading !== null && lastReading !== undefined">
                                <p class="mt-1 text-lg font-bold text-zinc-100">
                                    <span x-text="parseFloat(lastReading).toFixed(2)"></span>h
                                </p>
                            </template>
                            <template x-if="lastReading === null || lastReading === undefined">
                                <p class="mt-1 text-sm text-zinc-500">Nenhum apontamento anterior conhecido para este equipamento.</p>
                            </template>
                        </div>

                        {{-- Data/hora travada no momento da captura --}}
                        <p class="mt-3 text-[11px] font-semibold text-zinc-400">
                            🕐 Este apontamento será registrado em <span x-text="recordedAtLabel"></span>
                        </p>

                        <div class="mt-3 space-y-3">
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-zinc-400">Novo Horímetro (h)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    x-model="reading"
                                    placeholder="Ex: 1234.50"
                                    class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-orange-500"
                                />
                            </div>

                            {{-- Diferença calculada em tempo real, 100% client-side --}}
                            <div
                                x-show="difference !== null"
                                x-cloak
                                class="rounded-xl p-3"
                                :class="difference >= 0 ? 'border border-emerald-900 bg-emerald-950/30' : 'border border-red-900 bg-red-950/30'"
                            >
                                <p class="text-[11px] text-zinc-500">Diferença em relação ao último registro</p>
                                <p
                                    class="mt-0.5 text-base font-bold"
                                    :class="difference >= 0 ? 'text-emerald-400' : 'text-red-400'"
                                    x-text="(difference >= 0 ? '+' : '') + difference + 'h'"
                                ></p>
                            </div>

                            <label x-show="needsResetConfirm" x-cloak class="flex items-start gap-2 rounded-xl border border-amber-800 bg-amber-950/30 p-3">
                                <input type="checkbox" x-model="resetConfirmed" class="mt-0.5 rounded border-zinc-700 bg-zinc-800 text-amber-500 focus:ring-amber-500">
                                <span class="text-[11px] font-semibold text-amber-400">
                                    Leitura menor que a anterior, ou salto grande demais. Confirmo que este valor está correto (ex: reset do horímetro por troca de painel).
                                </span>
                            </label>

                            {{-- Foto de validação --}}
                            <template x-if="photoDataUrl">
                                <div class="relative h-32 w-full">
                                    <img :src="photoDataUrl" alt="Foto do painel" class="h-32 w-full rounded-xl object-cover">
                                    <button
                                        type="button"
                                        @click="removePhoto()"
                                        class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white hover:bg-red-600"
                                    >
                                        ✕
                                    </button>
                                </div>
                            </template>
                            <template x-if="!photoDataUrl">
                                <label class="flex min-h-[3rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-700 hover:border-zinc-600">
                                    <input type="file" accept="image/*" capture="environment" @change="onPhotoSelected($event)" class="hidden">
                                    <span class="text-xs font-semibold text-zinc-400">📷 Foto do painel (validação)</span>
                                </label>
                            </template>

                            <div>
                                <label class="mb-1 block text-[11px] font-semibold text-zinc-400">Observações</label>
                                <textarea
                                    x-model="notes"
                                    rows="2"
                                    placeholder="Observações (opcional)"
                                    class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-orange-500"
                                ></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <template x-if="asset">
            <footer class="sticky bottom-0 border-t border-zinc-800 bg-zinc-950 px-5 pb-4 pt-3">
                <button
                    type="button"
                    @click="save()"
                    class="min-h-[3.25rem] w-full rounded-xl bg-orange-500 text-sm font-bold text-white active:bg-orange-600"
                >
                    Salvar Apontamento
                </button>
            </footer>
        </template>
    </div>
    </body>
</html>
