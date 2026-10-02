<x-filament-panels::page>
    @php
        $tree = $this->getTenantTree();
    @endphp

    <div class="space-y-3" x-data="{ open: null }">
        @forelse ($tree as $index => $node)
            @php
                $tenant = $node['tenant'];
                $clients = $node['clients'];
                $comContrato = $clients->where('ativo', true)->count();
                $alocados = $clients->sum('equipamentos');
                $totalAtivos = $node['assets_total'];
            @endphp

            <div class="fi-section rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <button
                    type="button"
                    x-on:click="open = (open === {{ $index }}) ? null : {{ $index }}"
                    class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left"
                >
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-building-office-2 class="h-5 w-5 shrink-0 text-gray-400" />
                        <div>
                            <div class="font-semibold text-gray-950 dark:text-white">{{ $tenant->name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $clients->count() }} {{ $clients->count() === 1 ? 'cliente' : 'clientes' }}
                                &middot;
                                {{ $comContrato }} com contrato vigente
                                &middot;
                                {{ $totalAtivos }} {{ $totalAtivos === 1 ? 'equipamento cadastrado' : 'equipamentos cadastrados' }}
                                ({{ $alocados }} vinculados a clientes)
                            </div>
                        </div>
                    </div>

                    <x-heroicon-o-chevron-down
                        class="h-5 w-5 shrink-0 text-gray-400 transition-transform"
                        x-bind:class="open === {{ $index }} ? 'rotate-180' : ''"
                    />
                </button>

                <div x-show="open === {{ $index }}" x-collapse x-cloak>
                    <div class="border-t border-gray-200 dark:border-gray-700">
                        @if ($clients->isEmpty())
                            <p class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">Nenhum cliente cadastrado.</p>
                        @else
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2 text-left font-medium">Cliente</th>
                                        <th class="px-4 py-2 text-left font-medium">Status</th>
                                        <th class="px-4 py-2 text-left font-medium">Equipamentos</th>
                                        <th class="px-4 py-2 text-left font-medium">Login do Portal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($clients as $row)
                                        @php $client = $row['client']; @endphp
                                        <tr>
                                            <td class="px-4 py-2 text-gray-950 dark:text-white">{{ $client->name }}</td>
                                            <td class="px-4 py-2">
                                                @if ($row['ativo'])
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                                        Ativo
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                        Sem contrato vigente
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row['equipamentos'] }}</td>
                                            <td class="px-4 py-2">
                                                @if ($row['portal_habilitado'])
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                                        {{ $client->email ?? 'habilitado' }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                        Sem acesso
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum tenant cadastrado.</p>
        @endforelse
    </div>
</x-filament-panels::page>
