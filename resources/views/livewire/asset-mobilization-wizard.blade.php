{{-- Wizard de Mobilização: 5 etapas para remover equipamento do pátio --}}

<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-950 md:h-full md:min-h-0 md:overflow-y-auto" x-data="{
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
}" @livewire:navigating="initSignature()">
    {{-- Cabeçalho fixo --}}
    <header class="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-900 px-5 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xs font-bold tracking-widest text-zinc-400">MOBILIZAÇÃO</h1>
                <p class="mt-1 text-sm font-semibold text-zinc-100">
                    @if ($asset)
                        {{ $asset->name }}
                    @else
                        Selecione um equipamento
                    @endif
                </p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-800 text-xs font-bold text-zinc-300">
                {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
            </div>
        </div>

        {{-- Progresso --}}
        <div class="mt-3">
            <div class="flex items-baseline justify-between mb-2">
                <span class="text-[11px] font-bold tracking-widest text-emerald-400">
                    ETAPA {{ $step }} DE {{ $this->totalSteps }}
                </span>
                <span class="text-[11px] font-semibold uppercase text-zinc-500">{{ $this->stepLabel }}</span>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-800">
                <div class="h-2 rounded-full bg-emerald-500 transition-all duration-300"
                     style="width: {{ $this->stepProgress }}%"></div>
            </div>
        </div>
    </header>

    {{-- Conteúdo por etapa --}}
    <main class="flex-1 overflow-y-auto">
        @if ($step === 1)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">
                    📱 Scan QR/Etiqueta do Ativo
                </label>
                <p class="text-[11px] text-zinc-500">
                    Leia a etiqueta do equipamento para confirmar a identidade.
                    Ou busque manualmente pelo patrimônio/nome.
                </p>

                {{-- Busca manual --}}
                <div class="mt-4">
                    <input type="text"
                           wire:model.live="assetSearch"
                           placeholder="Patrimônio ou nome do ativo..."
                           class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500">
                </div>

                {{-- Equipamento selecionado --}}
                @if ($asset)
                    <div class="mt-4 rounded-xl border border-emerald-900 bg-emerald-950/20 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-400">
                            ✓ Equipamento Selecionado
                        </p>

                        <div class="mt-3 space-y-2">
                            <p class="font-semibold text-zinc-100">{{ $asset->name }}</p>
                            <p class="text-sm text-zinc-400">
                                PAT. {{ $asset->patrimonio ?? '—' }}
                            </p>

                            @if ($contract)
                                <div class="mt-3 rounded-lg bg-zinc-800 p-3">
                                    <p class="text-[11px] font-semibold text-zinc-400">Contrato</p>
                                    <p class="mt-1 text-sm font-semibold text-zinc-100">
                                        {{ $contract->client?->name }}
                                    </p>
                                    <p class="text-[11px] text-zinc-500">
                                        Nº {{ $contract->number }} • CEP: {{ $contract->client?->cep }}
                                    </p>
                                </div>
                            @else
                                <p class="text-[11px] text-amber-400">
                                    ⚠️ Nenhum contrato ativo encontrado para este ativo.
                                </p>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

        @elseif ($step === 2)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">
                    📋 Checklist de Itens e Acessórios
                </label>
                <p class="text-[11px] text-zinc-500">
                    Verifique cada item: presente, ausente ou danificado.
                </p>

                {{-- Lista de itens --}}
                <div class="mt-4 space-y-2">
                    @forelse ($checklistItems as $itemId => $item)
                        <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-3">
                            <div class="flex items-center justify-between">
                                <p class="font-semibold text-zinc-100">{{ $item['name'] }}</p>
                                <button wire:click="removeChecklistItem('{{ $itemId }}')"
                                        type="button"
                                        class="text-xs text-red-400 hover:text-red-300">
                                    ✕
                                </button>
                            </div>

                            {{-- Estados do item --}}
                            <div class="mt-3 flex gap-2">
                                <button wire:click="toggleItemState('{{ $itemId }}')"
                                        type="button"
                                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                        :class="'{{ $item['state'] }}' === 'present' ? 'bg-emerald-600 text-zinc-950' : 'bg-emerald-900/30 text-emerald-400'">
                                    ✓ Presente
                                </button>
                                <button wire:click="toggleItemState('{{ $itemId }}')"
                                        type="button"
                                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                        :class="'{{ $item['state'] }}' === 'absent' ? 'bg-yellow-600 text-zinc-950' : 'bg-yellow-900/30 text-yellow-400'">
                                    — Ausente
                                </button>
                                <button wire:click="toggleItemState('{{ $itemId }}')"
                                        type="button"
                                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                        :class="'{{ $item['state'] }}' === 'damaged' ? 'bg-red-600 text-zinc-950' : 'bg-red-900/30 text-red-400'">
                                    ⚠️ Danificado
                                </button>
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-[11px] text-zinc-500">Nenhum item adicionado ainda.</p>
                    @endforelse

                    <button wire:click="addChecklistItem"
                            type="button"
                            class="w-full rounded-lg border border-dashed border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-400 hover:bg-zinc-800">
                        + Adicionar Item
                    </button>
                </div>
            </div>

        @elseif ($step === 3)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">
                    📸 Estado Físico do Equipamento
                </label>
                <p class="text-[11px] text-zinc-500">
                    Registre horímetro, combustível e tire fotos em 3 ângulos.
                </p>

                {{-- Horímetro --}}
                <div class="mt-4">
                    <label class="text-xs font-semibold text-zinc-300">Horímetro Inicial</label>
                    <input type="number"
                           wire:model="horimetroStart"
                           placeholder="0"
                           step="0.01"
                           min="0"
                           class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 focus:ring-2 focus:ring-emerald-500">
                </div>

                {{-- Combustível --}}
                <div class="mt-3">
                    <label class="text-xs font-semibold text-zinc-300">Nível de Combustível</label>
                    <div class="mt-2 flex gap-2">
                        <button wire:click="$set('fuelLevelStart', 'empty')" type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                :class="$fuelLevelStart === 'empty' ? 'bg-red-600 text-zinc-950' : 'bg-red-900/30 text-red-400'">
                                Vazio
                        </button>
                        <button wire:click="$set('fuelLevelStart', 'half')" type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                :class="$fuelLevelStart === 'half' ? 'bg-yellow-600 text-zinc-950' : 'bg-yellow-900/30 text-yellow-400'">
                                Meio
                        </button>
                        <button wire:click="$set('fuelLevelStart', 'full')" type="button"
                                class="flex-1 rounded-lg px-2 py-2 text-xs font-bold"
                                :class="$fuelLevelStart === 'full' ? 'bg-emerald-600 text-zinc-950' : 'bg-emerald-900/30 text-emerald-400'">
                                Cheio
                        </button>
                    </div>
                </div>

                {{-- Observações --}}
                <div class="mt-3">
                    <label class="text-xs font-semibold text-zinc-300">Observações</label>
                    <textarea wire:model="observations"
                              placeholder="Observações sobre o estado..."
                              rows="3"
                              class="mt-1 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                {{-- Fotos obrigatórias --}}
                <div class="mt-4 rounded-lg border border-zinc-800 bg-zinc-900 p-3">
                    <p class="text-xs font-semibold text-zinc-300">📸 Fotos Obrigatórias</p>
                    <p class="mt-1 text-[11px] text-zinc-500">Tire fotos da frente, trás e lateral do equipamento.</p>

                    <div class="mt-3 space-y-2">
                        <div class="rounded-lg bg-zinc-800 p-2">
                            <input type="file" wire:model="photoFront" accept="image/*" capture="environment" class="text-xs text-zinc-400">
                            @if ($photoFront) <p class="mt-1 text-[10px] text-emerald-400">✓ Frente capturada</p> @endif
                        </div>
                        <div class="rounded-lg bg-zinc-800 p-2">
                            <input type="file" wire:model="photoBack" accept="image/*" capture="environment" class="text-xs text-zinc-400">
                            @if ($photoBack) <p class="mt-1 text-[10px] text-emerald-400">✓ Trás capturada</p> @endif
                        </div>
                        <div class="rounded-lg bg-zinc-800 p-2">
                            <input type="file" wire:model="photoSide" accept="image/*" capture="environment" class="text-xs text-zinc-400">
                            @if ($photoSide) <p class="mt-1 text-[10px] text-emerald-400">✓ Lateral capturada</p> @endif
                        </div>
                    </div>
                </div>
            </div>

        @elseif ($step === 4)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">
                    ✓ Confirmação de Saída
                </label>

                {{-- Resumo --}}
                <div class="mt-4 space-y-3">
                    <div class="rounded-lg border border-emerald-900 bg-emerald-950/20 p-4">
                        <p class="text-xs font-bold text-emerald-400">Equipamento</p>
                        <p class="mt-2 text-sm font-semibold text-zinc-100">{{ $asset?->name }}</p>
                        <p class="text-[11px] text-zinc-400">PAT. {{ $asset?->patrimonio }}</p>
                        <p class="text-[11px] text-zinc-500 mt-1">Horímetro: {{ number_format($horimetroStart ?? 0, 2, ',', '.') }}</p>
                    </div>

                    <div class="rounded-lg border border-blue-900 bg-blue-950/20 p-4">
                        <p class="text-xs font-bold text-blue-400">Contrato / Cliente</p>
                        <p class="mt-2 text-sm font-semibold text-zinc-100">{{ $contract?->client?->name }}</p>
                        <p class="text-[11px] text-zinc-400">Nº {{ $contract?->number }}</p>
                        <p class="text-[11px] text-zinc-500">CEP: {{ $contract?->client?->cep }}</p>
                    </div>

                    <div class="rounded-lg border border-zinc-800 bg-zinc-900 p-4">
                        <p class="text-xs font-bold text-zinc-400">Itens Verificados</p>
                        <p class="mt-2 text-sm text-zinc-100">{{ count($checklistItems) }} item(ns) registrado(s)</p>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-900 bg-amber-950/20 px-4 py-3">
                    <p class="text-xs font-semibold text-amber-400">⚠️ Após confirmar, próxima etapa é assinatura do cliente.</p>
                </div>
            </div>

        @elseif ($step === 5)
            <div class="space-y-3 px-5 pb-4 pt-4">
                <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">
                    ✍️ Assinatura de Recebimento
                </label>
                <p class="text-[11px] text-zinc-500">
                    O cliente confirma o recebimento do equipamento assinando abaixo.
                </p>

                {{-- Canvas de assinatura --}}
                <div class="mt-4 rounded-xl border-2 border-zinc-700 bg-white" style="height: 120px;">
                    <canvas id="signaturePad" class="w-full h-full" x-init="initSignature()"></canvas>
                </div>

                <button type="button"
                        @click="clearSignaturePad(); @entangle('signature').defer = null"
                        class="w-full rounded-xl border border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-400 hover:bg-zinc-800">
                    Limpar assinatura
                </button>

                <div class="rounded-lg border border-amber-900 bg-amber-950/20 px-4 py-3">
                    <p class="text-xs font-semibold text-amber-400">⚠️ Ao finalizar, o ativo será marcado como EM LOCAÇÃO.</p>
                </div>
            </div>
        @endif
    </main>

    {{-- Rodapé fixo --}}
    <footer class="sticky bottom-0 border-t border-zinc-800 bg-zinc-900 px-5 pb-4 pt-3">
        <div class="mb-2 min-h-[1.25rem] text-[11px] font-semibold">
            @if ($saveState === 'error')
                <button type="button" wire:click="$emit('retry')" class="text-left text-red-400 underline">
                    Não foi possível salvar. Toque para tentar de novo.
                </button>
            @elseif ($saveState === 'saved')
                <span class="text-emerald-400">✓ Salvo</span>
            @endif
            <span wire:loading wire:target="next,back" class="text-zinc-400">Salvando...</span>
        </div>

        <div class="flex gap-3">
            <button type="button" wire:click="back"
                    class="min-h-[3.25rem] flex-1 rounded-xl border border-zinc-700 bg-zinc-900 text-sm font-bold tracking-wide text-zinc-300">
                VOLTAR
            </button>
            <button type="button"
                    @click="if ($step === 5) { @entangle('signature').defer = getSignatureData(); }"
                    wire:click="next"
                    wire:loading.attr="disabled"
                    wire:target="next"
                    class="min-h-[3.25rem] flex-[1.4] rounded-xl bg-emerald-500 text-sm font-bold tracking-wide text-zinc-950 disabled:bg-zinc-800 disabled:text-zinc-600">
                {{ $step === 5 ? 'FINALIZAR' : 'CONTINUAR' }}
            </button>
        </div>
    </footer>
</div>

@pushOnce('scripts')
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
@endPushOnce
