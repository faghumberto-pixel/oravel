<x-filament-panels::page>
    {{-- JS do drag-and-drop (SortableJS) só carrega nesta página, não em todo o painel. --}}
    @vite('resources/js/central-kanban.js')

    @php
        $leadsByStage = $this->getLeadsByStage();
        $openStageOptions = collect(\App\Models\SalesLead::stageLabels())
            ->except([\App\Models\SalesLead::STAGE_GANHO, \App\Models\SalesLead::STAGE_PERDIDO]);
        $segmentOptions = \App\Models\Client::nicheLabels();
        $sourceOptions = \App\Models\SalesLead::sourceLabels();
        // Paleta de avatar por responsavel -- por PESSOA, nao por estagio
        // (pedido do usuario: estilo Pipedrive, onde o avatar identifica
        // QUEM e' o dono do negocio, nao em que fase ele esta). Indice fixo
        // via hash do nome, pra cada pessoa sempre cair na mesma cor.
        $avatarPalette = [
            ['bg' => 'bg-blue-100 dark:bg-blue-500/20', 'text' => 'text-blue-700 dark:text-blue-300'],
            ['bg' => 'bg-purple-100 dark:bg-purple-500/20', 'text' => 'text-purple-700 dark:text-purple-300'],
            ['bg' => 'bg-emerald-100 dark:bg-emerald-500/20', 'text' => 'text-emerald-700 dark:text-emerald-300'],
            ['bg' => 'bg-orange-100 dark:bg-orange-500/20', 'text' => 'text-orange-700 dark:text-orange-300'],
            ['bg' => 'bg-pink-100 dark:bg-pink-500/20', 'text' => 'text-pink-700 dark:text-pink-300'],
            ['bg' => 'bg-cyan-100 dark:bg-cyan-500/20', 'text' => 'text-cyan-700 dark:text-cyan-300'],
            ['bg' => 'bg-amber-100 dark:bg-amber-500/20', 'text' => 'text-amber-700 dark:text-amber-300'],
        ];
    @endphp

    {{--
        Estilo Pipedrive, pedido do usuario 2026-07-28: header da coluna com
        valor total em pipeline (nao so' contagem), card com avatar do
        responsavel (por PESSOA, ver $avatarPalette acima), drag-and-drop
        suave via SortableJS (resources/js/central-kanban.js) em vez do HTML5
        drag nativo puro da 1a versao -- essa lib da' a animacao/ghost/
        "trava visual" na coluna Ganho que HTML5 puro nao dava.

        Cores continuam vindo de App\Support\CrmPalette (fonte unica) --
        tailwind.config.js escaneia app/**\/*.php pra essas classes
        compilarem de verdade (ver comentario la').
    --}}
    <div class="flex flex-row gap-4 overflow-x-auto pb-4 custom-scrollbar min-h-[70vh]">
        @foreach($this->getColumns() as $stageId => $stageLabel)
            @php
                $leads = $leadsByStage->get($stageId, collect());
                $colors = \App\Support\CrmPalette::stage($stageId);
                $isOpenColumn = $stageId !== \App\Models\SalesLead::STAGE_GANHO;
                $stageTotal = $leads->sum('estimated_contract_value');
            @endphp

            <div class="flex-1 min-w-[260px] max-w-[300px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col shadow-sm overflow-hidden">
                <div class="{{ $colors['bg'] }} px-3.5 py-3 shadow-sm">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-[11px] font-black uppercase tracking-wide text-white leading-tight">{{ $stageLabel }}</h3>
                        <span class="text-[11px] text-white bg-white/20 rounded-full px-2 py-0.5 font-black shrink-0">{{ $leads->count() }}</span>
                    </div>
                    @if($stageTotal > 0)
                        <p class="text-[13px] font-black text-white/95 mt-1 leading-tight">
                            R$ {{ number_format($stageTotal, 0, ',', '.') }}
                        </p>
                    @endif
                </div>

                <div
                    x-data
                    x-init="initKanbanColumn($el, $wire, @js($stageId), {{ $isOpenColumn ? 'true' : 'false' }})"
                    data-stage="{{ $stageId }}"
                    data-accepts-drop="{{ $isOpenColumn ? '1' : '0' }}"
                    class="p-2.5 space-y-2.5 flex-1 max-h-[68vh] overflow-y-auto vertical-scrollbar min-h-[80px]"
                >
                    @forelse($leads as $lead)
                        @php
                            $segColors = \App\Support\CrmPalette::segment($lead->segment);
                            $repName = $lead->assignedUser?->name;
                            $repInitials = $repName ? collect(explode(' ', $repName))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') : '?';
                            $avatarColors = $avatarPalette[crc32($repName ?? 'sem-responsavel') % count($avatarPalette)];
                        @endphp
                        <div
                            wire:key="kanban-card-{{ $lead->id }}-{{ $lead->pipeline_stage }}"
                            data-lead-id="{{ $lead->id }}"
                            data-closed="{{ $lead->isOpen() ? '0' : '1' }}"
                            class="bg-white dark:bg-gray-900 p-3.5 rounded-lg border-l-4 {{ $colors['border'] }} border-t border-r border-b border-gray-200 dark:border-gray-700 hover:shadow-lg transition-shadow shadow-sm group {{ $lead->isOpen() ? 'cursor-grab' : '' }}"
                        >
                            <div class="flex justify-between items-start mb-2 gap-2">
                                <span class="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-wide {{ $segColors['text'] }} {{ $segColors['soft'] }} rounded-full px-2 py-0.5 truncate max-w-[100px]">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $segColors['dot'] }} shrink-0"></span>
                                    <span class="truncate">{{ $segmentOptions[$lead->segment] ?? $lead->segment ?? 'Sem segmento' }}</span>
                                </span>
                                <div
                                    class="w-6 h-6 rounded-full {{ $avatarColors['bg'] }} {{ $avatarColors['text'] }} flex items-center justify-center text-[10px] font-black shrink-0"
                                    title="{{ $repName ?? 'Sem responsável' }}"
                                >
                                    {{ $repInitials }}
                                </div>
                            </div>

                            <a href="{{ \App\Filament\Central\Resources\SalesLeadResource::getUrl('edit', ['record' => $lead]) }}" class="block">
                                <h4 class="text-base font-black text-gray-900 dark:text-gray-50 leading-tight mb-1.5 group-hover:{{ $colors['text'] }} transition-colors">
                                    {{ $lead->company_name }}
                                </h4>

                                @if($lead->critical_pain)
                                    <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mb-2 line-clamp-2">{{ $lead->critical_pain }}</p>
                                @endif

                                @if($lead->estimated_contract_value)
                                    <p class="text-[15px] font-black {{ $colors['text'] }} mb-1.5">
                                        R$ {{ number_format($lead->estimated_contract_value, 0, ',', '.') }}
                                    </p>
                                @endif
                            </a>

                            <div class="flex items-center justify-between gap-2 pt-2 mt-1 border-t border-gray-100 dark:border-gray-800">
                                <input
                                    type="text"
                                    list="segment-options-{{ $lead->id }}"
                                    value="{{ $segmentOptions[$lead->segment] ?? $lead->segment }}"
                                    placeholder="Segmento"
                                    wire:key="segment-input-{{ $lead->id }}-{{ $lead->segment }}"
                                    wire:change="updateSegment('{{ $lead->id }}', $event.target.value)"
                                    class="text-[9px] uppercase font-bold rounded bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-0 py-0.5 px-1.5 focus:ring-1 focus:ring-primary-500 w-20"
                                />
                                <datalist id="segment-options-{{ $lead->id }}">
                                    @foreach($segmentOptions as $segmentLabel)
                                        <option value="{{ $segmentLabel }}"></option>
                                    @endforeach
                                </datalist>
                                <input
                                    type="text"
                                    list="source-options-{{ $lead->id }}"
                                    value="{{ $sourceOptions[$lead->source] ?? $lead->source }}"
                                    placeholder="Origem"
                                    wire:key="source-input-{{ $lead->id }}-{{ $lead->source }}"
                                    wire:change="updateSource('{{ $lead->id }}', $event.target.value)"
                                    class="text-[9px] font-bold text-gray-500 dark:text-gray-400 tracking-wide bg-transparent border-0 py-0 px-0 focus:ring-0 text-right w-20"
                                />
                                <datalist id="source-options-{{ $lead->id }}">
                                    @foreach($sourceOptions as $sourceLabel)
                                        <option value="{{ $sourceLabel }}"></option>
                                    @endforeach
                                </datalist>
                            </div>

                            <div class="flex items-center justify-between gap-2 pt-1.5 mt-1">
                                @if($lead->isOpen())
                                    <select
                                        wire:key="stage-select-{{ $lead->id }}-{{ $lead->pipeline_stage }}"
                                        wire:change="moveToStage('{{ $lead->id }}', $event.target.value)"
                                        class="text-[9px] font-black uppercase {{ $colors['text'] }} tracking-wider bg-transparent dark:[color-scheme:dark] border-0 py-0 pl-0 pr-4 focus:ring-0 cursor-pointer"
                                        title="Mover manualmente (ou arraste o card)"
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
                                   class="text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 hover:{{ $colors['text'] }} tracking-wider shrink-0">
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
