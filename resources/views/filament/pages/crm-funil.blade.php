<x-filament-panels::page>
    @vite('resources/js/crm-kanban.js')

    @php
        $rows = $this->getFunnelStages();
        $colunas = $this->getColunas();
        $funis = $this->getFunis();
        $lostCount = $this->getLostCount();
        $conversionRate = $this->getConversionRate();
        $openPipelineValue = $this->getOpenPipelineValue();
        $weightedValue = $this->getWeightedPipelineValue();
        $averageTicket = $this->getAverageTicket();
        $conversionColorClass = match (true) {
            $conversionRate === null => 'text-gray-400 dark:text-gray-500',
            $conversionRate >= 20 => 'text-emerald-600 dark:text-emerald-400',
            $conversionRate >= 10 => 'text-amber-600 dark:text-amber-400',
            default => 'text-red-600 dark:text-red-400',
        };
        $btn = 'px-3 py-1.5 text-xs font-bold rounded-lg border transition-colors';
    @endphp

    <div class="flex flex-wrap items-center gap-3 mb-4">
        @if($funis->count() > 1)
            <select wire:change="trocarFunil($event.target.value)" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                @foreach($funis as $f)
                    <option value="{{ $f->id }}" @selected($f->id === $this->funilId)>{{ $f->nome }}</option>
                @endforeach
            </select>
        @endif
        <div class="inline-flex gap-1">
            <button type="button" wire:click="setVisao('kanban')" class="{{ $btn }} {{ $this->visao === 'kanban' ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">Kanban</button>
            <button type="button" wire:click="setVisao('piramide')" class="{{ $btn }} {{ $this->visao === 'piramide' ? 'bg-primary-600 text-white border-primary-600' : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300' }}">Pirâmide</button>
        </div>
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar lead ou empresa" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 w-64" />
    </div>

    <div class="{{ $this->visao === 'kanban' ? 'w-full' : 'max-w-5xl mx-auto' }}">
        @if($this->visao === 'kanban')
        <div class="flex flex-row gap-4 overflow-x-auto pb-4 min-h-[50vh] max-w-none">
            @foreach($colunas as $col)
                @php
                    $etapa = $col['etapa'];
                    $cores = \App\Support\CrmPalette::etapa($etapa);
                @endphp
                <div class="flex-1 min-w-[250px] max-w-[300px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col shadow-sm overflow-hidden" wire:key="coluna-{{ $etapa->id }}">
                    <div class="{{ $cores['bg'] }} px-3.5 py-3">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-[11px] font-black uppercase tracking-wide text-white leading-tight">{{ $etapa->nome }}</h3>
                            <span class="text-[11px] text-white bg-white/20 rounded-full px-2 py-0.5 font-black shrink-0">{{ $col['count'] }}</span>
                        </div>
                        <p class="text-[13px] font-black text-white/95 mt-1 leading-tight">R$ {{ number_format($col['total'], 0, ',', '.') }}</p>
                        @if($etapa->isAberta())
                            <p class="text-[10px] font-bold text-white/80 leading-tight">Ponderado ({{ $etapa->probabilidade }}%): R$ {{ number_format($col['ponderado'], 0, ',', '.') }}</p>
                        @endif
                    </div>

                    <div
                        x-data
                        x-init="initCrmKanbanColumn($el, $wire)"
                        data-etapa="{{ $etapa->id }}"
                        class="p-2.5 space-y-2.5 flex-1 max-h-[62vh] overflow-y-auto min-h-[80px]"
                    >
                        @foreach($col['leads'] as $lead)
                            <div
                                wire:key="card-{{ $lead->id }}-{{ $etapa->id }}"
                                data-lead-id="{{ $lead->id }}"
                                data-closed="{{ $lead->isOpen() ? '0' : '1' }}"
                                class="bg-white dark:bg-gray-900 p-3 rounded-lg border-l-4 {{ $cores['border'] }} border-t border-r border-b border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow shadow-sm {{ $lead->isOpen() ? 'cursor-grab' : '' }}"
                            >
                                <a href="{{ \App\Filament\Resources\CrmLeadResource::getUrl('edit', ['record' => $lead]) }}" class="block">
                                    <h4 class="text-sm font-black text-gray-900 dark:text-gray-50 leading-tight">{{ $lead->company_name ?: $lead->name }}</h4>
                                    @if($lead->company_name)
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $lead->name }}</p>
                                    @endif
                                    @if($lead->estimated_value)
                                        <p class="text-sm font-black {{ $cores['text'] }} mt-1">R$ {{ number_format($lead->estimated_value, 0, ',', '.') }}</p>
                                    @endif
                                </a>
                                <div class="flex items-center justify-between gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-gray-800">
                                    <span class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ $lead->assignedUser?->name ?? 'Sem responsável' }}</span>
                                    <select wire:change="moverLead('{{ $lead->id }}', $event.target.value)" class="text-[10px] rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 py-0.5 pl-1 pr-5" aria-label="Mover para outra etapa">
                                        @foreach($colunas as $opt)
                                            <option value="{{ $opt['etapa']->id }}" @selected($opt['etapa']->id === $etapa->id)>{{ $opt['etapa']->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        @endif

        @if($this->visao === 'piramide')
        <div class="flex flex-col items-center">
            @foreach($rows as $row)
                @php
                    $top = $row['topWidth'];
                    $bottom = $row['bottomWidth'];
                    $clipPath = 'polygon('
                        . (50 - $top / 2) . '% 0%, '
                        . (50 + $top / 2) . '% 0%, '
                        . (50 + $bottom / 2) . '% 100%, '
                        . (50 - $bottom / 2) . '% 100%)';
                    $bandColor = \App\Support\CrmPalette::etapa($row['etapa'])['bg'];
                @endphp
                <button
                    wire:click="selectStage('{{ $row['stage'] }}')"
                    class="relative block w-full h-[150px] -mt-px group {{ $bandColor }} hover:brightness-110 transition-[filter] border-none cursor-pointer"
                    style="clip-path: {{ $clipPath }};"
                    title="Ver leads em {{ $row['label'] }}"
                >
                    <div class="relative h-full flex flex-col items-center justify-center text-center pointer-events-none px-6">
                        <span class="text-base font-black uppercase tracking-wide text-white leading-tight">{{ $row['label'] }}</span>
                        <span class="text-4xl font-black text-white leading-tight mt-1">{{ $row['count'] }}</span>
                        <span class="text-xs font-bold uppercase tracking-wide text-white/0 group-hover:text-white/80 transition-colors leading-tight mt-1">Ver leads →</span>
                    </div>
                </button>
            @endforeach
        </div>

        @endif

        <div class="mt-8 grid grid-cols-2 md:grid-cols-5 gap-4 max-w-5xl mx-auto">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-banknotes class="w-3.5 h-3.5 text-emerald-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Em Pipeline</p>
                </div>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                    R$ {{ number_format($openPipelineValue, 0, ',', '.') }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Soma dos leads abertos</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-scale class="w-3.5 h-3.5 text-indigo-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Ponderado</p>
                </div>
                <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400">R$ {{ number_format($weightedValue, 0, ',', '.') }}</p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Valor × probabilidade da etapa</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-arrow-trending-up class="w-3.5 h-3.5 {{ $conversionColorClass }}" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Taxa de Conversão</p>
                </div>
                <p class="text-2xl font-black {{ $conversionColorClass }}">
                    {{ $conversionRate !== null ? number_format($conversionRate, 1, ',', '.').'%' : '—' }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Ganhos ÷ leads do funil</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-calculator class="w-3.5 h-3.5 text-blue-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Ticket Médio</p>
                </div>
                <p class="text-2xl font-black text-blue-600 dark:text-blue-400">
                    {{ $averageTicket !== null ? 'R$ '.number_format($averageTicket, 0, ',', '.') : '—' }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Média dos leads convertidos</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-x-circle class="w-3.5 h-3.5 text-red-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Perdidos</p>
                </div>
                <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ $lostCount }}</p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Fora do funil acima</p>
            </div>
        </div>

        <!-- Lista de Leads do Estágio Selecionado -->
        @if($this->visao === 'piramide' && $this->selectedStage)
            <div class="mt-8 max-w-4xl mx-auto">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 p-4 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                                {{ $this->getSelectedStageLabel() }}
                                <span class="ml-2 text-2xl font-black text-gray-600 dark:text-gray-400">({{ $this->getSelectedStageLeads()->count() }})</span>
                            </h3>
                            <button
                                wire:click="selectStage('{{ $this->selectedStage }}')"
                                class="text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white"
                            >
                                ✕ Fechar
                            </button>
                        </div>
                    </div>

                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->getSelectedStageLeads() as $lead)
                            <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-white truncate">{{ $lead->name }}</p>
                                        @if($lead->company_name)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ $lead->company_name }}</p>
                                        @endif
                                        @if($lead->phone)
                                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">{{ $lead->phone }}</p>
                                        @endif
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        @if($lead->estimated_value)
                                            <p class="font-bold text-gray-900 dark:text-white">
                                                R$ {{ number_format($lead->estimated_value, 0, ',', '.') }}
                                            </p>
                                        @else
                                            <p class="font-bold text-gray-400 dark:text-gray-600">—</p>
                                        @endif
                                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                            {{ $lead->created_at->format('d/m/Y') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                                <p>Nenhum lead neste estágio</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($this->mostrarPerda)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 p-5 shadow-xl space-y-3">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Motivo da perda</h3>
                <select wire:model="perdaMotivo" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Selecione o motivo</option>
                    @foreach($this->getMotivosPerda() as $slug => $nome)
                        <option value="{{ $slug }}">{{ $nome }}</option>
                    @endforeach
                </select>
                <textarea wire:model="perdaDetalhe" rows="3" placeholder="Detalhe (obrigatório para alguns motivos, ex.: qual concorrente)" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800"></textarea>
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="cancelarPerda" class="{{ $btn }} border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300">Cancelar</button>
                    <button type="button" wire:click="confirmarPerda" class="{{ $btn }} bg-red-600 text-white border-red-600">Marcar como perdido</button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
