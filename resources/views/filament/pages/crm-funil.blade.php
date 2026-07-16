<x-filament-panels::page>
    @php
        $funnelStages = $this->getFunnelStages();
        $grouped = $this->getLeadsByStage();
        $widths = $this->getFunnelWidths();
        $stageColors = [
            'novo' => '#475569',
            'contato_iniciado' => '#2563eb',
            'qualificado' => '#f59e0b',
            'convertido' => '#059669',
        ];
        $topCount = max(1, $grouped->get(array_key_first($funnelStages), collect())->count());
        $perdidoLeads = $grouped->get(\App\Models\CrmLead::STAGE_PERDIDO, collect());
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3 justify-between">
        <input
            type="text"
            wire:model.live.debounce.400ms="search"
            placeholder="Buscar por nome ou empresa..."
            class="fi-input max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
        />

        <div
            wire:click="selectStage('{{ \App\Models\CrmLead::STAGE_PERDIDO }}')"
            class="cursor-pointer rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-center hover:bg-red-500/20 transition-colors {{ $this->selectedStage === \App\Models\CrmLead::STAGE_PERDIDO ? 'ring-2 ring-red-500' : '' }}"
        >
            <p class="text-[10px] font-black uppercase tracking-widest text-red-400">Perdidos</p>
            <p class="text-xs font-bold text-red-300">{{ $perdidoLeads->count() }} leads · R$ {{ number_format($this->getStageValue($perdidoLeads), 2, ',', '.') }}</p>
        </div>
    </div>

    {{-- Funil (piramide invertida): cada faixa afunila da largura da faixa anterior ate a propria largura --}}
    <div class="mx-auto w-full" style="max-width: 700px;">
        @php $previousWidth = 100; @endphp
        @foreach($funnelStages as $stageId => $stageLabel)
            @php
                $leads = $grouped->get($stageId, collect());
                $width = $widths[$stageId] ?? 20;
                $topLeft = (100 - $previousWidth) / 2;
                $topRight = 100 - $topLeft;
                $bottomLeft = (100 - $width) / 2;
                $bottomRight = 100 - $bottomLeft;
                $conversion = $topCount > 0 ? round(($leads->count() / $topCount) * 100) : 0;
            @endphp

            <div
                wire:click="selectStage('{{ $stageId }}')"
                wire:key="funnel-band-{{ $stageId }}"
                class="relative flex flex-col items-center justify-center text-center cursor-pointer transition-opacity hover:opacity-90 {{ $this->selectedStage === $stageId ? 'ring-2 ring-white' : '' }}"
                style="height: 84px; margin-bottom: 3px; background-color: {{ $stageColors[$stageId] }}; clip-path: polygon({{ $topLeft }}% 0, {{ $topRight }}% 0, {{ $bottomRight }}% 100%, {{ $bottomLeft }}% 100%);"
            >
                <span class="text-xs font-black uppercase tracking-widest text-white">{{ $stageLabel }}</span>
                <span class="text-[11px] font-bold text-white/90 mt-0.5">
                    {{ $leads->count() }} leads · R$ {{ number_format($this->getStageValue($leads), 2, ',', '.') }}
                    @if(!$loop->first)
                        · {{ $conversion }}% do topo
                    @endif
                </span>
            </div>

            @php $previousWidth = $width; @endphp
        @endforeach
    </div>

    {{-- Lista do estagio selecionado --}}
    @if($this->selectedStage)
        @php
            $selectedLabel = $this->getStages()[$this->selectedStage];
            $selectedLeads = $grouped->get($this->selectedStage, collect());
        @endphp

        <div class="mt-6 rounded-xl border border-gray-800/50 bg-gray-900/40 backdrop-blur-sm">
            <div class="p-3 border-b border-gray-800/50 flex items-center justify-between">
                <h3 class="text-xs font-black uppercase tracking-widest text-gray-200">{{ $selectedLabel }} ({{ $selectedLeads->count() }})</h3>
                <button wire:click="selectStage('{{ $this->selectedStage }}')" class="text-[10px] font-bold uppercase text-gray-500 hover:text-white">Fechar</button>
            </div>

            <div class="divide-y divide-gray-800/50">
                @forelse($selectedLeads as $lead)
                    <div wire:key="funnel-lead-{{ $lead->id }}" class="p-3 flex flex-wrap items-center gap-3 justify-between">
                        <div class="min-w-[180px]">
                            <p class="text-sm font-bold text-gray-100">{{ $lead->name }}</p>
                            @if($lead->company_name)
                                <p class="text-[10px] text-gray-500">{{ $lead->company_name }}</p>
                            @endif
                        </div>

                        <span class="text-xs font-mono font-bold text-primary-400">
                            @if($lead->estimated_value) R$ {{ number_format($lead->estimated_value, 2, ',', '.') }} @else -- @endif
                        </span>

                        <span class="text-[10px] text-gray-500 truncate max-w-[140px]">
                            {{ $lead->assignedUser?->name ? explode(' ', $lead->assignedUser->name)[0] : 'Sem vendedor' }}
                        </span>

                        <div class="flex items-center gap-2">
                            <select
                                x-data
                                x-on:change="
                                    let stage = $event.target.value;
                                    if (! stage) return;
                                    if (stage === '{{ \App\Models\CrmLead::STAGE_PERDIDO }}') {
                                        let motivo = prompt('Motivo da perda:');
                                        if (! motivo) { $event.target.value = ''; return; }
                                        $wire.moveStage('{{ $lead->id }}', stage, motivo);
                                    } else {
                                        $wire.moveStage('{{ $lead->id }}', stage);
                                    }
                                    $event.target.value = '';
                                "
                                class="fi-select rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-[11px] py-1"
                            >
                                <option value="">Mover para...</option>
                                @foreach($this->getStages() as $stageId => $stageLabel)
                                    @if($stageId !== $lead->stage)
                                        <option value="{{ $stageId }}">{{ $stageLabel }}</option>
                                    @endif
                                @endforeach
                            </select>

                            @if($lead->stage === \App\Models\CrmLead::STAGE_CONVERTIDO)
                                @if($lead->client_id)
                                    <a href="{{ \App\Filament\Resources\ClientResource::getUrl('edit', ['record' => $lead->client_id]) }}" class="text-[10px] font-black uppercase text-success-400 hover:text-success-300 tracking-wider transition-colors shrink-0">Ver Cliente</a>
                                @else
                                    <button
                                        wire:click="converterEmCliente('{{ $lead->id }}')"
                                        wire:confirm="Criar um Cliente formal a partir deste Lead? Não cria nenhum Contrato -- isso continua manual."
                                        class="text-[10px] font-black uppercase text-success-400 hover:text-success-300 tracking-wider transition-colors shrink-0"
                                    >Converter em Cliente</button>
                                @endif
                            @endif

                            <a href="{{ \App\Filament\Resources\CrmLeadResource::getUrl('edit', ['record' => $lead]) }}" class="text-[10px] font-black uppercase text-primary-400 hover:text-primary-300 tracking-wider transition-colors shrink-0">Abrir</a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-[10px] text-gray-700 uppercase font-bold italic tracking-wide">Sem leads neste estágio</div>
                @endforelse
            </div>
        </div>
    @endif
</x-filament-panels::page>
