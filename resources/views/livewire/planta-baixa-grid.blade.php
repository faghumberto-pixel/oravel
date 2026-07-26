<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Unidade (Matriz/Filial)</label>
            <select wire:model.live="internalUnitId" class="fi-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                @foreach ($this->internalUnits as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>

        @if ($context === \App\Models\StorageLocation::CONTEXT_PATIO_ATIVOS)
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Cor por</label>
                <select wire:model.live="colorMode" class="fi-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="status">Status</option>
                    <option value="criticidade">Criticidade</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Status</label>
                <select wire:model.live="statusFilter" class="fi-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    <option value="">Todos</option>
                    <option value="disponivel">Disponível</option>
                    <option value="locado">Locado</option>
                    <option value="manutencao">Em Manutenção</option>
                    <option value="operando">Em Operação</option>
                    <option value="aguardando_triagem">Aguardando Triagem</option>
                    <option value="quarentena">Quarentena</option>
                    <option value="reservado">Reservado</option>
                </select>
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Capacidade mín.</label>
                <input type="number" wire:model.live.debounce.500ms="capacityMin" class="fi-input w-28 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Capacidade máx.</label>
                <input type="number" wire:model.live.debounce.500ms="capacityMax" class="fi-input w-28 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Patrimônio/Tag</label>
                <input type="text" wire:model.live.debounce.500ms="patrimonioSearch" class="fi-input w-36 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
            </div>
        @endif
    </div>

    @if ($this->locations->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            Nenhuma posição cadastrada nesta unidade ainda. Cadastre em Ativos e Materiais → Localizações (Planta Baixa).
        </div>
    @else
        @php
            $maxRow = $this->locations->max('row');
            $maxCol = $this->locations->max('column');
        @endphp

        <div
            class="grid gap-2 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
            style="grid-template-columns: repeat({{ $maxCol }}, minmax(3.5rem, 1fr)); grid-template-rows: repeat({{ $maxRow }}, 3.5rem);"
        >
            @foreach ($this->locations as $location)
                @php
                    $color = $this->cellColor($location->id);
                    $hex = $color === 'criticidade' ? $this->cellCriticalityHex($location->id) : null;
                    $count = ($this->occupantsByLocation[$location->id] ?? collect())->count();
                    $colorClasses = match ($color) {
                        'success' => 'bg-emerald-500/80 hover:bg-emerald-500 text-white',
                        'danger' => 'bg-red-500/80 hover:bg-red-500 text-white',
                        'warning' => 'bg-amber-500/80 hover:bg-amber-500 text-white',
                        'info' => 'bg-blue-500/80 hover:bg-blue-500 text-white',
                        'primary' => 'bg-orange-600/80 hover:bg-orange-600 text-white',
                        'purple' => 'bg-purple-500/80 hover:bg-purple-500 text-white',
                        'criticidade' => '',
                        default => 'bg-gray-200 hover:bg-gray-300 text-gray-500 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300',
                    };
                @endphp
                <button
                    type="button"
                    wire:click="selectLocation('{{ $location->id }}')"
                    style="grid-row: {{ $location->row }}; grid-column: {{ $location->column }}; {{ $hex ? 'background-color:'.$hex.'cc' : '' }}"
                    class="flex flex-col items-center justify-center rounded-lg text-xs font-semibold shadow-sm transition {{ $colorClasses }} {{ $selectedLocationId === $location->id ? 'ring-2 ring-offset-2 ring-gray-900 dark:ring-white' : '' }}"
                    title="{{ $location->code }}"
                >
                    <span>{{ $location->code }}</span>
                    @if ($count > 0)
                        <span class="text-[10px] opacity-90">{{ $count }} {{ $count === 1 ? 'item' : 'itens' }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    @if ($this->selectedLocation)
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-2 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ $this->selectedLocation->code }} @if ($this->selectedLocation->label) — {{ $this->selectedLocation->label }} @endif
                </h3>
                <button type="button" wire:click="closeModal" class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">Fechar</button>
            </div>

            @if ($this->selectedOccupants->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum item nesta posição.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($this->selectedOccupants as $occupant)
                        <li class="flex items-center justify-between py-2 text-sm">
                            @if ($context === \App\Models\StorageLocation::CONTEXT_ALMOXARIFADO)
                                <span class="font-medium text-gray-900 dark:text-white">{{ $occupant->name }} <span class="text-gray-400">({{ $occupant->sku }})</span></span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $occupant->locationStocks->first()?->current_quantity ?? 0 }} un.</span>
                            @else
                                <span class="font-medium text-gray-900 dark:text-white">{{ $occupant->patrimonio ?? $occupant->tag ?? $occupant->name }} — {{ $occupant->name }}</span>
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ $occupant->capacity_value ? rtrim(rtrim(number_format((float) $occupant->capacity_value, 2, ',', '.'), '0'), ',').' '.$occupant->capacity_unit : '' }}
                                    · {{ ucfirst(str_replace('_', ' ', $occupant->status)) }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
