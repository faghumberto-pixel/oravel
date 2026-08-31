<x-filament-panels::page>
    <div class="max-w-full">

        {{-- Cabeçalho Analítico --}}
        <div class="flex flex-col md:flex-row justify-between items-center mb-2 p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div>
                <h2 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-tight">Análise de Preventivas</h2>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">Execuções de manutenção preventiva em andamento</p>
            </div>

            <div class="flex items-center gap-8 mt-4 md:mt-0">
                @php
                    $allRecordsGrouped = $this->getRecords();
                    $totalFiltrado = $allRecordsGrouped->flatten()->count();
                    $totalGeral = $this->getTotalExecutionsCount();
                @endphp
                <div class="flex gap-6 border-r border-gray-200 dark:border-gray-800 pr-8">
                    <div class="text-right">
                        <span class="block text-[9px] text-gray-400 dark:text-gray-500 uppercase font-black tracking-widest">Total de Execuções</span>
                        <span class="text-2xl font-black text-gray-700 dark:text-gray-300">{{ $totalGeral }}</span>
                    </div>
                    <div class="text-right">
                        <span class="block text-[9px] text-gray-400 dark:text-gray-500 uppercase font-black tracking-widest">Encontradas</span>
                        <span class="text-2xl font-black {{ $search || $technicianId || $assetId || $weekFilter ? 'text-amber-500' : 'text-gray-600 dark:text-gray-400' }}">{{ $totalFiltrado }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barra de Filtros Avançada --}}
        <div class="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between mb-2 p-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            {{-- Input de Busca --}}
            <div class="relative flex-1 max-w-xl">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 z-10">
                    <x-heroicon-o-magnifying-glass wire:loading.remove wire:target="search" class="w-4 h-4" />
                    <svg wire:loading wire:target="search" class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Buscar patrimônio..."
                       class="w-full pl-9 pr-10 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-100 font-bold placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-all">

                @if(!empty($search))
                    <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 dark:text-gray-400 font-bold hover:scale-110 transition active:scale-95 z-10">
                        <x-heroicon-o-x-mark class="w-4 h-4 stroke-[3]" />
                    </button>
                @endif
            </div>

            <div class="flex gap-3">
                <button wire:click="toggleFiltersPanel" type="button"
                        class="relative flex items-center gap-2 px-5 py-2.5 text-[10px] font-black uppercase rounded-lg border transition-all whitespace-nowrap {{ $showFilters ? 'border-blue-500 text-blue-600 dark:text-blue-400 bg-blue-500/10' : 'border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' }}">
                    <x-heroicon-o-funnel class="w-4 h-4" />
                    Filtros
                    @if($this->getActiveFilterCount() > 0)
                        <span class="absolute -top-2 -right-2 bg-amber-500 text-white text-[9px] font-black rounded-full w-4 h-4 flex items-center justify-center">
                            {{ $this->getActiveFilterCount() }}
                        </span>
                    @endif
                </button>

                <button onclick="window.print()"
                        class="flex items-center gap-2 px-5 py-2.5 text-[10px] font-black uppercase rounded-lg border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all whitespace-nowrap">
                    <x-heroicon-o-printer class="w-4 h-4" />
                    Imprimir
                </button>
            </div>
        </div>

        {{-- Painel de Filtros (Técnico + Equipamento + Período + Grupo + Cliente + Colunas) --}}
        <div class="mb-2 p-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 grid grid-cols-1 lg:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Filtrar por técnico</label>
                    <select wire:model.live="technicianId"
                            class="w-full py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-200 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos os técnicos</option>
                        @foreach($this->getTechniciansList() as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Filtrar por equipamento</label>
                    <select wire:model.live="assetId"
                            class="w-full py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-200 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos os equipamentos</option>
                        @foreach($this->getAssetsList() as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->patrimonio }} - {{ $asset->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Filtrar por grupo de equipamento</label>
                    <select wire:model.live="groupId"
                            class="w-full py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-200 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos os grupos</option>
                        @foreach($this->getGroupsList() as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Filtrar por cliente</label>
                    <select wire:model.live="clientId"
                            class="w-full py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-200 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos os clientes</option>
                        @foreach($this->getClientsList() as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Data inicial</label>
                    <input wire:model.live="startDate"
                           type="date"
                           class="w-full py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-200 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Data final</label>
                    <input wire:model.live="endDate"
                           type="date"
                           class="w-full py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-200 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-2">Colunas visíveis</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($this->getStatuses() as $statusId => $statusData)
                            @php $isHidden = in_array($statusId, $hiddenStatuses, true); @endphp
                            <button wire:click="toggleStatusVisibility('{{ $statusId }}')"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase border transition-all {{ $isHidden ? 'border-gray-200 dark:border-gray-800 text-gray-400 dark:text-gray-600 bg-gray-50 dark:bg-gray-950/40 line-through' : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 bg-gray-50 dark:bg-gray-800' }}">
                                <span class="w-2 h-2 rounded-full {{ $statusData['color'] }}"></span>
                                {{ $statusData['title'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

        {{-- Chips de filtros ativos --}}
        @if($this->getActiveFilterCount() > 0)
            <div class="flex flex-wrap items-center gap-2 mb-2">
                <span class="text-[9px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">Filtros ativos:</span>

                @if($technicianId)
                    <span class="flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/40 text-blue-600 dark:text-blue-300 text-[10px] font-bold">
                        Técnico: {{ $this->getTechniciansList()->firstWhere('id', $technicianId)?->name ?? '--' }}
                        <button wire:click="$set('technicianId', '')" class="hover:text-blue-800 dark:hover:text-white"><x-heroicon-o-x-mark class="w-3 h-3" /></button>
                    </span>
                @endif

                @if($assetId)
                    <span class="flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/40 text-blue-600 dark:text-blue-300 text-[10px] font-bold">
                        Equipamento: {{ $this->getAssetsList()->firstWhere('id', $assetId)?->patrimonio ?? '--' }}
                        <button wire:click="$set('assetId', '')" class="hover:text-blue-800 dark:hover:text-white"><x-heroicon-o-x-mark class="w-3 h-3" /></button>
                    </span>
                @endif

                @if($weekFilter)
                    <span class="flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-blue-500/10 border border-blue-500/40 text-blue-600 dark:text-blue-300 text-[10px] font-bold">
                        Semana: {{ $weekFilter }}
                        <button wire:click="$set('weekFilter', '')" class="hover:text-blue-800 dark:hover:text-white"><x-heroicon-o-x-mark class="w-3 h-3" /></button>
                    </span>
                @endif

                <button wire:click="clearFilters" class="text-[10px] font-bold uppercase text-gray-400 dark:text-gray-500 hover:text-gray-900 dark:hover:text-white underline">
                    Limpar tudo
                </button>
            </div>
        @else
            <div class="mb-1"></div>
        @endif

        {{-- Grid Principal do Kanban --}}
        <div class="flex flex-row gap-4 overflow-x-auto pb-4 custom-scrollbar min-h-[70vh]">
            @forelse($this->getVisibleStatuses() as $statusId => $statusData)
                @php
                    $records = $allRecordsGrouped->get($statusId, collect());
                    $statusColorMap = [
                        'aguardando_diagnostico' => 'bg-slate-600',
                        'em_manutencao' => 'bg-blue-600',
                        'aguardando_peca' => 'bg-amber-500',
                        'teste_qualidade' => 'bg-purple-600',
                        'pronto_giro' => 'bg-teal-600',
                        'pendencia' => 'bg-orange-500',
                        'concluido' => 'bg-emerald-600',
                    ];
                    $headerBg = $statusColorMap[$statusId] ?? 'bg-slate-600';
                    $sideBorder = str_replace('bg-', 'border-', $headerBg);
                @endphp

                <div class="flex-1 min-w-[230px] max-w-[280px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col shadow-sm overflow-hidden">
                    <div class="{{ $headerBg }} px-3 py-3 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-[11px] font-black uppercase tracking-wide text-white leading-tight">{{ $statusData['title'] }}</h3>
                        </div>
                        <span class="text-[11px] text-white/90 font-bold">{{ $records->count() }} execuções</span>
                    </div>

                    <div wire:loading.class="opacity-40" wire:target="search" class="p-2.5 space-y-2.5 flex-1 max-h-[62vh] overflow-y-auto vertical-scrollbar transition-opacity duration-200">
                        @forelse($records as $execution)
                            <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $execution->maintenanceOrder?->id]) }}"
                               class="block bg-white dark:bg-gray-900 p-3 rounded-lg border-l-4 {{ $sideBorder }} border-t border-r border-b border-gray-200 dark:border-gray-700 hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500 transition-all shadow-sm group">
                                {{-- Patrimônio --}}
                                <div class="flex justify-between items-start mb-1.5 gap-2">
                                    <span class="text-[11px] font-mono font-bold text-gray-400 dark:text-gray-500 truncate max-w-[110px]">
                                        {{ $execution->asset?->patrimonio ?? 'N/A' }}
                                    </span>
                                </div>

                                {{-- Plano de Manutenção --}}
                                <h4 class="text-base font-black text-gray-900 dark:text-gray-50 leading-tight mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ $execution->maintenancePlan?->name ?? 'Sem Plano' }}
                                </h4>

                                {{-- Técnico --}}
                                @if($execution->technician)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mb-1.5 truncate">
                                        👤 {{ $execution->technician->name }}
                                    </p>
                                @endif

                                {{-- Horímetro --}}
                                <div class="flex items-center justify-between pt-2 mt-1 border-t border-gray-100 dark:border-gray-800">
                                    <div class="flex items-center gap-1.5 text-gray-600 dark:text-gray-400">
                                        <x-heroicon-o-bolt class="w-3.5 h-3.5 text-emerald-500" />
                                        <span class="text-[11px] font-mono font-bold">
                                            {{ number_format($execution->horimetro_at_execution, 0) }}h
                                            @if($execution->next_due_horimetro)
                                                / {{ number_format($execution->next_due_horimetro, 0) }}h
                                            @endif
                                        </span>
                                    </div>
                                    <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 group-hover:underline">
                                        Ver OS
                                    </span>
                                </div>
                            </a>
                        @empty
                            <div class="text-center py-12 text-[10px] text-gray-400 dark:text-gray-600 uppercase font-bold italic tracking-wide border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">Sem registros</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="w-full text-center py-16 text-sm text-gray-500 dark:text-gray-400 italic">
                    Todas as colunas estão ocultas pelo filtro. <button wire:click="clearFilters" class="underline text-blue-600 dark:text-blue-400">Limpar filtros</button>.
                </div>
            @endforelse
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgb(203 213 225); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgb(148 163 184); }
        :is(.dark .custom-scrollbar)::-webkit-scrollbar-thumb { background: rgb(51 65 85); }
        :is(.dark .custom-scrollbar)::-webkit-scrollbar-thumb:hover { background: rgb(71 85 105); }

        .vertical-scrollbar::-webkit-scrollbar { width: 5px; }
        .vertical-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .vertical-scrollbar::-webkit-scrollbar-thumb { background: rgb(203 213 225); border-radius: 10px; }
        :is(.dark .vertical-scrollbar)::-webkit-scrollbar-thumb { background: rgb(51 65 85); }

        @media print {
            .max-w-full > * { page-break-inside: avoid; }
            .flex.flex-row.gap-4 { display: grid; grid-template-columns: repeat(3, 1fr); }
            .min-w-\[230px\] { min-width: auto; max-width: 100%; }
            .overflow-x-auto { overflow: visible; }
            .p-2\.5 { max-height: none !important; }
        }
    </style>
</x-filament-panels::page>
