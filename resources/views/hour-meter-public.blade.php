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

        @vite(['resources/css/app.css', 'resources/js/hour-meter-public.js'])
    </head>
    <body class="font-sans antialiased bg-zinc-950 text-zinc-100 overscroll-none">
    <div
        class="mx-auto flex min-h-screen max-w-md flex-col"
        x-data="hourMeterPublic('{{ $asset->hour_meter_public_token }}', {{ $lastReading !== null ? (float) $lastReading : 'null' }})"
        x-init="init()"
    >
        <header class="px-5 pb-2 pt-6">
            <h1 class="text-xs font-bold tracking-widest text-zinc-400">REGISTRO DE HORÍMETRO</h1>
            <span class="text-xs font-bold tracking-wide text-zinc-300">{{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
        </header>

        <div class="px-5 pb-2">
            <div class="rounded-xl bg-amber-950/40 px-3 py-2 text-[11px] font-semibold text-amber-400">
                📋 Equipamento locado — apontamento feito pelo responsável designado da empresa contratante.
            </div>
        </div>

        <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-8">
            {{-- Cabeçalho do equipamento --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h2 class="text-xl font-extrabold leading-tight text-white">{{ $asset->name }}</h2>
                <p class="mt-1 text-sm font-medium text-zinc-400">
                    PAT: {{ $asset->patrimonio ?? '—' }} · TAG: {{ $asset->tag ?? '—' }}
                </p>
                @if ($asset->client)
                    <p class="mt-1 text-[11px] text-zinc-500">Locado para: {{ $asset->client->name }}</p>
                @endif
            </div>

            <div x-show="saved" x-cloak class="rounded-xl bg-emerald-900/30 px-3 py-2 text-xs font-bold text-emerald-400">
                ✓ Apontamento registrado com sucesso. Obrigado!
            </div>

            <div x-show="error" x-cloak class="rounded-xl bg-red-950/40 px-3 py-2 text-xs font-bold text-red-400" x-text="error"></div>

            {{-- Form de apontamento --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Apontamento de Horímetro</h3>

                {{-- Último horímetro -- somente leitura, base de comparação --}}
                <div class="mt-3 rounded-xl border border-zinc-800 bg-zinc-950/50 p-3">
                    <p class="text-[11px] text-zinc-500">Último horímetro registrado</p>
                    @if ($lastReading !== null)
                        <p class="mt-1 text-lg font-bold text-zinc-100">{{ number_format((float) $lastReading, 2, ',', '.') }}h</p>
                    @else
                        <p class="mt-1 text-sm text-zinc-500">Nenhum apontamento anterior conhecido para este equipamento.</p>
                    @endif
                </div>

                <p class="mt-3 text-[11px] font-semibold text-zinc-400">
                    🕐 Este apontamento será registrado em <span x-text="recordedAtLabel"></span>
                </p>

                <div class="mt-3 space-y-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold text-zinc-400">Seu Nome Completo</label>
                        <input
                            type="text"
                            x-model="name"
                            placeholder="Nome de quem está registrando"
                            class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-base text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-orange-500"
                        />
                        <p class="mt-1 text-[10px] text-zinc-600">Fica registrado como responsável por este apontamento.</p>
                    </div>

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
        </main>

        <footer class="sticky bottom-0 border-t border-zinc-800 bg-zinc-950 px-5 pb-4 pt-3">
            <button
                type="button"
                @click="submit()"
                :disabled="submitting"
                class="min-h-[3.25rem] w-full rounded-xl bg-orange-500 text-sm font-bold text-white active:bg-orange-600 disabled:opacity-50"
            >
                <span x-show="!submitting">Salvar Apontamento</span>
                <span x-show="submitting">Enviando...</span>
            </button>
        </footer>
    </div>
    </body>
</html>
