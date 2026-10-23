{{-- Wizard de Mobilização: 5 etapas para remover equipamento do pátio --}}

<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto" x-data="{
    signaturePad: null,
    initSignature() {
        const canvas = document.getElementById('signaturePad');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * window.devicePixelRatio;
        canvas.height = rect.height * window.devicePixelRatio;
        ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        this.signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: 'rgb(0, 0, 0)',
            throttle: 16,
        });
    },
    getSignatureData() {
        return this.signaturePad?.toDataURL('image/png') || null;
    },
    clearSignaturePad() {
        this.signaturePad?.clear();
    }
}" x-on:livewire:navigating="initSignature()">
    {{-- Cabeçalho fixo --}}
    <header class="sticky top-0 z-40 rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-5 text-white shadow-md">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xs font-bold tracking-widest text-blue-100">MOBILIZAÇÃO</h1>
                <p class="mt-1 text-sm font-semibold text-white">
                    @if ($asset)
                        {{ $asset->name }}
                    @else
                        Selecione um equipamento
                    @endif
                </p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/25 text-xs font-bold text-white">
                {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
            </div>
        </div>

        {{-- Progresso --}}
        <div class="mt-3">
            <div class="flex items-baseline justify-between mb-2">
                <span class="text-[11px] font-bold tracking-widest text-white">
                    ETAPA {{ $step }} DE {{ $this->totalSteps }}
                </span>
                <span class="text-[11px] font-semibold uppercase text-blue-100">{{ $this->stepLabel }}</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-white/25">
                <div class="h-2 rounded-full bg-white transition-all duration-300"
                     style="width: {{ $this->stepProgress }}%"></div>
            </div>
        </div>
    </header>

    {{-- Conteúdo por etapa --}}
    <main class="flex-1 overflow-y-auto">
        @if ($step === 1)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-600">
                    📱 Scan QR/Etiqueta do Ativo
                </label>
                <p class="text-[11px] text-zinc-600">
                    Leia a etiqueta do equipamento para confirmar a identidade.
                    Ou busque manualmente pelo patrimônio/nome.
                </p>

                <div class="mt-4">
                    <x-mobile.leitor-qr />
                    @if ($qrMensagem)
                        <p class="mt-2 rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-800">{{ $qrMensagem }}</p>
                    @endif
                </div>

                {{-- Busca manual --}}
                <div class="mt-4">
                    <input type="text"
                           wire:model.live.debounce.400ms="assetSearch"
                           placeholder="Patrimônio ou nome do ativo..."
                           class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-emerald-500">
                </div>

                @if (! $asset && ! empty($assetResults))
                    <div class="mt-2 space-y-2">
                        @foreach ($assetResults as $resultado)
                            <button type="button" wire:click="selectAsset('{{ $resultado['id'] }}')" wire:key="res-{{ $resultado['id'] }}"
                                    class="w-full rounded-xl border border-zinc-200 bg-white p-3 text-left shadow-sm active:bg-zinc-50">
                                <span class="block text-sm font-bold text-zinc-900">{{ $resultado['name'] }}</span>
                                <span class="block text-xs text-zinc-600">Patrimônio: {{ $resultado['patrimonio'] ?? '—' }}</span>
                            </button>
                        @endforeach
                    </div>
                @elseif (! $asset && mb_strlen(trim((string) $assetSearch)) >= 2)
                    <p class="mt-2 text-xs text-zinc-600">Nenhum equipamento encontrado.</p>
                @endif

                {{-- Equipamento selecionado --}}
                @if ($asset)
                    <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">
                            ✓ Equipamento Selecionado
                        </p>

                        <div class="mt-3 space-y-2">
                            <p class="font-semibold text-zinc-900">{{ $asset->name }}</p>
                            <p class="text-sm text-zinc-600">
                                PAT. {{ $asset->patrimonio ?? '—' }}
                            </p>

                            @if ($contract)
                                <div class="mt-3 rounded-lg bg-zinc-100 p-3">
                                    <p class="text-[11px] font-semibold text-zinc-600">Contrato</p>
                                    <p class="mt-1 text-sm font-semibold text-zinc-900">
                                        {{ $contract->client?->name }}
                                    </p>
                                    <p class="text-[11px] text-zinc-600">
                                        Nº {{ $contract->number }} • CEP: {{ $contract->client?->cep }}
                                    </p>
                                </div>
                            @else
                                <p class="text-[11px] text-amber-700">
                                    ⚠️ Nenhum contrato ativo encontrado para este ativo.
                                </p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

        @elseif ($step === 2)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-600">
                    📋 Checklist de Itens e Acessórios
                </label>
                <p class="text-[11px] text-zinc-600">
                    Verifique cada item: presente, ausente ou danificado.
                </p>

                {{-- Lista de itens --}}
                <div class="mt-4 space-y-2">
                    @forelse ($checklistItems as $itemId => $item)
                        <div class="rounded-lg border border-zinc-200 bg-white p-3">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-zinc-900">{{ $item['name'] }}</p>
                                <button wire:click="removeChecklistItem('{{ $itemId }}')"
                                        type="button"
                                        class="text-xs text-red-600 hover:text-red-600">
                                    ✕
                                </button>
                            </div>

                            {{-- Estados do item --}}
                            <div class="mt-3 flex gap-2">
                                <button wire:click="toggleItemState('{{ $itemId }}')"
                                        type="button"
                                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                        :class="'{{ $item['state'] }}' === 'present' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700'">
                                    ✓ Presente
                                </button>
                                <button wire:click="toggleItemState('{{ $itemId }}')"
                                        type="button"
                                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                        :class="'{{ $item['state'] }}' === 'absent' ? 'bg-yellow-600 text-white' : 'bg-yellow-50 text-yellow-700'">
                                    — Ausente
                                </button>
                                <button wire:click="toggleItemState('{{ $itemId }}')"
                                        type="button"
                                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                        :class="'{{ $item['state'] }}' === 'damaged' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-600'">
                                    ⚠️ Danificado
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-[11px] text-zinc-600">Nenhum item adicionado ainda.</p>
                    @endforelse

                    <button wire:click="addChecklistItem"
                            type="button"
                            class="w-full rounded-lg border border-dashed border-zinc-300 px-3 py-2 text-xs font-bold text-zinc-600 hover:bg-zinc-100">
                        + Adicionar Item
                    </button>
                </div>
            </div>

        @elseif ($step === 3)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-600">
                    📸 Estado Físico do Equipamento
                </label>
                <p class="text-[11px] text-zinc-600">
                    Registre horímetro, combustível e tire fotos em 3 ângulos.
                </p>

                {{-- Horímetro --}}
                <div class="mt-4">
                    <label class="text-xs font-semibold text-zinc-700">Horímetro Inicial</label>
                    <input type="number"
                           wire:model="horimetroStart"
                           placeholder="0"
                           step="0.01"
                           min="0"
                           class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-sm text-zinc-900 focus:ring-2 focus:ring-emerald-500">
                </div>

                {{-- Combustível --}}
                <div class="mt-3">
                    <label class="text-xs font-semibold text-zinc-700">Nível de Combustível</label>
                    <div class="mt-2 flex gap-2">
                        <button wire:click="$set('fuelLevelStart', 'empty')" type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                :class="$wire.fuelLevelStart === 'empty' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-600'">
                                Vazio
                        </button>
                        <button wire:click="$set('fuelLevelStart', 'half')" type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                :class="$wire.fuelLevelStart === 'half' ? 'bg-yellow-600 text-white' : 'bg-yellow-50 text-yellow-700'">
                                Meio
                        </button>
                        <button wire:click="$set('fuelLevelStart', 'full')" type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                :class="$wire.fuelLevelStart === 'full' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700'">
                                Cheio
                        </button>
                    </div>
                </div>

                {{-- Observações --}}
                <div class="mt-3">
                    <label class="text-xs font-semibold text-zinc-700">Observações</label>
                    <textarea wire:model="observations"
                              placeholder="Observações sobre o estado..."
                              rows="3"
                              class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                {{-- Fotos obrigatórias --}}
                <div class="mt-4 rounded-lg border border-zinc-200 bg-white p-3">
                    <p class="text-xs font-semibold text-zinc-700">📸 Fotos Obrigatórias</p>
                    <p class="mt-1 text-[11px] text-zinc-600">Tire fotos da frente, trás e lateral do equipamento.</p>

                    <div class="mt-3 space-y-2">
                        <div class="rounded-lg bg-zinc-100 p-2">
                            <input type="file" wire:model="photoFront" accept="image/*" capture="environment" class="text-xs text-zinc-600">
                            @if ($photoFront) <p class="mt-1 text-[10px] text-emerald-700">✓ Frente capturada</p> @endif
                        </div>
                        <div class="rounded-lg bg-zinc-100 p-2">
                            <input type="file" wire:model="photoBack" accept="image/*" capture="environment" class="text-xs text-zinc-600">
                            @if ($photoBack) <p class="mt-1 text-[10px] text-emerald-700">✓ Trás capturada</p> @endif
                        </div>
                        <div class="rounded-lg bg-zinc-100 p-2">
                            <input type="file" wire:model="photoSide" accept="image/*" capture="environment" class="text-xs text-zinc-600">
                            @if ($photoSide) <p class="mt-1 text-[10px] text-emerald-700">✓ Lateral capturada</p> @endif
                        </div>
                    </div>
                </div>
            </div>

        @elseif ($step === 4)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-600">
                    ✓ Confirmação de Saída
                </label>

                {{-- Resumo --}}
                <div class="mt-4 space-y-3">
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-bold text-emerald-700">Equipamento</p>
                        <p class="mt-2 text-sm font-semibold text-zinc-900">{{ $asset?->name }}</p>
                        <p class="text-[11px] text-zinc-600">PAT. {{ $asset?->patrimonio }}</p>
                        <p class="text-[11px] text-zinc-600 mt-1">Horímetro: {{ number_format($horimetroStart ?? 0, 2, ',', '.') }}</p>
                    </div>

                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                        <p class="text-xs font-bold text-blue-700">Contrato / Cliente</p>
                        <p class="mt-2 text-sm font-semibold text-zinc-900">{{ $contract?->client?->name }}</p>
                        <p class="text-[11px] text-zinc-600">Nº {{ $contract?->number }}</p>
                        <p class="text-[11px] text-zinc-600">CEP: {{ $contract?->client?->cep }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-white p-4">
                        <p class="text-xs font-bold text-zinc-600">Itens Verificados</p>
                        <p class="mt-2 text-sm text-zinc-900">{{ count($checklistItems) }} item(ns) registrado(s)</p>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold text-amber-700">⚠️ Após confirmar, próxima etapa é assinatura do cliente.</p>
                </div>
            </div>

        @elseif ($step === 5)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-600">
                    ✍️ Assinatura de Recebimento
                </label>
                <p class="text-[11px] text-zinc-600">
                    O cliente confirma o recebimento do equipamento assinando abaixo.
                </p>

                {{-- Canvas de assinatura --}}
                <div class="mt-4 rounded-xl border-2 border-zinc-300 bg-white" style="height: 120px;">
                    <canvas id="signaturePad" class="w-full h-full" x-init="initSignature()"></canvas>
                </div>

                <button type="button"
                        @click="clearSignaturePad(); @entangle('signature').defer = null"
                        class="w-full rounded-xl border border-zinc-300 px-3 py-2 text-xs font-bold text-zinc-600 hover:bg-zinc-100">
                    Limpar assinatura
                </button>

                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-semibold text-amber-700">⚠️ Ao finalizar, o ativo será marcado como EM LOCAÇÃO.</p>
                </div>
            </div>
        @endif
    </main>

    {{-- Rodapé fixo --}}
    <footer class="sticky bottom-0 border-t border-zinc-200 bg-white px-5 pb-4 pt-3">
        <div class="mb-2 min-h-[1.25rem] text-[11px] font-semibold">
            @if ($saveState === 'error')
                <button type="button" wire:click="$emit('retry')" class="text-left text-red-600 underline">
                    Não foi possível salvar. Toque para tentar de novo.
                </button>
            @elseif ($saveState === 'saved')
                <span class="text-emerald-700">✓ Salvo</span>
            @endif
            <span wire:loading wire:target="next,back" class="text-zinc-600">Salvando...</span>
        </div>

        <div class="flex gap-3">
            <button type="button" wire:click="back"
                    class="min-h-[3.25rem] flex-1 rounded-xl border border-zinc-300 bg-white text-sm font-bold tracking-wide text-zinc-700">
                VOLTAR
            </button>
            <button type="button"
                    @click="if ($step === 5) { @entangle('signature').defer = getSignatureData(); }"
                    wire:click="next"
                    wire:loading.attr="disabled"
                    wire:target="next"
                    class="min-h-[3.25rem] flex-[1.4] rounded-xl bg-emerald-600 text-sm font-bold tracking-wide text-white disabled:bg-zinc-100 disabled:text-zinc-500">
                {{ $step === 5 ? 'FINALIZAR' : 'CONTINUAR' }}
            </button>
        </div>
    </footer>
</div>

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
@endPushOnce
