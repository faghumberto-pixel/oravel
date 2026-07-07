<x-filament-panels::page>
    @php
        $stages = $this->getStages();
        $grouped = $this->getLeadsByStage();
        $stageColors = [
            'novo' => 'bg-slate-600',
            'contato_iniciado' => 'bg-blue-600',
            'qualificado' => 'bg-amber-500',
            'convertido' => 'bg-emerald-600',
            'perdido' => 'bg-red-600',
        ];
    @endphp

    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.400ms="search"
            placeholder="Buscar por nome ou empresa..."
            class="fi-input w-full max-w-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm"
        />
    </div>

    <div
        x-data="{ draggingId: null }"
        class="flex flex-row gap-4 overflow-x-auto pb-4 min-h-[70vh]"
    >
        @foreach($stages as $stageId => $stageLabel)
            @php $leads = $grouped->get($stageId, collect()); @endphp

            <div
                x-on:dragover.prevent
                x-on:drop.prevent="
                    if (! draggingId) return;
                    if ('{{ $stageId }}' === 'perdido') {
                        let motivo = prompt('Motivo da perda:');
                        if (! motivo) { draggingId = null; return; }
                        $wire.moveStage(draggingId, '{{ $stageId }}', motivo);
                    } else {
                        $wire.moveStage(draggingId, '{{ $stageId }}');
                    }
                    draggingId = null;
                "
                class="flex-1 min-w-[280px] max-w-[360px] bg-gray-900/40 rounded-xl border border-gray-800/50 flex flex-col backdrop-blur-sm shadow-xl"
            >
                <div class="{{ $stageColors[$stageId] ?? 'bg-gray-600' }} p-3 border-b border-white/10 rounded-t-xl shadow-md">
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-white">{{ $stageLabel }}</h3>
                    <div class="flex items-center justify-between mt-0.5">
                        <span class="text-[10px] text-white/80 font-bold">{{ $leads->count() }} leads</span>
                        <span class="text-[10px] text-white/80 font-bold">R$ {{ number_format($this->getStageValue($leads), 2, ',', '.') }}</span>
                    </div>
                </div>

                <div class="p-3 space-y-3 flex-1 max-h-[65vh] overflow-y-auto bg-gray-950/20">
                    @forelse($leads as $lead)
                        <div
                            wire:key="funil-card-{{ $lead->id }}"
                            draggable="true"
                            x-on:dragstart="draggingId = '{{ $lead->id }}'"
                            x-on:dragend="draggingId = null"
                            class="bg-gray-800 p-3 rounded-lg border border-gray-700 hover:border-primary-500 transition-all shadow-lg cursor-grab active:cursor-grabbing group"
                        >
                            <h4 class="text-sm font-bold text-gray-100 leading-tight mb-0.5 truncate">{{ $lead->name }}</h4>
                            @if($lead->company_name)
                                <p class="text-[10px] text-gray-500 font-semibold mb-2 truncate">{{ $lead->company_name }}</p>
                            @endif

                            @if($lead->estimated_value)
                                <p class="text-xs font-mono font-bold text-primary-400 mb-2">R$ {{ number_format($lead->estimated_value, 2, ',', '.') }}</p>
                            @endif

                            <div class="flex items-center justify-between pt-2 border-t border-gray-700/50">
                                <span class="text-[10px] text-gray-500 font-medium truncate max-w-[140px]">
                                    {{ $lead->assignedUser?->name ? explode(' ', $lead->assignedUser->name)[0] : 'Sem vendedor' }}
                                </span>
                                <a href="{{ \App\Filament\Resources\CrmLeadResource::getUrl('edit', ['record' => $lead]) }}" class="text-[10px] font-black uppercase text-primary-400 hover:text-primary-300 tracking-wider transition-colors shrink-0">Abrir</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-[10px] text-gray-700 uppercase font-bold italic tracking-wide border border-dashed border-gray-800/80 rounded-xl">Sem leads</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
