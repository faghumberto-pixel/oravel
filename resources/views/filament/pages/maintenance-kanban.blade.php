<x-filament-panels::page>
    <div class="max-w-full">

        {{-- Cabeçalho Analítico --}}
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 p-6 bg-gray-900 rounded-xl border border-gray-800 shadow-xl">
            <div>
                <h2 class="text-lg font-black text-white uppercase tracking-tight">Análise de Performance</h2>
                <p class="text-[11px] text-gray-400">Indicadores de ciclo médio de manutenção por etapa</p>
            </div>

            <div class="flex items-center gap-8 mt-4 md:mt-0">
                @php
                    $allRecordsGrouped = $this->getRecords();
                    $totalFiltrado = $allRecordsGrouped->flatten()->count();
                    $totalGeral = $this->getTotalOrdersCount();
                @endphp
                <div class="flex gap-6 border-r border-gray-800 pr-8">
                    <div class="text-right">
                        <span class="block text-[9px] text-gray-500 uppercase font-black tracking-widest">Total no Pátio</span>
                        <span class="text-2xl font-black text-gray-300">{{ $totalGeral }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-[9px] text-gray-500 uppercase font-black tracking-widest">Encontrados</span>
                        <span class="text-2xl font-black {{ $search || $technicianId ? 'text-amber-400' : 'text-gray-400' }}">{{ $totalFiltrado }}</span>
                    </div>
                </div>

                <div class="text-right">
                    <span class="block text-[9px] text-gray-500 uppercase font-black tracking-widest">Média do Quadro</span>
                    <span class="text-2xl font-black text-primary-400">{{ $this->getAverageLeadTime() }} <span class="text-xs text-gray-500 font-normal">dias</span></span>
                </div>

                @if(\App\Support\Tenancy::current())
                    <a href="{{ route('maintenance.report') }}"
                       target="_blank"
                       class="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-lg text-[10px] font-black uppercase transition border border-indigo-500">
                        <x-heroicon-o-document-chart-bar class="w-4 h-4" />
                        Relatório Analítico
                    </a>
                @endif
            </div>
        </div>

        {{-- Barra de Filtros Avançada --}}
        <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between mb-3 p-4 bg-gray-900/60 rounded-xl border border-gray-800/80 backdrop-blur-sm">
            <div class="flex gap-3">
                <button wire:click="$set('viewMode', 'oficina')"
                        class="px-6 py-2.5 text-[10px] font-black uppercase rounded-lg transition-all whitespace-nowrap {{ $this->viewMode === 'oficina' ? 'bg-primary-600 text-white shadow-md shadow-primary-900/30' : 'bg-gray-800 text-gray-400 hover:bg-gray-700' }}">
                    Fluxo de Oficina
                </button>
                <button wire:click="$set('viewMode', 'comercial')"
                        class="px-6 py-2.5 text-[10px] font-black uppercase rounded-lg transition-all whitespace-nowrap {{ $this->viewMode === 'comercial' ? 'bg-primary-600 text-white shadow-md shadow-primary-900/30' : 'bg-gray-800 text-gray-400 hover:bg-gray-700' }}">
                    Painel Comercial
                </button>
            </div>

            {{-- Input de Busca Inteligente Estilizado --}}
            <div class="relative flex-1 max-w-xl">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-700 z-10">
                    <x-heroicon-o-magnifying-glass wire:loading.remove wire:target="search" class="w-4 h-4" />
                    <svg wire:loading wire:target="search" class="animate-spin h-4 w-4 text-gray-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Buscar..."
                       class="w-full pl-9 pr-10 py-2.5 bg-white border border-gray-300 rounded-lg text-xs text-gray-950 font-bold placeholder-gray-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500 transition-all shadow-inner">

                @if(!empty($search))
                    <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-950 font-bold hover:scale-110 transition active:scale-95 z-10">
                        <x-heroicon-o-x-mark class="w-4 h-4 stroke-[3]" />
                    </button>
                @endif
            </div>

            <div class="flex gap-3">
                <button wire:click="toggleFiltersPanel"
                        class="relative flex items-center gap-2 px-5 py-2.5 text-[10px] font-black uppercase rounded-lg border transition-all whitespace-nowrap {{ $showFilters ? 'border-primary-500 text-primary-400 bg-primary-500/10' : 'border-gray-700 text-gray-300 hover:bg-gray-800' }}">
                    <x-heroicon-o-funnel class="w-4 h-4" />
                    Filtros
                    @if($this->getActiveFilterCount() > 0)
                        <span class="absolute -top-2 -right-2 bg-amber-500 text-gray-950 text-[9px] font-black rounded-full w-4 h-4 flex items-center justify-center">
                            {{ $this->getActiveFilterCount() }}
                        </span>
                    @endif
                </button>

                <a href="{{ $this->getPrintUrl() }}" target="_blank"
                   class="flex items-center gap-2 px-5 py-2.5 text-[10px] font-black uppercase rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 transition-all whitespace-nowrap">
                    <x-heroicon-o-printer class="w-4 h-4" />
                    Imprimir
                </a>

                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('create') }}"
                   class="flex items-center gap-2 px-5 py-2.5 text-[10px] font-black uppercase rounded-lg bg-primary-600 hover:bg-primary-500 text-white shadow-md shadow-primary-900/30 transition-all whitespace-nowrap">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Nova OS
                </a>
            </div>
        </div>

        {{-- Painel de Filtros (Técnico + Colunas) --}}
        @if($showFilters)
            <div class="mb-3 p-4 bg-gray-900/80 rounded-xl border border-gray-800/80 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2">Filtrar por técnico</label>
                    <select wire:model.live="technicianId"
                            class="w-full py-2 px-3 bg-gray-800 border border-gray-700 rounded-lg text-xs text-gray-200 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500">
                        <option value="">Todos os técnicos</option>
                        @foreach($this->getTechniciansList() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-500 mb-2">Colunas visíveis</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($this->getStatuses() as $statusId => $statusData)
                            @php $isHidden = in_array($statusId, $hiddenStatuses, true); @endphp
                            <button wire:click="toggleStatusVisibility('{{ $statusId }}')"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase border transition-all {{ $isHidden ? 'border-gray-800 text-gray-600 bg-gray-950/40 line-through' : 'border-gray-700 text-gray-200 bg-gray-800' }}">
                                <span class="w-2 h-2 rounded-full {{ $statusData['color'] }}"></span>
                                {{ $statusData['title'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Chips de filtros ativos --}}
        @if($this->getActiveFilterCount() > 0)
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <span class="text-[9px] font-black uppercase tracking-widest text-gray-500">Filtros ativos:</span>

                @if($technicianId)
                    <span class="flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-primary-500/10 border border-primary-500/40 text-primary-300 text-[10px] font-bold">
                        Técnico: {{ $this->getTechniciansList()[$technicianId] ?? '--' }}
                        <button wire:click="$set('technicianId', '')" class="hover:text-white"><x-heroicon-o-x-mark class="w-3 h-3" /></button>
                    </span>
                @endif

                @foreach($hiddenStatuses as $hiddenId)
                    <span class="flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-gray-800 border border-gray-700 text-gray-400 text-[10px] font-bold">
                        Oculta: {{ $this->getStatuses()[$hiddenId]['title'] ?? $hiddenId }}
                        <button wire:click="toggleStatusVisibility('{{ $hiddenId }}')" class="hover:text-white"><x-heroicon-o-x-mark class="w-3 h-3" /></button>
                    </span>
                @endforeach

                <button wire:click="clearFilters" class="text-[10px] font-bold uppercase text-gray-500 hover:text-white underline">
                    Limpar tudo
                </button>
            </div>
        @else
            <div class="mb-6"></div>
        @endif

        {{-- Grid Principal do Kanban --}}
        <div class="flex flex-row gap-4 overflow-x-auto pb-4 custom-scrollbar min-h-[70vh]">
            @forelse($this->getVisibleStatuses() as $statusId => $statusData)
                @php
                    $records = $allRecordsGrouped->get($statusId, collect());
                    $borderColor = str_replace('bg-', 'border-', $statusData['color']);
                @endphp

                <div class="flex-1 min-w-[290px] max-w-[400px] bg-gray-900/40 rounded-xl border border-gray-800/50 flex flex-col backdrop-blur-sm shadow-xl">
                    <div class="{{ $statusData['color'] }} p-3 border-b border-white/10 rounded-t-xl shadow-md">
                        <div class="flex items-center justify-between">
                            <h3 class="text-[10px] font-black uppercase tracking-widest text-white">{{ $statusData['title'] }}</h3>
                            @if(!empty($statusData['flag']))
                                <span class="text-[8px] font-black uppercase bg-black/25 text-white px-1.5 py-0.5 rounded">{{ $statusData['flag'] }}</span>
                            @endif
                        </div>
                        <span class="text-[10px] text-white/80 font-bold">{{ $records->count() }} OS</span>
                    </div>

                    <div wire:loading.class="opacity-40" wire:target="search" class="p-3 space-y-3 flex-1 max-h-[58vh] overflow-y-auto vertical-scrollbar bg-gray-950/20 transition-opacity duration-200">
                        @forelse($records as $record)
                            @php
                                $elapsedSeconds = ($record->total_time_seconds ?? 0) + ($record->last_timer_start ? now()->diffInSeconds($record->last_timer_start) : 0);
                                $elapsedLabel = sprintf('%02dh %02dm', intdiv($elapsedSeconds, 3600), intdiv($elapsedSeconds % 3600, 60));
                                $partNames = $record->parts?->pluck('name')->filter()->implode(', ');
                            @endphp
                            <div wire:key="kanban-card-{{ $record->id }}" class="bg-gray-800 p-4 rounded-lg border-l-4 {{ $borderColor }} border-t border-r border-b border-gray-700 hover:border-primary-500 transition-all shadow-lg hover:shadow-primary-900/10 group">
                                <div class="flex justify-between items-start mb-2 gap-2">
                                    <span class="text-xs font-mono font-bold text-primary-400 group-hover:text-primary-300 transition-colors truncate max-w-[140px]">
                                        #{{ $record->os_number ?? substr($record->id, 0, 8) }}
                                    </span>
                                    <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded bg-gray-900 text-gray-500 shrink-0">
                                        {{ $record->maintenance_type ?? 'Corretiva' }}
                                    </span>
                                </div>

                                <h4 class="text-sm font-bold text-gray-100 leading-tight mb-0.5">{{ $record->asset?->name ?? 'Equipamento Indisponível' }}</h4>
                                <p class="text-[10px] text-gray-500 uppercase font-semibold mb-3">Pat: {{ $record->asset?->patrimonio ?? '---' }}</p>

                                @if($statusId === 'aguardando_peca' && $partNames)
                                    <p class="text-[10px] text-amber-400 font-semibold mb-2 truncate">Peças: {{ $partNames }}</p>
                                @endif

                                @if($statusId === 'em_manutencao')
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white shrink-0">
                                            <x-heroicon-s-play class="w-3 h-3" />
                                        </span>
                                        <x-heroicon-o-bolt class="w-3.5 h-3.5 text-amber-400 shrink-0" />
                                        <span class="text-[11px] font-mono font-bold text-gray-200">{{ $elapsedLabel }}</span>
                                    </div>
                                @endif

                                <div class="flex items-center justify-between pt-2 border-t border-gray-700/50">
                                    @if($statusId === 'concluido')
                                        <span class="text-[10px] text-gray-500 font-medium">
                                            Concluído: {{ $record->finished_at?->format('d/m/y') ?? '--' }}
                                        </span>
                                    @elseif($record->client)
                                        <span class="text-[10px] text-gray-500 font-medium truncate max-w-[160px]">
                                            Cliente: {{ $record->client->name }}
                                        </span>
                                    @else
                                        <span class="text-[10px] text-gray-500 font-medium truncate max-w-[160px]">
                                            Técnico: {{ $record->technician?->name ? explode(' ', $record->technician->name)[0] : '--' }}
                                        </span>
                                    @endif

                                    <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $record]) }}" class="text-[10px] font-black uppercase text-primary-400 hover:text-primary-300 tracking-wider transition-colors shrink-0">Abrir</a>
                                </div>

                                <div class="flex items-center gap-2 mt-2 text-gray-600">
                                    @if($statusId === 'teste_qualidade')
                                        <x-heroicon-o-pause-circle class="w-3.5 h-3.5" />
                                    @else
                                        @if(($record->evidences_count ?? 0) > 0)
                                            <x-heroicon-o-photo class="w-3.5 h-3.5" />
                                        @endif
                                        <x-heroicon-o-bolt class="w-3.5 h-3.5" />
                                        @if($record->parts && $record->parts->isNotEmpty())
                                            <x-heroicon-o-cube class="w-3.5 h-3.5" />
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-12 text-[10px] text-gray-700 uppercase font-bold italic tracking-wide border border-dashed border-gray-800/80 rounded-xl bg-black/5">Sem registros</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="w-full text-center py-16 text-sm text-gray-600 italic">
                    Todas as colunas estão ocultas pelo filtro. <button wire:click="clearFilters" class="underline text-primary-400">Limpar filtros</button>.
                </div>
            @endforelse
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #090d16; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; border: 2px solid #090d16; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #334155; }

        .vertical-scrollbar::-webkit-scrollbar { width: 5px; }
        .vertical-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .vertical-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 10px; }
        .vertical-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
    </style>
</x-filament-panels::page>
