<div class="space-y-4">
    {{-- Busca de materiais --}}
    <div class="rounded-2xl bg-zinc-900 p-4">
        <label class="text-xs font-bold uppercase tracking-wide text-zinc-400">Adicionar Material</label>

        <div class="mt-3 space-y-2">
            <input
                type="text"
                wire:model.live="materialSearch"
                placeholder="Buscar por nome ou código..."
                class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500"
                autocomplete="off"
            />

            {{-- Resultados da busca --}}
            @if ($materialSearch && $this->materialSearchResults->isNotEmpty())
                <div class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-zinc-700 bg-zinc-800">
                    @foreach ($this->materialSearchResults as $material)
                        <button
                            type="button"
                            wire:click="selectMaterial({{ $material->id }})"
                            class="w-full border-b border-zinc-700 px-3 py-2 text-left text-xs hover:bg-zinc-700 last:border-b-0"
                        >
                            <span class="block font-semibold text-zinc-100">{{ $material->name }}</span>
                            <span class="text-[11px] text-zinc-500">
                                SKU: {{ $material->sku ?? '—' }} · R$ {{ number_format($material->price, 2, ',', '.') }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @elseif ($materialSearch)
                <div class="rounded-xl border border-zinc-700 bg-zinc-800 px-3 py-2 text-center text-xs text-zinc-500">
                    Nenhum material encontrado
                </div>
            @endif
        </div>
    </div>

    {{-- Material selecionado - entrada de quantidade --}}
    @if ($selectedMaterialId)
        <div class="rounded-2xl border border-emerald-900 bg-emerald-950/20 p-4">
            <div class="mb-3 flex items-start justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-400">Material Selecionado</p>
                    <p class="mt-1 text-sm font-semibold text-zinc-100">{{ $this->selectedMaterial?->name }}</p>
                    <p class="text-[11px] text-zinc-500">
                        Unitário: R$ {{ number_format($this->selectedMaterial?->price, 2, ',', '.') }}
                    </p>
                </div>
                <button
                    type="button"
                    wire:click="clearSelectedMaterial"
                    class="text-zinc-500 hover:text-zinc-300"
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
                    class="flex-1 rounded-xl border-0 bg-zinc-800 p-2 text-sm text-zinc-100 focus:ring-2 focus:ring-emerald-500"
                />
                <button
                    type="button"
                    wire:click="addMaterialToOrder"
                    class="rounded-xl bg-emerald-500 px-4 py-2 text-xs font-bold text-zinc-950 hover:bg-emerald-600"
                >
                    Adicionar
                </button>
            </div>

            @error('materialQuantity')
                <p class="mt-2 text-[11px] text-red-400">{{ $message }}</p>
            @enderror
        </div>
    @endif

    {{-- Lista de materiais adicionados --}}
    @if ($this->appliedMaterials->isNotEmpty())
        <div class="space-y-2">
            <div class="flex items-baseline justify-between px-1">
                <span class="text-xs font-bold uppercase tracking-wide text-zinc-400">Materiais Aplicados</span>
                <span class="text-[11px] font-semibold text-zinc-500">{{ $this->appliedMaterials->count() }} item(ns)</span>
            </div>

            @foreach ($this->appliedMaterials as $applied)
                <div class="flex items-center gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-zinc-100">{{ $applied->material->name }}</p>
                        <p class="text-[11px] text-zinc-500">
                            {{ $applied->quantity }}x @ R$ {{ number_format($applied->unit_price, 2, ',', '.') }} = R$ {{ number_format($applied->quantity * $applied->unit_price, 2, ',', '.') }}
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="removeMaterial({{ $applied->id }})"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-red-800 text-xs font-bold text-red-400 hover:bg-red-900/20"
                    >
                        ✕
                    </button>
                </div>
            @endforeach
        </div>

        {{-- Resumo de custos --}}
        <div class="rounded-2xl border border-zinc-700 bg-zinc-900 p-4">
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-zinc-400">Subtotal de Materiais:</span>
                    <span class="font-semibold text-zinc-100">R$ {{ number_format($this->materialCostTotal, 2, ',', '.') }}</span>
                </div>
                <div class="flex justify-between border-t border-zinc-800 pt-2">
                    <span class="text-xs font-bold uppercase tracking-wide text-zinc-400">Total da O.S.:</span>
                    <span class="text-lg font-bold text-emerald-400">R$ {{ number_format($this->orderTotalCost, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-zinc-700 px-4 py-8 text-center">
            <p class="text-sm text-zinc-500">Nenhum material adicionado ainda</p>
        </div>
    @endif
</div>
