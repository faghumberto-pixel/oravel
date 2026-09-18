<x-filament-panels::page>
    {{-- Header Widgets (Stats) --}}
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
        />
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Filtros</h3>
        <form wire:submit="submit" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2">Data Inicial</label>
                <input type="date" wire:model="dateStart" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Data Final</label>
                <input type="date" wire:model="dateEnd" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Tipo</label>
                <select wire:model="type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                    <option value="">Todos</option>
                    <option value="AR">Contas a Receber</option>
                    <option value="AP">Contas a Pagar</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Status</label>
                <select wire:model="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                    <option value="">Todos</option>
                    <option value="pendente">Pendente</option>
                    <option value="atrasado">Atrasado</option>
                    <option value="pago">Pago</option>
                </select>
            </div>
        </form>
    </div>

    {{-- Chart --}}
    <div class="grid gap-6">
        @livewire(\App\Filament\Widgets\CashflowAccumulatedChart::class,
            [
                'dateStart' => $this->dateStart,
                'dateEnd' => $this->dateEnd,
                'status' => $this->status,
                'type' => $this->type,
            ],
            key('cashflow-chart-' . $this->dateStart . $this->dateEnd . $this->status . $this->type)
        )
    </div>

    {{-- Detail Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Detalhes</h3>

        @php
            $records = $this->getDetailRecords();
        @endphp

        @if($records->isEmpty())
            <p class="text-gray-500 dark:text-gray-400 text-center py-8">Nenhum registro encontrado</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 dark:border-gray-700">
                        <tr class="bg-gray-50 dark:bg-gray-700">
                            <th class="px-4 py-3 text-left font-semibold">Data</th>
                            <th class="px-4 py-3 text-left font-semibold">Cliente</th>
                            <th class="px-4 py-3 text-left font-semibold">Descrição</th>
                            <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                            <th class="px-4 py-3 text-right font-semibold">Valor</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($records as $record)
                            @php
                                $statusColor = match($record->status) {
                                    'pago' => 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400',
                                    'atrasado' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400',
                                    'pendente' => 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400',
                                    default => 'bg-gray-50 dark:bg-gray-900/20 text-gray-700 dark:text-gray-400',
                                };
                                $typeLabel = $record->type === 'AR' ? 'Receber' : 'Pagar';
                                $typeBg = $record->type === 'AR' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' : 'bg-orange-50 dark:bg-orange-900/20 text-orange-700 dark:text-orange-400';
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($record->date)->format('d/m/Y') }}</td>
                                <td class="px-4 py-3">
                                    @if($record->type === 'AR' && $record->client_id)
                                        @php $client = \App\Models\Client::find($record->client_id) @endphp
                                        {{ $client?->name ?? '-' }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $record->description }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $typeBg }}">
                                        {{ $typeLabel }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    R$ {{ number_format($record->amount, 2, ',', '.') }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $statusColor }}">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Total: {{ $records->count() }} registro(s)
            </div>
        @endif
    </div>
</x-filament-panels::page>
