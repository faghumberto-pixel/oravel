<x-filament-panels::page>
    @php
        $kpis = $this->getKpis();
        $columns = $this->getKanbanColumns();
        $alerts = $this->getCriticalAlerts();
        $tenantName = \App\Support\Tenancy::current()?->name;

        // Painel de sala-de-controle: tema escuro fixo (não segue o toggle
        // claro/escuro do painel) -- o wrapper .dark abaixo escopa isso,
        // inclusive pros 3 ChartWidgets do Filament embutidos via @livewire.
        //
        // headerBg/cardBorder: mesma paleta literal de
        // MaintenanceKanban::statusMap() (Kanban do Pátio) -- cabeçalho de
        // coluna em bloco de cor sólida + card com borda esquerda colorida,
        // pra esse Kanban ficar visualmente igual ao do Pátio.
        $tones = [
            'critical' => ['text' => 'text-rose-400', 'chip' => 'bg-rose-500/15', 'bar' => 'bg-rose-500', 'dot' => 'bg-rose-500', 'ring' => 'ring-rose-500/50', 'headerBg' => 'bg-red-600', 'cardBorder' => 'border-red-500'],
            'info' => ['text' => 'text-indigo-400', 'chip' => 'bg-indigo-500/15', 'bar' => 'bg-indigo-500', 'dot' => 'bg-indigo-500', 'ring' => 'ring-indigo-500/50', 'headerBg' => 'bg-slate-600', 'cardBorder' => 'border-slate-500'],
            'lightblue' => ['text' => 'text-sky-400', 'chip' => 'bg-sky-500/15', 'bar' => 'bg-sky-500', 'dot' => 'bg-sky-500', 'ring' => 'ring-sky-500/50', 'headerBg' => 'bg-blue-600', 'cardBorder' => 'border-blue-500'],
            'warning' => ['text' => 'text-amber-400', 'chip' => 'bg-amber-500/15', 'bar' => 'bg-amber-500', 'dot' => 'bg-amber-500', 'ring' => 'ring-amber-500/50', 'headerBg' => 'bg-purple-600', 'cardBorder' => 'border-purple-500'],
            'success' => ['text' => 'text-emerald-400', 'chip' => 'bg-emerald-500/15', 'bar' => 'bg-emerald-500', 'dot' => 'bg-emerald-500', 'ring' => 'ring-emerald-500/50', 'headerBg' => 'bg-emerald-600', 'cardBorder' => 'border-emerald-500'],
            'neutral' => ['text' => 'text-gray-300', 'chip' => 'bg-gray-700/60', 'bar' => 'bg-gray-500', 'dot' => 'bg-gray-500', 'ring' => 'ring-gray-500/40', 'headerBg' => 'bg-gray-600', 'cardBorder' => 'border-gray-500'],
        ];

        $kpiCards = [
            ['group' => 'total', 'value' => $kpis['total'], 'label' => 'O.S. Totais', 'icon' => 'heroicon-o-clipboard-document-list', 'tone' => 'neutral'],
            ['group' => 'concluidas', 'value' => $kpis['concluidas'], 'label' => 'Concluídas', 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['group' => 'em_andamento', 'value' => $kpis['emAndamento'], 'label' => 'Em Andamento', 'icon' => 'heroicon-o-clock', 'tone' => 'lightblue'],
            ['group' => 'revisao_pendente', 'value' => $kpis['revisaoPendente'], 'label' => 'Revisão Pendente', 'icon' => 'heroicon-o-magnifying-glass-circle', 'tone' => 'warning'],
            ['group' => 'criticas', 'value' => $kpis['criticas'], 'label' => 'Críticas/Bloqueadas', 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'critical'],
        ];
    @endphp

    <div class="dark">
    <div class="max-w-full flex flex-col gap-3 rounded-2xl bg-gray-900 p-3 text-gray-100 ring-1 ring-white/5">

        {{-- ===================== CABEÇALHO COMPACTO ===================== --}}
        <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-800/60 backdrop-blur-sm px-4 py-2.5 ring-1 ring-white/5">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-100 truncate">Sistema de Gestão de Manutenção Preventiva</p>
                <p class="text-[10px] text-gray-400 truncate">{{ $tenantName ?? 'Painel PMP' }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="hidden sm:inline text-[10px] font-medium text-gray-500 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Ao vivo</span>
            </div>
        </div>

        {{-- ===================== KPIs (1 linha única) ===================== --}}
        <div class="grid grid-cols-5 gap-2">
            @foreach($kpiCards as $kpi)
                @php $tone = $tones[$kpi['tone']]; @endphp
                <button
                    type="button"
                    wire:click="openKpiList('{{ $kpi['group'] }}')"
                    class="min-w-0 flex items-center gap-2 rounded-lg bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 px-2.5 py-2 text-left transition hover:ring-white/15 hover:bg-gray-800"
                >
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md {{ $tone['chip'] }} {{ $tone['text'] }}">
                        <x-dynamic-component :component="$kpi['icon']" class="h-3.5 w-3.5" />
                    </span>
                    <div class="min-w-0 leading-tight">
                        <div class="text-lg font-bold tabular-nums text-white">{{ $kpi['value'] }}</div>
                        <div class="text-[9px] font-medium uppercase tracking-wide text-gray-400 truncate">{{ $kpi['label'] }}</div>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- ===================== ÁREA / GAUGE / ROSCA (3 iguais) ===================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-4 rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5">
                @livewire(\App\Filament\Widgets\Charts\AreaChart::class, [
                    'labels' => $this->getEvolutionAreaData()['labels'],
                    'seriesA' => $this->getEvolutionAreaData()['seriesA'],
                    'seriesB' => $this->getEvolutionAreaData()['seriesB'],
                    'chartTitle' => 'Evolução Mensal (Realizado vs. Planejado)',
                ])
            </div>
            <div class="lg:col-span-4 rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5">
                @livewire(\App\Filament\Widgets\Charts\GaugeChart::class, [
                    'value' => $this->getTaxaConclusao(),
                    'target' => 80,
                    'chartTitle' => 'Taxa de Conclusão',
                ])
            </div>
            <div class="lg:col-span-4">
                @livewire(\App\Filament\Widgets\PmpStatusDonutChart::class)
            </div>
        </div>

        {{-- ===================== LINHA COM MARCADORES / ALERTAS ===================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3">
            <div class="lg:col-span-6 rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5">
                @livewire(\App\Filament\Widgets\Charts\LineChartWithMarkers::class, [
                    'labels' => $this->getPrazoFatalPorMesData()['labels'],
                    'series' => $this->getPrazoFatalPorMesData()['series'],
                    'chartTitle' => 'O.S. com Prazo Fatal por Mês',
                ])
            </div>

            <div class="lg:col-span-6 rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3 flex flex-col">
                <h3 class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                    <x-heroicon-o-bell-alert class="w-3.5 h-3.5" />
                    Alertas Críticos &amp; Pendências
                </h3>
                <div class="flex-1 space-y-0.5 overflow-y-auto max-h-[190px] pr-1 pmp-scroll">
                    @forelse($alerts as $alert)
                        @php $t = $alert['tone'] === 'critical' ? $tones['critical'] : $tones['warning']; @endphp
                        <div class="flex items-start gap-2 py-1.5 border-l-2 {{ $alert['tone'] === 'critical' ? 'border-rose-500' : 'border-amber-500' }} pl-2">
                            <div class="min-w-0">
                                <p class="text-[11px] font-medium text-gray-200 leading-snug">{{ $alert['text'] }}</p>
                                <p class="text-[9.5px] {{ $t['text'] }} font-medium">{{ $alert['meta'] }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex items-center justify-center py-4">
                            <p class="text-[11px] text-gray-500 italic">Nenhum alerta.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ===================== KANBAN + BARRAS (disposição original) ===================== --}}
        <div class="flex-1 grid grid-cols-1 xl:grid-cols-12 gap-3 min-h-0">

            <div class="xl:col-span-10 rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3 flex flex-col min-h-0" x-data="{ dragging: null, overCol: null }">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">
                        <x-heroicon-o-view-columns class="w-3.5 h-3.5" />
                        Quadro de Manutenção Preventiva
                    </h3>
                </div>

                <div class="flex-1 grid grid-cols-5 gap-2 min-h-0">
                    @foreach($columns as $colKey => $col)
                        @php $tone = $tones[$col['tone']]; @endphp
                        {{-- Mesmo idioma visual do Kanban do Pátio (MaintenanceKanban):
                             cabeçalho em bloco de cor sólida + cards com borda esquerda colorida. --}}
                        <div class="min-w-0 rounded-lg bg-gray-800/40 ring-1 ring-white/5 flex flex-col overflow-hidden shadow-sm">
                            <div class="{{ $tone['headerBg'] }} px-2.5 py-2 shrink-0">
                                <h4 class="text-[10px] font-black uppercase tracking-wide text-white leading-tight truncate">{{ $col['title'] }}</h4>
                                <span class="text-[10px] text-white/90 font-bold">{{ $col['cards']->count() }} OS</span>
                            </div>

                            <div
                                class="p-1.5 space-y-1.5 flex-1 min-h-[300px] max-h-[68vh] overflow-y-auto pmp-scroll transition-colors"
                                :class="overCol === '{{ $colKey }}' ? 'bg-indigo-500/10 ring-1 ring-inset ring-indigo-400/40' : ''"
                                x-on:dragover.prevent="overCol = '{{ $colKey }}'"
                                x-on:dragleave="overCol = (overCol === '{{ $colKey }}') ? null : overCol"
                                x-on:drop.prevent="overCol = null; if (dragging) { $wire.moveCard(dragging, '{{ $colKey }}'); dragging = null; }"
                            >
                                @forelse($col['cards'] as $card)
                                    <div
                                        draggable="true"
                                        x-on:dragstart="dragging = '{{ $card['id'] }}'"
                                        x-on:dragend="dragging = null"
                                        wire:key="pmp-card-{{ $card['id'] }}"
                                        class="bg-gray-900 p-2 rounded-md border-l-4 {{ $card['blocked'] ? 'border-red-500 ring-1 ring-red-500/50' : $tone['cardBorder'] }} cursor-grab active:cursor-grabbing hover:shadow-md transition-all shadow-sm"
                                    >
                                        <div class="flex items-center justify-between gap-1 mb-1">
                                            <span class="text-[9px] font-mono font-bold text-gray-500 truncate">{{ $card['code'] }}</span>
                                            @if($card['blocked'])
                                                <x-heroicon-s-exclamation-triangle class="w-2.5 h-2.5 text-red-400 shrink-0" />
                                            @endif
                                        </div>

                                        <p class="text-[10.5px] font-bold text-gray-50 leading-snug mb-1 line-clamp-2">{{ $card['title'] }}</p>

                                        <div class="flex items-center gap-1 text-[9.5px] text-gray-400 mb-0.5">
                                            <x-heroicon-o-user class="w-2.5 h-2.5 shrink-0 text-gray-600" />
                                            <span class="truncate">{{ $card['tech'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[9.5px] text-gray-400 mb-1.5">
                                            <x-heroicon-o-calendar class="w-2.5 h-2.5 shrink-0 text-gray-600" />
                                            <span>{{ $card['date'] }}</span>
                                        </div>

                                        <div class="h-0.5 rounded-full bg-gray-700 overflow-hidden">
                                            <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $card['progress'] }}%"></div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-4 text-[9px] text-gray-600 uppercase font-semibold tracking-wide border border-dashed border-white/10 rounded-md">
                                        Sem itens
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex justify-end gap-1.5 mt-2 pt-2 border-t border-white/5">
                    <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('index', ['tableFilters[maintenance_type][value]' => 'Preventiva']) }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-medium rounded-md bg-gray-900/60 ring-1 ring-white/5 text-gray-400 hover:text-white hover:ring-white/15 transition">
                        <x-heroicon-o-funnel class="w-3 h-3" />
                        Filter
                    </a>
                    <a href="{{ \App\Filament\Resources\UserResource::getUrl('index') }}"
                       class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-medium rounded-md bg-gray-900/60 ring-1 ring-white/5 text-gray-400 hover:text-white hover:ring-white/15 transition">
                        <x-heroicon-o-users class="w-3 h-3" />
                        Users
                    </a>
                </div>
            </div>

            <div class="xl:col-span-2 flex flex-col min-h-[300px]">
                @livewire(\App\Filament\Widgets\PmpByEquipmentTypeChart::class)
            </div>
        </div>
    </div>
    </div>

    {{-- ===================== MODAL: equipamentos do KPI clicado ===================== --}}
    @if($openKpiGroup)
        @php $items = $this->getKpiListItems(); @endphp
        <div class="dark">
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-950/70 backdrop-blur-[2px]" wire:click.self="closeKpiList">
            <div class="w-full max-w-xl max-h-[80vh] flex flex-col bg-gray-900 rounded-2xl ring-1 ring-white/10 shadow-2xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-white/5">
                    <div>
                        <h3 class="text-sm font-bold text-white">{{ $this->getKpiGroupLabel() }}</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $items->count() }} equipamento(s) — localização pra apoiar a ida do técnico</p>
                    </div>
                    <button type="button" wire:click="closeKpiList" class="w-7 h-7 flex items-center justify-center rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                    </button>
                </div>

                <div class="overflow-y-auto pmp-scroll divide-y divide-white/5">
                    @forelse($items as $item)
                        <a href="{{ $item['edit_url'] }}" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-800/60 transition">
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gray-800 text-gray-400 shrink-0">
                                <x-heroicon-o-map-pin class="w-4 h-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[12.5px] font-semibold text-gray-100">
                                    {{ $item['asset_name'] }}
                                    <span class="font-mono font-normal text-gray-500">· {{ $item['patrimonio'] }}</span>
                                </p>
                                <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $item['location'] }}</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $item['technician'] }}</p>
                            </div>
                            <span class="text-[10px] font-mono tabular-nums text-gray-500 shrink-0">OS #{{ $item['os_number'] }}</span>
                        </a>
                    @empty
                        <p class="text-xs text-gray-500 italic py-10 text-center">Nenhuma O.S. nesse grupo.</p>
                    @endforelse
                </div>
            </div>
        </div>
        </div>
    @endif

    <style>
        .pmp-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .pmp-scroll::-webkit-scrollbar-track { background: transparent; }
        .pmp-scroll::-webkit-scrollbar-thumb { background: rgb(71 85 105); border-radius: 10px; }
    </style>
</x-filament-panels::page>
