<x-filament-panels::page>
    {{--
        Classes de cor literais aqui de proposito, nao vindas so' da classe
        PHP da Page -- tailwind.config.js so escaneia .blade.php (nao
        app/**/*.php), entao uma classe so e' compilada se aparecer como
        texto literal em algum arquivo escaneado. MaintenanceKanban usa
        str_replace('bg-','border-',...) em runtime e SO "funciona" pra
        border-blue-600 por coincidencia (aparece literal num botao
        daquele mesmo arquivo) -- as outras cores dele nunca foram
        realmente compiladas. Aqui cada uma fica literal, sem depender
        de coincidencia nenhuma.
    --}}
    @php
        $leadsByStage = $this->getLeadsByStage();
        $columnColors = [
            'prospeccao' => 'bg-slate-600',
            'contato_qualificado' => 'bg-blue-600',
            'demonstracao_realizada' => 'bg-purple-600',
            'proposta_enviada' => 'bg-orange-500',
            'ganho' => 'bg-emerald-600',
        ];
        $columnBorderColors = [
            'prospeccao' => 'border-slate-600',
            'contato_qualificado' => 'border-blue-600',
            'demonstracao_realizada' => 'border-purple-600',
            'proposta_enviada' => 'border-orange-500',
            'ganho' => 'border-emerald-600',
        ];
    @endphp

    <div class="flex flex-row gap-4 overflow-x-auto pb-4 custom-scrollbar min-h-[70vh]">
        @foreach($this->getColumns() as $stageId => $stageLabel)
            @php
                $leads = $leadsByStage->get($stageId, collect());
                $headerBg = $columnColors[$stageId] ?? 'bg-gray-600';
                $sideBorder = $columnBorderColors[$stageId] ?? 'border-gray-600';
            @endphp

            <div class="flex-1 min-w-[230px] max-w-[280px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col shadow-sm overflow-hidden">
                <div class="{{ $headerBg }} px-3 py-3 shadow-sm">
                    <h3 class="text-[11px] font-black uppercase tracking-wide text-white leading-tight">{{ $stageLabel }}</h3>
                    <span class="text-[11px] text-white/90 font-bold">{{ $leads->count() }} {{ $leads->count() === 1 ? 'lead' : 'leads' }}</span>
                </div>

                <div class="p-2.5 space-y-2.5 flex-1 max-h-[68vh] overflow-y-auto vertical-scrollbar">
                    @forelse($leads as $lead)
                        <div wire:key="funil-card-{{ $lead->id }}"
                             class="bg-white dark:bg-gray-900 p-3 rounded-lg border-l-4 {{ $sideBorder }} border-t border-r border-b border-gray-200 dark:border-gray-700 hover:shadow-md transition-all shadow-sm group">
                            <a href="{{ \App\Filament\Central\Resources\SalesLeadResource::getUrl('edit', ['record' => $lead]) }}" class="block">
                                <div class="flex justify-between items-start mb-1.5 gap-2">
                                    <span class="text-[11px] font-mono font-bold text-gray-400 dark:text-gray-500 truncate max-w-[110px]">
                                        #{{ substr($lead->id, 0, 8) }}
                                    </span>
                                    @if($lead->segment)
                                        <span class="text-[9px] uppercase font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 shrink-0">
                                            {{ \App\Models\Client::nicheLabels()[$lead->segment] ?? $lead->segment }}
                                        </span>
                                    @endif
                                </div>

                                <h4 class="text-base font-black text-gray-900 dark:text-gray-50 leading-tight mb-2 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                    {{ $lead->company_name }}
                                </h4>

                                @if($lead->critical_pain)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mb-1.5 line-clamp-2">{{ $lead->critical_pain }}</p>
                                @endif

                                <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mb-1.5 truncate">
                                    {{ $lead->assignedUser?->name ? explode(' ', $lead->assignedUser->name)[0] : 'Sem responsável' }}
                                    @if($lead->estimated_contract_value)
                                        · <span class="text-gray-700 dark:text-gray-300">R$ {{ number_format($lead->estimated_contract_value, 0, ',', '.') }}</span>
                                    @endif
                                </p>
                            </a>

                            <div class="flex items-center justify-between pt-2 mt-1 border-t border-gray-100 dark:border-gray-800">
                                @if($lead->isOpen() && $lead->nextStage() && $lead->nextStage() !== \App\Models\SalesLead::STAGE_GANHO)
                                    <button
                                        wire:click="advance('{{ $lead->id }}')"
                                        class="flex items-center gap-1 text-[10px] font-black uppercase text-primary-600 dark:text-primary-400 tracking-wider"
                                    >
                                        <x-heroicon-s-arrow-right-circle class="w-3.5 h-3.5" />
                                        Avançar
                                    </button>
                                @elseif($lead->pipeline_stage === \App\Models\SalesLead::STAGE_PROPOSTA_ENVIADA)
                                    <span class="flex items-center gap-1 text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400 tracking-wider">
                                        <x-heroicon-s-check-badge class="w-3.5 h-3.5" />
                                        Pronto pra converter
                                    </span>
                                @else
                                    <span></span>
                                @endif

                                <a href="{{ \App\Filament\Central\Resources\SalesLeadResource::getUrl('edit', ['record' => $lead]) }}"
                                   class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 tracking-wider shrink-0">
                                    Editar
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-[10px] text-gray-400 dark:text-gray-600 uppercase font-bold italic tracking-wide border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">
                            Sem leads
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
