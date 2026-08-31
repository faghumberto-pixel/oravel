<x-filament-panels::page>
    <div style="max-width: 100%;">

        {{-- Cabeçalho Analítico --}}
        <div style="display: flex; flex-direction: column; gap: 1.5rem; margin-bottom: 1.5rem; padding: 1.5rem; background-color: #1f2937; border-radius: 12px; border: 4px solid #374151;">
            <div>
                <h2 style="font-size: 1.125rem; font-weight: 900; color: #f3f4f6; text-transform: uppercase; letter-spacing: 0.05em; margin: 0;">Execuções de Manutenção Preventiva</h2>
                <p style="font-size: 0.6875rem; color: #9ca3af; margin: 0.25rem 0 0 0;">Acompanhamento de preventivas em andamento</p>
            </div>

            <div style="display: flex; gap: 2rem; align-items: center;">
                @php
                    $allRecords = $this->getRecords();
                    $totalFiltrado = $allRecords->flatten()->count();
                    $totalGeral = $this->getTotalExecutionsCount();
                @endphp

                <div style="display: flex; gap: 1.5rem; border-right: 1px solid #4b5563; padding-right: 2rem;">
                    <div style="text-align: right;">
                        <span style="display: block; font-size: 0.5625rem; color: #6b7280; text-transform: uppercase; font-weight: 900; letter-spacing: 0.08em; margin-bottom: 0.25rem;">Total de Execuções</span>
                        <span style="font-size: 1.875rem; font-weight: 900; color: #d1d5db;">{{ $totalGeral }}</span>
                    </div>
                    <div style="text-align: right;">
                        <span style="display: block; font-size: 0.5625rem; color: #6b7280; text-transform: uppercase; font-weight: 900; letter-spacing: 0.08em; margin-bottom: 0.25rem;">Encontradas</span>
                        <span style="font-size: 1.875rem; font-weight: 900; color: {{ $search || $technicianId || $assetId || $weekFilter ? '#f59e0b' : '#6b7280' }};">{{ $totalFiltrado }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Barra de Filtros --}}
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 0.75rem; padding: 1rem; background-color: #1f2937; border-radius: 12px; border: 4px solid #374151;">
            <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                {{-- Input de Busca --}}
                <div style="position: relative; flex: 1; min-width: 250px;">
                    <input wire:model.live.debounce.300ms="search"
                           type="text"
                           placeholder="Buscar patrimônio..."
                           style="width: 100%; padding: 0.625rem 0.75rem 0.625rem 2rem; background-color: #111827; border: 1px solid #374151; border-radius: 8px; font-size: 0.75rem; color: #f3f4f6; font-weight: 700; box-sizing: border-box;">
                    @if(!empty($search))
                        <button wire:click="$set('search', '')" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6b7280; cursor: pointer; padding: 0; font-size: 1rem;">
                            ✕
                        </button>
                    @endif
                </div>

                <button wire:click="toggleFiltersPanel"
                        style="display: flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.625rem; font-weight: 900; text-transform: uppercase; border-radius: 8px; border: 2px solid {{ $showFilters ? '#3b82f6' : '#374151' }}; background-color: transparent; color: {{ $showFilters ? '#60a5fa' : '#9ca3af' }}; cursor: pointer; white-space: nowrap; transition: all 0.2s;">
                    🔽 Filtros
                    @if($this->getActiveFilterCount() > 0)
                        <span style="display: inline-flex; align-items: center; justify-content: center; background-color: #f59e0b; color: white; font-size: 0.5rem; font-weight: 900; border-radius: 50%; width: 1rem; height: 1rem;">
                            {{ $this->getActiveFilterCount() }}
                        </span>
                    @endif
                </button>
            </div>

            {{-- Painel de Filtros Avançados --}}
            @if($showFilters)
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; padding-top: 1rem; border-top: 1px solid #374151;">
                    {{-- Filtro por Técnico --}}
                    <div>
                        <label style="display: block; font-size: 0.625rem; font-weight: 900; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem;">Técnico Responsável</label>
                        <select wire:model.live="technicianId" style="width: 100%; padding: 0.625rem; background-color: #111827; border: 1px solid #374151; border-radius: 8px; font-size: 0.75rem; color: #f3f4f6; box-sizing: border-box;">
                            <option value="">Todos</option>
                            @foreach($this->getTechniciansList() as $tech)
                                <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filtro por Equipamento --}}
                    <div>
                        <label style="display: block; font-size: 0.625rem; font-weight: 900; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem;">Equipamento</label>
                        <select wire:model.live="assetId" style="width: 100%; padding: 0.625rem; background-color: #111827; border: 1px solid #374151; border-radius: 8px; font-size: 0.75rem; color: #f3f4f6; box-sizing: border-box;">
                            <option value="">Todos</option>
                            @foreach($this->getAssetsList() as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->patrimonio }} - {{ $asset->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Filtro por Semana --}}
                    <div>
                        <label style="display: block; font-size: 0.625rem; font-weight: 900; color: #9ca3af; text-transform: uppercase; margin-bottom: 0.5rem;">Semana</label>
                        <input wire:model.live="weekFilter"
                               type="week"
                               style="width: 100%; padding: 0.625rem; background-color: #111827; border: 1px solid #374151; border-radius: 8px; font-size: 0.75rem; color: #f3f4f6; box-sizing: border-box;">
                    </div>

                    {{-- Botão Limpar Filtros --}}
                    <div style="display: flex; align-items: flex-end;">
                        <button wire:click="clearFilters()" style="width: 100%; padding: 0.625rem; background-color: #6b7280; border: none; border-radius: 8px; font-size: 0.625rem; font-weight: 900; color: white; cursor: pointer; text-transform: uppercase; transition: background-color 0.2s;">
                            Limpar Filtros
                        </button>
                    </div>
                </div>
            @endif
        </div>

        {{-- Kanban Board --}}
        <div style="display: flex; gap: 1.5rem; overflow-x: auto; padding: 1.5rem; background-color: #111827; border-radius: 12px; border: 4px solid #374151; min-height: 600px;">
            @foreach($this->getVisibleStatuses() as $statusKey => $statusConfig)
                <div style="flex: 0 0 380px; display: flex; flex-direction: column; background-color: #1f2937; border-radius: 8px; border: 2px solid {{ $statusConfig['color'] }}; overflow: hidden;">
                    {{-- Cabeçalho da Coluna --}}
                    <div style="background-color: {{ $statusConfig['color'] }}; padding: 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="font-size: 0.875rem; font-weight: 900; color: white; margin: 0; text-transform: uppercase;">{{ $statusConfig['title'] }}</h3>
                            <p style="font-size: 0.625rem; color: rgba(255,255,255,0.8); margin: 0.25rem 0 0 0;">{{ $allRecords[$statusKey]->count() }} itens</p>
                        </div>
                        <button wire:click="toggleStatusVisibility('{{ $statusKey }}')" style="background: none; border: none; color: white; cursor: pointer; font-size: 1.25rem; padding: 0;">
                            👁️
                        </button>
                    </div>

                    {{-- Cards da Coluna --}}
                    <div style="flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        @forelse($allRecords[$statusKey] as $execution)
                            <div style="background-color: #111827; border-radius: 8px; padding: 0.75rem; border-left: 4px solid {{ $statusConfig['color'] }}; cursor: move;">
                                {{-- Patrimônio e Plano --}}
                                <div style="font-size: 0.75rem; font-weight: 900; color: #f3f4f6; margin-bottom: 0.5rem;">
                                    {{ $execution->asset?->patrimonio ?? 'N/A' }}
                                </div>
                                <div style="font-size: 0.625rem; color: #9ca3af; margin-bottom: 0.5rem;">
                                    {{ $execution->maintenancePlan?->name ?? 'Sem Plano' }}
                                </div>

                                {{-- Técnico --}}
                                @if($execution->technician)
                                    <div style="font-size: 0.625rem; color: #60a5fa; margin-bottom: 0.25rem;">
                                        👤 {{ $execution->technician->name }}
                                    </div>
                                @endif

                                {{-- Horímetro --}}
                                <div style="font-size: 0.625rem; color: #10b981; padding-top: 0.5rem; border-top: 1px solid #374151;">
                                    H: {{ number_format($execution->horimetro_at_execution, 2) }}
                                    @if($execution->next_due_horimetro)
                                        / {{ number_format($execution->next_due_horimetro, 2) }}
                                    @endif
                                </div>

                                {{-- Data --}}
                                <div style="font-size: 0.625rem; color: #6b7280; margin-top: 0.5rem;">
                                    {{ $execution->created_at?->format('d/m/Y H:i') }}
                                </div>
                            </div>
                        @empty
                            <div style="text-align: center; padding: 2rem 1rem; color: #6b7280; font-size: 0.75rem;">
                                Nenhuma execução
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

    </div>

    <style>
        div::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        div::-webkit-scrollbar-track {
            background: #1f2937;
        }
        div::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 4px;
        }
        div::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
    </style>
</x-filament-panels::page>
