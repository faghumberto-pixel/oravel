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
    @endphp

    <div class="max-w-full space-y-6">

        {{-- ===================== KPI CARDS ===================== --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0">
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-2xl font-black text-gray-900 dark:text-white leading-none">{{ $kpis['total'] }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mt-1">O.S. Totais</div>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 {{ $toneClasses['success']['kpiBg'] }} shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-600/15 {{ $toneClasses['success']['kpiTone'] }} shrink-0">
                        <x-heroicon-o-wrench class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-2xl font-black {{ $toneClasses['success']['kpiTone'] }} leading-none">{{ $kpis['concluidas'] }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mt-1">Concluídas</div>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 {{ $toneClasses['info']['kpiBg'] }} shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-blue-600/15 {{ $toneClasses['info']['kpiTone'] }} shrink-0">
                        <x-heroicon-o-clock class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-2xl font-black {{ $toneClasses['info']['kpiTone'] }} leading-none">{{ $kpis['emAndamento'] }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mt-1">Em Andamento</div>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 {{ $toneClasses['warning']['kpiBg'] }} shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-amber-500/15 {{ $toneClasses['warning']['kpiTone'] }} shrink-0">
                        <x-heroicon-o-list-bullet class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-2xl font-black {{ $toneClasses['warning']['kpiTone'] }} leading-none">{{ $kpis['revisaoPendente'] }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mt-1">Revisão Pendente</div>
                    </div>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 {{ $toneClasses['critical']['kpiBg'] }} shadow-sm">
                <div class="flex items-center gap-3">
                    <span class="flex items-center justify-center w-9 h-9 rounded-lg bg-red-600/15 {{ $toneClasses['critical']['kpiTone'] }} shrink-0">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-2xl font-black {{ $toneClasses['critical']['kpiTone'] }} leading-none">{{ $kpis['criticas'] }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mt-1">Críticas/Bloqueadas</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== GRÁFICOS + ALERTAS ===================== --}}
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 items-stretch">
            <div class="xl:col-span-2">
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
        </div>

        {{-- ===================== KANBAN + BARRAS ===================== --}}
        <div class="grid grid-cols-1 xl:grid-cols-4 gap-4 items-start" x-data="{ dragging: null, overCol: null }">

            <div class="xl:col-span-3 p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[11px] font-black uppercase tracking-wide text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                        <x-heroicon-o-view-columns class="w-4 h-4" />
                        Quadro de Manutenção Preventiva
                    </h3>
                </div>

                <div class="flex flex-row gap-3 overflow-x-auto pb-2 custom-scrollbar">
                    @foreach($columns as $colKey => $col)
                        @php $tone = $toneClasses[$col['tone']]; @endphp
                        <div class="flex-1 min-w-[230px] max-w-[280px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col overflow-hidden">
                            <div class="{{ $tone['tag'] }} px-3 py-2.5">
                                <h4 class="text-[10.5px] font-black uppercase tracking-wide text-white leading-tight">{{ $col['title'] }}</h4>
                                <span class="text-[10.5px] text-white/90 font-bold">{{ $col['cards']->count() }} item(ns)</span>
                            </div>

                            <div
                                class="p-2.5 space-y-2.5 flex-1 min-h-[120px] max-h-[60vh] overflow-y-auto vertical-scrollbar transition-colors"
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

            <div>
                @livewire(\App\Filament\Widgets\PmpByEquipmentTypeChart::class)
            </div>
        </div>
    </div>

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
