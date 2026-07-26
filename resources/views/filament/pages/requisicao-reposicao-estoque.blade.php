<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex items-end gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Unidade (Matriz/Filial)</label>
                <select wire:model.live="internalUnitId" class="fi-input rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                    @foreach ($this->internalUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->lowStockRows->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                Nenhum material abaixo do estoque mínimo nesta unidade.
            </div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                        <tr>
                            <th class="p-3"></th>
                            <th class="p-3">SKU</th>
                            <th class="p-3">Material</th>
                            <th class="p-3">Atual</th>
                            <th class="p-3">Mínimo</th>
                            <th class="p-3">Qtd. a repor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($this->lowStockRows as $stock)
                            <tr>
                                <td class="p-3">
                                    <input type="checkbox" wire:model.live="selections.{{ $stock->material_id }}.selected">
                                </td>
                                <td class="p-3">{{ $stock->material->sku }}</td>
                                <td class="p-3 font-medium text-gray-900 dark:text-white">{{ $stock->material->name }}</td>
                                <td class="p-3">{{ $stock->current_quantity }}</td>
                                <td class="p-3">{{ $stock->minimum_threshold }}</td>
                                <td class="p-3">
                                    <input type="number" min="1" wire:model.live="selections.{{ $stock->material_id }}.quantity" class="fi-input w-24 rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <x-filament::button wire:click="gerarRequisicao">
                    Gerar Requisição de Compra
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
