<x-filament-panels::page>
    @php
        $leadsByStage = $this->getLeadsByStage();
        $columnColors = [
            'prospeccao' => 'bg-gray-500',
            'contato_qualificado' => 'bg-amber-500',
            'demonstracao_realizada' => 'bg-sky-500',
            'proposta_enviada' => 'bg-orange-500',
            'ganho' => 'bg-emerald-600',
        ];
    @endphp

    <div class="flex flex-row gap-4 overflow-x-auto pb-4 min-h-[70vh]">
        @foreach($this->getColumns() as $stageId => $stageLabel)
            @php $leads = $leadsByStage->get($stageId, collect()); @endphp

            <div class="flex-1 min-w-[260px] max-w-[300px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col shadow-sm overflow-hidden">
                <div class="{{ $columnColors[$stageId] ?? 'bg-gray-500' }} px-3 py-3 shadow-sm">
                    <h3 class="text-[11px] font-black uppercase tracking-wide text-white leading-tight">{{ $stageLabel }}</h3>
                    <span class="text-[11px] text-white/90 font-bold">{{ $leads->count() }} {{ $leads->count() === 1 ? 'lead' : 'leads' }}</span>
                </div>

                <div class="p-2.5 space-y-2.5 flex-1 max-h-[68vh] overflow-y-auto">
                    @forelse($leads as $lead)
                        <div wire:key="funil-card-{{ $lead->id }}" class="bg-white dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                            <a href="{{ \App\Filament\Central\Resources\SalesLeadResource::getUrl('edit', ['record' => $lead]) }}" class="block hover:text-primary-600">
                                <p class="text-xs font-bold text-gray-900 dark:text-gray-100 truncate">{{ $lead->company_name }}</p>
                            </a>

                            @if($lead->segment)
                                <span class="inline-block mt-1 text-[9px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 rounded px-1.5 py-0.5">
                                    {{ \App\Models\Client::nicheLabels()[$lead->segment] ?? $lead->segment }}
                                </span>
                            @endif

                            @if($lead->critical_pain)
                                <p class="mt-2 text-[10px] text-gray-500 dark:text-gray-400 line-clamp-2">{{ $lead->critical_pain }}</p>
                            @endif

                            <div class="mt-2 flex items-center justify-between text-[10px] text-gray-400">
                                <span>{{ $lead->assignedUser?->name ?? 'Sem responsável' }}</span>
                                @if($lead->estimated_contract_value)
                                    <span class="font-bold text-gray-600 dark:text-gray-300">R$ {{ number_format($lead->estimated_contract_value, 0, ',', '.') }}</span>
                                @endif
                            </div>

                            @if($lead->isOpen() && $lead->nextStage() && $lead->nextStage() !== \App\Models\SalesLead::STAGE_GANHO)
                                <button
                                    wire:click="advance('{{ $lead->id }}')"
                                    class="mt-2.5 w-full text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400 border border-primary-500/40 rounded-md py-1.5 hover:bg-primary-500/10 transition-colors"
                                >
                                    Avançar Estágio
                                </button>
                            @elseif($lead->pipeline_stage === \App\Models\SalesLead::STAGE_PROPOSTA_ENVIADA)
                                <a href="{{ \App\Filament\Central\Resources\SalesLeadResource::getUrl('edit', ['record' => $lead]) }}"
                                   class="mt-2.5 block text-center text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 border border-emerald-500/40 rounded-md py-1.5 hover:bg-emerald-500/10 transition-colors">
                                    Converter em Tenant
                                </a>
                            @endif
                        </div>
                    @empty
                        <p class="text-[10px] text-gray-400 text-center py-6">Nenhum lead nesta etapa.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
