<x-filament-panels::page>
    @php
        $entries = $this->entries;
        $pending = $this->pending;
        $kpis = $this->getKpis();
        $tenantName = \App\Support\Tenancy::current()?->name;
    @endphp

    {{-- Mesmo padrão do Dashboard PMP: tema escuro fixo nesta página (wrapper
         .dark escopado, não depende do toggle claro/escuro do painel). --}}
    <div class="dark">
    <div class="max-w-full flex flex-col gap-3 rounded-2xl bg-gray-900 p-3 text-gray-100 ring-1 ring-white/5">

        {{-- ===================== CABEÇALHO COMPACTO ===================== --}}
        <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-800/60 backdrop-blur-sm px-4 py-2.5 ring-1 ring-white/5">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-100 truncate">Chegadas e Saídas no Pátio</p>
                <p class="text-[10px] text-gray-400 truncate">{{ $tenantName ?? 'Portaria' }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="hidden sm:inline text-[10px] font-medium text-gray-500 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Ao vivo</span>
            </div>
        </div>

        {{-- ===================== KPIs (1 linha única) ===================== --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
            @foreach([
                ['value' => $kpis['hoje'], 'label' => 'Movimentações Hoje', 'icon' => 'heroicon-o-truck', 'chip' => 'bg-gray-700/60', 'text' => 'text-gray-300'],
                ['value' => $kpis['entradasHoje'], 'label' => 'Entradas Hoje', 'icon' => 'heroicon-o-arrow-down-circle', 'chip' => 'bg-emerald-500/15', 'text' => 'text-emerald-400'],
                ['value' => $kpis['saidasHoje'], 'label' => 'Saídas Hoje', 'icon' => 'heroicon-o-arrow-up-circle', 'chip' => 'bg-amber-500/15', 'text' => 'text-amber-400'],
                ['value' => $kpis['aguardando'], 'label' => 'Aguardando Chegada', 'icon' => 'heroicon-o-clock', 'chip' => 'bg-indigo-500/15', 'text' => 'text-indigo-400'],
                ['value' => $kpis['emAndamento'], 'label' => 'Laudo em Andamento', 'icon' => 'heroicon-o-clipboard-document-check', 'chip' => 'bg-sky-500/15', 'text' => 'text-sky-400'],
            ] as $kpi)
                <div class="min-w-0 flex items-center gap-2 rounded-lg bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 px-2.5 py-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md {{ $kpi['chip'] }} {{ $kpi['text'] }}">
                        <x-dynamic-component :component="$kpi['icon']" class="h-3.5 w-3.5" />
                    </span>
                    <div class="min-w-0 leading-tight">
                        <div class="text-lg font-bold tabular-nums text-white">{{ $kpi['value'] }}</div>
                        <div class="text-[9px] font-medium uppercase tracking-wide text-gray-400 truncate">{{ $kpi['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ===================== GRÁFICOS ===================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
            <div class="lg:col-span-2">
                @livewire(\App\Filament\Widgets\PatioMovementsChart::class)
            </div>
            <div>
                @livewire(\App\Filament\Widgets\PatioReasonDonutChart::class)
            </div>
        </div>

        {{-- ===================== PORTARIA: MOVIMENTAÇÕES RECENTES ===================== --}}
        <div class="rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 overflow-hidden">
            <div class="px-3 py-2 border-b border-white/5">
                <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Movimentações Recentes na Portaria</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase text-gray-500">
                            <th class="px-3 py-2 font-medium">Data/Hora</th>
                            <th class="px-3 py-2 font-medium">Direção</th>
                            <th class="px-3 py-2 font-medium">Placa</th>
                            <th class="px-3 py-2 font-medium">Motorista</th>
                            <th class="px-3 py-2 font-medium">Motivo</th>
                            <th class="px-3 py-2 font-medium">Ativo</th>
                            <th class="px-3 py-2 font-medium">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $entry)
                            <tr class="border-t border-white/5">
                                <td class="px-3 py-2 text-gray-300">{{ $entry->arrived_at->format('d/m/Y H:i') }}</td>
                                <td class="px-3 py-2">
                                    <span @class([
                                        'inline-flex items-center rounded-md px-2 py-1 text-[10px] font-bold uppercase tracking-wide',
                                        'bg-emerald-500/15 text-emerald-400' => $entry->direction === \App\Models\PatioEntry::DIRECTION_ENTRADA,
                                        'bg-amber-500/15 text-amber-400' => $entry->direction === \App\Models\PatioEntry::DIRECTION_SAIDA,
                                    ])>
                                        {{ \App\Models\PatioEntry::directionLabels()[$entry->direction] ?? $entry->direction }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-300">{{ $entry->plate ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-300">{{ $entry->driver_name ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-300">{{ \App\Models\PatioEntry::reasonLabels()[$entry->reason] ?? $entry->reason }}</td>
                                <td class="px-3 py-2 text-gray-300">{{ $entry->asset?->patrimonio ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-300">{{ $entry->registeredBy?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-3 py-6 text-center text-xs text-gray-500">Nenhuma movimentação registrada ainda.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ===================== FILA DE LAUDOS DE RECEBIMENTO ===================== --}}
        <div class="rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3">
            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">Fila de Laudos de Recebimento</h3>

            @if($pending->isEmpty())
                <div class="text-center py-10">
                    <x-heroicon-o-building-storefront class="w-8 h-8 mx-auto text-emerald-400 mb-2" />
                    <p class="text-xs font-semibold text-gray-400">Nenhuma desmobilização aguardando Laudo de Recebimento.</p>
                </div>
            @endif

            <div class="space-y-2">
                @foreach($pending as $movement)
                    @php
                        $arrival = $movement->patioArrival;
                        $items = $arrival?->items;
                        $total = $items?->count() ?? 0;
                        $checked = $items?->where('is_checked', true)->count() ?? 0;
                        $progress = $total > 0 ? (int) round($checked / $total * 100) : 0;
                        $started = (bool) $arrival;
                    @endphp

                    <div class="bg-gray-900 rounded-lg border-l-4 {{ $started ? 'border-amber-500' : 'border-indigo-500' }} overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-3 py-2.5">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-2 h-2 rounded-full {{ $started ? 'bg-amber-400' : 'bg-indigo-400' }} shrink-0"></span>
                                <div class="min-w-0">
                                    <p class="text-[12.5px] font-bold text-gray-50 truncate">{{ $movement->asset?->name ?? 'Equipamento' }}</p>
                                    <p class="text-[10.5px] text-gray-400 truncate">
                                        {{ $movement->maintenanceOrder?->client?->name ?? 'Cliente não informado' }}
                                        &middot; Checklist concluído {{ $movement->completed_at?->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                            <span class="text-[9.5px] font-bold uppercase tracking-wider {{ $started ? 'text-amber-400' : 'text-indigo-400' }} shrink-0">
                                {{ $started ? 'Laudo em Andamento' : 'Aguardando Chegada' }}
                            </span>
                        </div>

                        @if($started)
                            <div class="px-3 pb-2">
                                <div class="flex items-center justify-between text-[9.5px] font-bold text-gray-400 mb-1">
                                    <span>{{ $checked }}/{{ $total }} itens</span>
                                    <span>{{ $progress }}%</span>
                                </div>
                                <div class="h-1 w-full overflow-hidden rounded-full bg-gray-800">
                                    <div class="h-1 rounded-full bg-amber-400" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>
                        @endif

                        <div class="border-t border-white/5 p-2">
                            <a href="{{ route('equipment-movements.patio-arrival-mobile', $movement) }}"
                               class="block w-full py-2 rounded-md text-center {{ $started ? 'bg-amber-500 hover:bg-amber-600' : 'bg-indigo-600 hover:bg-indigo-700' }} text-white text-[10.5px] font-bold uppercase tracking-wider transition">
                                {{ $started ? 'Continuar Laudo de Recebimento' : 'Iniciar Laudo de Recebimento' }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    </div>
</x-filament-panels::page>
