<x-filament-panels::page>
    @php
        $kpis = $this->getKpis();
        $columns = $this->getKanbanColumns();
        $alerts = $this->getCriticalAlerts();

        $toneClasses = [
            'critical' => ['tag' => 'bg-red-600', 'border' => 'border-red-500', 'kpiTone' => 'text-red-600 dark:text-red-400', 'kpiBg' => 'bg-red-500/10'],
            'info' => ['tag' => 'bg-blue-600', 'border' => 'border-blue-500', 'kpiTone' => 'text-blue-600 dark:text-blue-400', 'kpiBg' => 'bg-blue-500/10'],
            'lightblue' => ['tag' => 'bg-sky-500', 'border' => 'border-sky-400', 'kpiTone' => 'text-sky-600 dark:text-sky-400', 'kpiBg' => 'bg-sky-500/10'],
            'warning' => ['tag' => 'bg-amber-500', 'border' => 'border-amber-400', 'kpiTone' => 'text-amber-600 dark:text-amber-400', 'kpiBg' => 'bg-amber-500/10'],
            'success' => ['tag' => 'bg-emerald-600', 'border' => 'border-emerald-500', 'kpiTone' => 'text-emerald-600 dark:text-emerald-400', 'kpiBg' => 'bg-emerald-500/10'],
        ];

        $kpiCards = [
            ['group' => null, 'value' => $kpis['total'], 'label' => 'O.S. Totais', 'icon' => 'heroicon-o-clipboard-document-list', 'tone' => null],
            ['group' => 'concluidas', 'value' => $kpis['concluidas'], 'label' => 'Concluídas', 'icon' => 'heroicon-o-wrench', 'tone' => 'success'],
            ['group' => 'em_andamento', 'value' => $kpis['emAndamento'], 'label' => 'Em Andamento', 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['group' => 'revisao_pendente', 'value' => $kpis['revisaoPendente'], 'label' => 'Revisão Pendente', 'icon' => 'heroicon-o-list-bullet', 'tone' => 'warning'],
            ['group' => 'criticas', 'value' => $kpis['criticas'], 'label' => 'Críticas/Bloqueadas', 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'critical'],
        ];
    @endphp

    <div class="max-w-full space-y-5">

        {{-- ===================== KPI CARDS (compactos, clicáveis) ===================== --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach($kpiCards as $kpi)
                @php $tone = $kpi['tone'] ? $toneClasses[$kpi['tone']] : null; @endphp
                <button
                    type="button"
                    wire:click="openKpiList('{{ $kpi['group'] ?? 'total' }}')"
                    class="group text-left p-2.5 rounded-lg border border-gray-200 dark:border-gray-800 {{ $tone['kpiBg'] ?? 'bg-white dark:bg-gray-900' }} shadow-sm hover:shadow-md hover:border-gray-300 dark:hover:border-gray-700 transition-all"
                >
                    <div class="flex items-center gap-2">
                        <span class="flex items-center justify-center w-7 h-7 rounded-md {{ $tone ? 'bg-white/60 dark:bg-black/20' : 'bg-gray-100 dark:bg-gray-800' }} {{ $tone['kpiTone'] ?? 'text-gray-500 dark:text-gray-400' }} shrink-0">
                            <x-dynamic-component :component="$kpi['icon']" class="w-4 h-4" />
                        </span>
                        <div class="min-w-0">
                            <div class="text-lg font-black leading-none {{ $tone['kpiTone'] ?? 'text-gray-900 dark:text-white' }}">{{ $kpi['value'] }}</div>
                            <div class="text-[9px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mt-0.5 truncate">{{ $kpi['label'] }}</div>
                        </div>
                        <x-heroicon-o-chevron-right class="w-3 h-3 text-gray-300 dark:text-gray-600 ml-auto shrink-0 group-hover:translate-x-0.5 transition-transform" />
                    </div>
                </button>
            @endforeach
        </div>

        {{-- ===================== GRÁFICOS + ALERTAS (1 linha só) ===================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-stretch">
            <div>
                @livewire(\App\Filament\Widgets\PmpEvolutionChart::class)
            </div>
            <div>
                @livewire(\App\Filament\Widgets\PmpStatusDonutChart::class)
            </div>
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm flex flex-col">
                <h3 class="text-[11px] font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3 flex items-center gap-1.5">
                    <x-heroicon-o-bell-alert class="w-4 h-4" />
                    Alertas Críticos &amp; Pendências
                </h3>
                <div class="space-y-0 divide-y divide-gray-100 dark:divide-gray-800 overflow-y-auto max-h-[220px]">
                    @forelse($alerts as $alert)
                        <div class="flex items-start gap-2.5 py-2">
                            <span class="w-2 h-2 rounded-full mt-1.5 shrink-0 {{ $alert['tone'] === 'critical' ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 leading-snug">{{ $alert['text'] }}</p>
                                <p class="text-[10px] font-mono text-gray-400 dark:text-gray-500 mt-0.5">{{ $alert['meta'] }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-600 italic py-4 text-center">Nenhum alerta no momento.</p>
                    @endforelse
                </div>
            </div>
            <div>
                @livewire(\App\Filament\Widgets\PmpByEquipmentTypeChart::class)
            </div>
        </div>

        {{-- ===================== KANBAN (full width) ===================== --}}
        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm" x-data="{ dragging: null, overCol: null }">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[11px] font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                    <x-heroicon-o-view-columns class="w-4 h-4" />
                    Quadro de Manutenção Preventiva
                </h3>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                @foreach($columns as $colKey => $col)
                    @php $tone = $toneClasses[$col['tone']]; @endphp
                    <div class="bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col overflow-hidden">
                        <div class="{{ $tone['tag'] }} px-3 py-2.5">
                            <h4 class="text-[10.5px] font-black uppercase tracking-wide text-white leading-tight">{{ $col['title'] }}</h4>
                            <span class="text-[10.5px] text-white/90 font-bold">{{ $col['cards']->count() }} item(ns)</span>
                        </div>

                        <div
                            class="p-2.5 space-y-2.5 flex-1 min-h-[140px] max-h-[65vh] overflow-y-auto vertical-scrollbar transition-colors"
                            :class="overCol === '{{ $colKey }}' ? 'bg-blue-500/10 ring-2 ring-inset ring-blue-400' : ''"
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
                                    class="bg-white dark:bg-gray-900 p-3 rounded-lg border-l-4 {{ $card['blocked'] ? 'border-red-500 ring-1 ring-red-500/40' : $tone['border'] }} border-t border-r border-b border-gray-200 dark:border-gray-700 shadow-sm cursor-grab active:cursor-grabbing"
                                >
                                    <div class="flex items-center justify-between gap-2 mb-1.5">
                                        <span class="text-[10.5px] font-mono font-bold text-gray-400 dark:text-gray-500">{{ $card['code'] }}</span>
                                        @if($card['blocked'])
                                            <x-heroicon-s-exclamation-triangle class="w-3.5 h-3.5 text-red-500 shrink-0" />
                                        @endif
                                    </div>

                                    <p class="text-xs font-bold text-gray-900 dark:text-gray-50 leading-snug mb-2">{{ $card['title'] }}</p>

                                    @if(!empty($card['patrimonio']))
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 font-mono mb-1.5">Patrim. {{ $card['patrimonio'] }}</p>
                                    @endif

                                    <div class="flex items-center gap-1.5 text-[10.5px] text-gray-500 dark:text-gray-400 mb-1">
                                        <x-heroicon-o-user class="w-3 h-3 shrink-0" />
                                        <span class="truncate">{{ $card['tech'] }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10.5px] text-gray-500 dark:text-gray-400 mb-2">
                                        <x-heroicon-o-calendar class="w-3 h-3 shrink-0" />
                                        <span>{{ $card['date'] }}</span>
                                    </div>

                                    <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                        <div class="h-full rounded-full {{ $tone['tag'] }}" style="width: {{ $card['progress'] }}%"></div>
                                    </div>

                                    <div class="flex items-center justify-between mt-2">
                                        <div class="flex -space-x-1.5">
                                            @foreach($card['avatars'] as $initials)
                                                <span class="w-5 h-5 rounded-full bg-gray-200 dark:bg-gray-700 border-2 border-white dark:border-gray-900 flex items-center justify-center text-[8px] font-black text-gray-600 dark:text-gray-300">{{ $initials }}</span>
                                            @endforeach
                                        </div>
                                        <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500">{{ $card['progress'] }}%</span>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-8 text-[10px] text-gray-400 dark:text-gray-600 uppercase font-bold italic border border-dashed border-gray-300 dark:border-gray-700 rounded-lg">Sem itens</div>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-gray-100 dark:border-gray-800">
                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('index', ['tableFilters[maintenance_type][value]' => 'Preventiva']) }}"
                   class="flex items-center gap-1.5 px-3.5 py-2 text-[10.5px] font-bold uppercase rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                    <x-heroicon-o-funnel class="w-3.5 h-3.5" />
                    Filter
                </a>
                <a href="{{ \App\Filament\Resources\UserResource::getUrl('index') }}"
                   class="flex items-center gap-1.5 px-3.5 py-2 text-[10.5px] font-bold uppercase rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition">
                    <x-heroicon-o-users class="w-3.5 h-3.5" />
                    Users
                </a>
            </div>
        </div>
    </div>

    {{-- ===================== MODAL: lista de equipamentos do KPI clicado ===================== --}}
    @if($openKpiGroup)
        @php $items = $this->getKpiListItems(); @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" wire:click.self="closeKpiList">
            <div class="w-full max-w-2xl max-h-[80vh] flex flex-col bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xl">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
                    <div>
                        <h3 class="text-sm font-black text-gray-900 dark:text-white">{{ $this->getKpiGroupLabel() }}</h3>
                        <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ $items->count() }} equipamento(s) -- localização pra apoiar a ida do técnico</p>
                    </div>
                    <button type="button" wire:click="closeKpiList" class="text-gray-400 hover:text-gray-700 dark:hover:text-white">
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>
                </div>

                <div class="overflow-y-auto vertical-scrollbar divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($items as $item)
                        <a href="{{ $item['edit_url'] }}" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/60 transition">
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100">
                                    {{ $item['asset_name'] }}
                                    <span class="font-mono font-normal text-gray-400 dark:text-gray-500">· Patrim. {{ $item['patrimonio'] }}</span>
                                </p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                    <x-heroicon-o-map-pin class="w-3 h-3 shrink-0" />
                                    <span class="truncate">{{ $item['location'] }}</span>
                                </p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5 flex items-center gap-1">
                                    <x-heroicon-o-user class="w-3 h-3 shrink-0" />
                                    {{ $item['technician'] }}
                                </p>
                            </div>
                            <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500 shrink-0">OS #{{ $item['os_number'] }}</span>
                        </a>
                    @empty
                        <p class="text-xs text-gray-400 dark:text-gray-600 italic py-8 text-center">Nenhuma O.S. nesse grupo.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    <style>
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgb(203 213 225); border-radius: 10px; }
        :is(.dark .custom-scrollbar)::-webkit-scrollbar-thumb { background: rgb(51 65 85); }

        .vertical-scrollbar::-webkit-scrollbar { width: 5px; }
        .vertical-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .vertical-scrollbar::-webkit-scrollbar-thumb { background: rgb(203 213 225); border-radius: 10px; }
        :is(.dark .vertical-scrollbar)::-webkit-scrollbar-thumb { background: rgb(51 65 85); }
    </style>
</x-filament-panels::page>
