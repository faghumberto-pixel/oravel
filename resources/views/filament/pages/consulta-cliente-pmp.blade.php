<x-filament-panels::page>
    @php
        $categories = [
            'atrasada' => ['label' => 'Atrasadas / Vencidas', 'color' => 'danger'],
            'pendente' => ['label' => 'Pendentes', 'color' => 'warning'],
            'em_andamento' => ['label' => 'Em Andamento', 'color' => 'info'],
            'programada' => ['label' => 'Programadas', 'color' => 'primary'],
            'concluida' => ['label' => 'Concluídas', 'color' => 'success'],
        ];
        $rowsByCategory = $this->clientId ? $this->rowsByCategory : [];
    @endphp

    <div class="flex flex-col gap-6">
        <div class="max-w-md">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
            <select wire:model.live="clientId" class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 text-sm">
                <option value="">Selecione um cliente...</option>
                @foreach($this->clients as $client)
                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                @endforeach
            </select>
        </div>

        @if(! $this->clientId)
            <x-filament::section>
                <p class="text-sm text-gray-500">Selecione um cliente para ver as manutenções previstas dos ativos alugados por ele.</p>
            </x-filament::section>
        @elseif(empty($rowsByCategory) || collect($rowsByCategory)->every(fn ($c) => $c->isEmpty()))
            <x-filament::section>
                <p class="text-sm text-gray-500">Nenhum ativo com contrato ativo encontrado para este cliente, ou nenhum plano de manutenção aplicável.</p>
            </x-filament::section>
        @else
            @foreach($categories as $key => $meta)
                @php $rows = $rowsByCategory[$key] ?? collect(); @endphp
                @if($rows->isNotEmpty())
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2">
                                <x-filament::badge :color="$meta['color']">{{ $rows->count() }}</x-filament::badge>
                                <span>{{ $meta['label'] }}</span>
                            </div>
                        </x-slot>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b dark:border-gray-700">
                                        <th class="py-2 pr-4">Ativo</th>
                                        <th class="py-2 pr-4">Plano</th>
                                        <th class="py-2 pr-4">Técnico</th>
                                        <th class="py-2 pr-4">Próximas datas previstas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                        <tr class="border-b last:border-0 dark:border-gray-800">
                                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">{{ $row['asset']->name }}</td>
                                            <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $row['plan']->name }}</td>
                                            <td class="py-2 pr-4 text-gray-600 dark:text-gray-400">{{ $row['order']?->technician?->name ?? 'Sem técnico' }}</td>
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
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
