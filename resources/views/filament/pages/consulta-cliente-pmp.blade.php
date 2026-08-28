<x-filament-panels::page>
    @php
        $categoryMeta = [
            'atrasada' => ['label' => 'Atrasada', 'color' => 'danger'],
            'pendente' => ['label' => 'Pendente', 'color' => 'warning'],
            'em_andamento' => ['label' => 'Em Andamento', 'color' => 'info'],
            'programada' => ['label' => 'Programada', 'color' => 'primary'],
            'concluida' => ['label' => 'Concluída', 'color' => 'success'],
        ];
        $rows = $this->clientId ? $this->maintenanceRows : collect();
    @endphp

    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="max-w-xs w-full">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
                <select wire:model.live="clientId" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm">
                    <option value="">Selecione um cliente...</option>
                    @foreach($this->clients as $client)
                        <option value="{{ $client->id }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>

            @if($this->clientId)
                <div class="max-w-xs w-full">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Equipamento</label>
                    <select wire:model.live="filterAssetId" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm">
                        <option value="">Todos os equipamentos</option>
                        @foreach($this->filterAssetOptions as $assetId => $assetName)
                            <option value="{{ $assetId }}">{{ $assetName }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="max-w-xs w-full">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select wire:model.live="filterStatus" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm">
                        <option value="">Todos os status</option>
                        @foreach($categoryMeta as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="max-w-xs w-full">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Técnico</label>
                    <select wire:model.live="filterTechnicianId" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm">
                        <option value="">Todos os técnicos</option>
                        @foreach($this->filterTechnicianOptions as $techId => $techName)
                            <option value="{{ $techId }}">{{ $techName }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        @if(! $this->clientId)
            <x-filament::section>
                <p class="text-sm text-gray-500">Selecione um cliente para ver as manutenções previstas dos ativos alugados por ele.</p>
            </x-filament::section>
        @elseif($rows->isEmpty())
            <x-filament::section>
                <p class="text-sm text-gray-500">Nenhum resultado para os filtros selecionados.</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::badge color="gray">{{ $rows->count() }}</x-filament::badge>
                        <span>Manutenções</span>
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b dark:border-gray-700">
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 pr-4">Equipamento</th>
                                <th class="py-2 pr-4">Patrimônio</th>
                                <th class="py-2 pr-4">Local</th>
                                <th class="py-2 pr-4">Plano</th>
                                <th class="py-2 pr-4">Técnico</th>
                                <th class="py-2 pr-4">OS</th>
                                <th class="py-2 pr-4">Última manutenção</th>
                                <th class="py-2 pr-4">Próximas datas previstas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                @php $meta = $categoryMeta[$row['category']]; @endphp
                                <tr class="border-b last:border-0 dark:border-gray-800">
                                    <td class="py-2 pr-4">
                                        <x-filament::badge :color="$meta['color']">{{ $meta['label'] }}</x-filament::badge>
                                    </td>
                                    <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['asset']->name }}</td>
                                    <td class="py-2 pr-4 font-mono text-gray-600 dark:text-gray-400">{{ $row['asset']->patrimonio ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                        {{ $row['location']['label'] ?? '—' }}
                                        @if(!empty($row['location']['city']))
                                            <span class="block text-xs text-gray-400">{{ $row['location']['city'] }}@if(!empty($row['location']['uf'])) / {{ $row['location']['uf'] }}@endif</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $row['plan']->name }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $row['order']?->technician?->name ?? 'Sem técnico' }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400 font-mono">{{ $row['order']?->os_number ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $row['last_completed_at']?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">
                                        @forelse($row['projections'] as $projection)
                                            <span class="inline-block mr-2">{{ $projection['month_label'] }} ({{ $projection['reason'] }})</span>
                                        @empty
                                            —
                                        @endforelse
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
