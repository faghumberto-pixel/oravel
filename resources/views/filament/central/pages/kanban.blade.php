<x-filament-panels::page>
    {{--
        Cores vêm de App\Support\CrmPalette (fonte única, ver comentário lá)
        -- antes cada Blade tinha seu próprio array de classes Tailwind
        literais duplicado; agora só existe aqui via PHP, e
        tailwind.config.js escaneia app/**\/*.php pra compilar mesmo assim.

        Drag-and-drop nativo (HTML5 DnD + Alpine) chamando o MESMO
        Livewire::moveToStage() que o <select> já usava -- pedido do usuário
        2026-07-28 ("mais interativo"). O <select> continua existindo como
        fallback acessível (teclado/leitor de tela), drag é só um atalho
        visual por cima da mesma validação de servidor (SalesLead::
        moveToStage() já rejeita Ganho/Perdido como destino, ver catch()
        abaixo -- soltar lá só mostra a notificação de aviso que já existia).
    --}}
    @php
        $leadsByStage = $this->getLeadsByStage();
        $openStageOptions = collect(\App\Models\SalesLead::stageLabels())
            ->except([\App\Models\SalesLead::STAGE_GANHO, \App\Models\SalesLead::STAGE_PERDIDO]);
        $segmentOptions = \App\Models\Client::nicheLabels();
        $sourceOptions = \App\Models\SalesLead::sourceLabels();
    @endphp

    <div x-data="{ dragOverStage: null }" class="flex flex-row gap-4 overflow-x-auto pb-4 custom-scrollbar min-h-[70vh]">
        @foreach($this->getColumns() as $stageId => $stageLabel)
            @php
                $leads = $leadsByStage->get($stageId, collect());
                $colors = \App\Support\CrmPalette::stage($stageId);
            @endphp

            <div
                class="flex-1 min-w-[230px] max-w-[280px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border flex flex-col shadow-sm overflow-hidden transition-colors"
                :class="dragOverStage === @js($stageId) ? '{{ $colors['border'] }} ring-2 ring-offset-1 dark:ring-offset-gray-900 {{ $colors['ring'] }}' : 'border-gray-200 dark:border-gray-700/60'"
                @dragover.prevent="dragOverStage = @js($stageId)"
                @dragleave="dragOverStage = null"
                @drop.prevent="
                    dragOverStage = null;
                    $wire.moveToStage($event.dataTransfer.getData('text/plain'), @js($stageId));
                "
            >
                <div class="{{ $colors['bg'] }} px-3 py-3 shadow-sm flex items-center justify-between gap-2">
                    <h3 class="text-[11px] font-black uppercase tracking-wide text-white leading-tight">{{ $stageLabel }}</h3>
                    <span class="text-[11px] text-white bg-white/20 rounded-full px-2 py-0.5 font-black shrink-0">{{ $leads->count() }}</span>
                </div>

                <div class="p-2.5 space-y-2.5 flex-1 max-h-[68vh] overflow-y-auto vertical-scrollbar">
                    @forelse($leads as $lead)
                        @php $segColors = \App\Support\CrmPalette::segment($lead->segment); @endphp
                        <div
                            wire:key="funil-card-{{ $lead->id }}-{{ $lead->pipeline_stage }}"
                            @if($lead->isOpen())
                                draggable="true"
                                @dragstart="$event.dataTransfer.setData('text/plain', '{{ $lead->id }}'); $event.dataTransfer.effectAllowed = 'move'"
                                @dragend="dragOverStage = null"
                            @endif
                            class="bg-white dark:bg-gray-900 p-3 rounded-lg border-l-4 {{ $colors['border'] }} border-t border-r border-b border-gray-200 dark:border-gray-700 hover:shadow-md transition-all shadow-sm group {{ $lead->isOpen() ? 'cursor-grab active:cursor-grabbing' : '' }}"
                        >
                            <div class="flex justify-between items-start mb-1.5 gap-2">
                                <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wide {{ $segColors['text'] }} {{ $segColors['soft'] }} rounded-full px-2 py-0.5 truncate max-w-[110px]">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $segColors['dot'] }} shrink-0"></span>
                                    <span class="truncate">{{ $segmentOptions[$lead->segment] ?? $lead->segment ?? 'Sem segmento' }}</span>
                                </span>
                                <input
                                    type="text"
                                    list="segment-options-{{ $lead->id }}"
                                    value="{{ $segmentOptions[$lead->segment] ?? $lead->segment }}"
                                    placeholder="Segmento"
                                    wire:key="segment-input-{{ $lead->id }}-{{ $lead->segment }}"
                                    wire:change="updateSegment('{{ $lead->id }}', $event.target.value)"
                                    class="sr-only focus:not-sr-only text-[9px] uppercase font-bold rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-0 py-0.5 px-1.5 focus:ring-1 focus:ring-primary-500 w-24 text-right"
                                />
                                <datalist id="segment-options-{{ $lead->id }}">
                                    @foreach($segmentOptions as $segmentLabel)
                                        <option value="{{ $segmentLabel }}"></option>
                                    @endforeach
                                </datalist>
                            </div>

                            <a href="{{ \App\Filament\Central\Resources\SalesLeadResource::getUrl('edit', ['record' => $lead]) }}" class="block">
                                <h4 class="text-base font-black text-gray-900 dark:text-gray-50 leading-tight mb-2 group-hover:{{ $colors['text'] }} transition-colors">
                                    {{ $lead->company_name }}
                                </h4>

                                @if($lead->critical_pain)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mb-1.5 line-clamp-2">{{ $lead->critical_pain }}</p>
                                @endif

                                <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mb-1.5 truncate">
                                    {{ $lead->assignedUser?->name ? explode(' ', $lead->assignedUser->name)[0] : 'Sem responsável' }}
                                    @if($lead->estimated_contract_value)
                                        · <span class="{{ $colors['text'] }} font-black">R$ {{ number_format($lead->estimated_contract_value, 0, ',', '.') }}</span>
                                    @endif
                                </p>
                            </a>

                            <div class="flex items-center justify-between gap-2 pt-1.5 mt-1 border-t border-gray-100 dark:border-gray-800">
                                <span class="text-[9px] uppercase font-bold text-gray-400 dark:text-gray-600 shrink-0">Origem</span>
                                <input
                                    type="text"
                                    list="source-options-{{ $lead->id }}"
                                    value="{{ $sourceOptions[$lead->source] ?? $lead->source }}"
                                    placeholder="Origem"
                                    wire:key="source-input-{{ $lead->id }}-{{ $lead->source }}"
                                    wire:change="updateSource('{{ $lead->id }}', $event.target.value)"
                                    class="text-[10px] font-bold text-gray-600 dark:text-gray-300 tracking-wide bg-transparent border-0 py-0 px-0 focus:ring-0 text-right w-24"
                                />
                                <datalist id="source-options-{{ $lead->id }}">
                                    @foreach($sourceOptions as $sourceLabel)
                                        <option value="{{ $sourceLabel }}"></option>
                                    @endforeach
                                </datalist>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-2 mt-1 border-t border-gray-100 dark:border-gray-800">
                                @if($lead->isOpen())
                                    <select
                                        wire:key="stage-select-{{ $lead->id }}-{{ $lead->pipeline_stage }}"
                                        wire:change="moveToStage('{{ $lead->id }}', $event.target.value)"
                                        class="text-[10px] font-black uppercase {{ $colors['text'] }} tracking-wider bg-transparent dark:[color-scheme:dark] border-0 py-0 pl-0 pr-5 focus:ring-0 cursor-pointer"
                                    >
                                        @foreach($openStageOptions as $optStageId => $optStageLabel)
                                            <option
                                                value="{{ $optStageId }}"
                                                @selected($optStageId === $lead->pipeline_stage)
                                                class="bg-white text-gray-900 dark:bg-gray-800 dark:text-gray-100"
                                            >{{ $optStageLabel }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <span></span>
                                @endif

                                <a href="{{ \App\Filament\Central\Resources\SalesLeadResource::getUrl('edit', ['record' => $lead]) }}"
                                   class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500 hover:{{ $colors['text'] }} tracking-wider shrink-0">
                                    Editar
                                </a>
                            </div>

                            @if($lead->pipeline_stage === \App\Models\SalesLead::STAGE_PROPOSTA_ENVIADA)
                                <span class="flex items-center gap-1 text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400 tracking-wider mt-1.5">
                                    <x-heroicon-s-check-badge class="w-3.5 h-3.5" />
                                    Pronto pra converter
                                </span>
                            @endif
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
