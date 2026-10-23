<div class="space-y-4">
    {{-- Busca de materiais --}}
    <div class="rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
        <label class="text-xs font-bold uppercase tracking-wide text-zinc-600">Adicionar Material</label>

        <div class="mt-3 space-y-2">
            <input
                type="text"
                wire:model.live="materialSearch"
                placeholder="Buscar por nome ou código..."
                class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-emerald-500"
                autocomplete="off"
            />

            {{-- Resultados da busca --}}
            @if ($materialSearch && $this->materialSearchResults->isNotEmpty())
                <div class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-zinc-300 bg-zinc-100">
                    @foreach ($this->materialSearchResults as $material)
                        <button
                            type="button"
                            wire:click="selectMaterial('{{ $material->id }}')"
                            class="w-full border-b border-zinc-300 px-3 py-2 text-left text-xs hover:bg-zinc-200 last:border-b-0"
                        >
                            <span class="block font-semibold text-zinc-900">{{ $material->name }}</span>
                            <span class="text-[11px] text-zinc-600">
                                SKU: {{ $material->sku ?? '—' }} · R$ {{ number_format($material->price, 2, ',', '.') }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @elseif ($materialSearch)
                <div class="rounded-xl border border-zinc-300 bg-zinc-100 px-3 py-2 text-center text-xs text-zinc-600">
                    Nenhum material encontrado
                </div>
            @endif
        </div>
    </div>

    {{-- Material selecionado - entrada de quantidade --}}
    @if ($selectedMaterialId)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="mb-3 flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">Material Selecionado</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-900">{{ $this->selectedMaterial?->name }}</p>
                    <p class="text-[11px] text-zinc-600">
                        Unitário: R$ {{ number_format($this->selectedMaterial?->price, 2, ',', '.') }}
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="clearSelectedMaterial"
                    class="text-zinc-600 hover:text-zinc-700"
                >
                    ✕
                </button>
            </div>

            <div class="flex gap-2">
                <input
                    type="number"
                    wire:model="materialQuantity"
                    placeholder="Quantidade"
                    min="1"
                    class="flex-1 rounded-xl border-0 bg-zinc-100 p-2 text-sm text-zinc-900 focus:ring-2 focus:ring-emerald-500"
                />
                <button
                    type="button"
                    wire:click="addMaterialToOrder"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-600"
                >
                    Adicionar
                </button>
            </div>

            @error('materialQuantity')
                <p class="mt-2 text-[11px] text-red-600">{{ $message }}</p>
            @enderror
        </div>
    @endif

    {{-- Lista de materiais adicionados --}}
    @if ($this->appliedMaterials->isNotEmpty())
        <div class="space-y-2">
            <div class="flex items-baseline justify-between px-1">
                <span class="text-xs font-bold uppercase tracking-wide text-zinc-600">Materiais Aplicados</span>
                <span class="text-[11px] font-semibold text-zinc-600">{{ $this->appliedMaterials->count() }} item(ns)</span>
            </div>

            @foreach ($this->appliedMaterials as $applied)
                <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-900">{{ $applied->material->name }}</p>
                        <p class="text-[11px] text-zinc-600">
                            {{ $applied->quantity }}x @ R$ {{ number_format($applied->unit_price, 2, ',', '.') }} = R$ {{ number_format($applied->quantity * $applied->unit_price, 2, ',', '.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="removeMaterial('{{ $applied->id }}')"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-red-200 text-xs font-bold text-red-600 hover:bg-red-50"
                    >
                        ✕
                    </button>
                </div>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center">
            <p class="text-sm text-zinc-600">Nenhum material adicionado ainda</p>
        </div>
    @endif

    {{-- Resumo de custos: sempre visivel (nao so' quando ha' material),
         porque a mao de obra corre desde o inicio do servico mesmo sem
         nenhum material aplicado ainda. --}}
    <div class="rounded-xl border border-zinc-300 bg-white p-4">
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-zinc-600">Mão de Obra (corrida):</span>
                <span class="font-semibold text-zinc-900">R$ {{ number_format($maintenanceOrder->labor_cost ?? 0, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-zinc-600">Subtotal de Materiais:</span>
                <span class="font-semibold text-zinc-900">R$ {{ number_format($this->materialCostTotal, 2, ',', '.') }}</span>
            </div>
            <div class="flex justify-between border-t border-zinc-200 pt-2">
                <span class="text-xs font-bold uppercase tracking-wide text-zinc-600">Total da O.S.:</span>
                <span class="text-lg font-bold text-emerald-700">R$ {{ number_format($this->orderTotalCost, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>
</div>
