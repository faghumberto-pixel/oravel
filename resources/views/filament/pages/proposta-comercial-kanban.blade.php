<x-filament-panels::page>
    @php
        $columns = $this->getColumns();
        $records = $this->getRecords();
        $colorMap = [
            'rascunho' => 'bg-gray-500',
            'enviada_para_comercial' => 'bg-blue-600',
            'aprovada_interna' => 'bg-amber-500',
            'aceita_pelo_cliente' => 'bg-emerald-600',
            'recusada_pelo_cliente' => 'bg-red-600',
            'rejeitada' => 'bg-red-700',
        ];
    @endphp

    <div class="flex flex-row gap-4 overflow-x-auto pb-4 custom-scrollbar min-h-[70vh]">
        @foreach($columns as $statusId => $statusLabel)
            @php
                $columnRecords = $records->get($statusId, collect());
                $headerBg = $colorMap[$statusId] ?? 'bg-gray-500';
            @endphp

            <div class="flex-1 min-w-[260px] max-w-[300px] bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200 dark:border-gray-700/60 flex flex-col shadow-sm overflow-hidden">
                <div class="{{ $headerBg }} px-3 py-3 shadow-sm">
                    <h3 class="text-[11px] font-black uppercase tracking-wide text-white leading-tight">{{ $statusLabel }}</h3>
                    <span class="text-[11px] text-white/90 font-bold">{{ $columnRecords->count() }} proposta(s)</span>
                </div>

                <div class="p-2.5 space-y-2.5 flex-1 max-h-[65vh] overflow-y-auto vertical-scrollbar">
                    @forelse($columnRecords as $proposta)
                        <div wire:key="proposta-kanban-card-{{ $proposta->id }}"
                             class="block bg-white dark:bg-gray-900 p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:shadow-md hover:border-blue-400 dark:hover:border-blue-500 transition-all shadow-sm group">
                        <a href="{{ \App\Filament\Resources\PropostaComercialResource::getUrl('view', ['record' => $proposta]) }}" class="block">
                            @if($proposta->solicitacao_locacao_id)
                                <div class="flex items-center gap-1.5 mb-2 -mt-1 -mx-1 px-2 py-1 rounded bg-emerald-500/10 border border-emerald-500/40">
                                    <x-heroicon-s-check-badge class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                    <span class="text-[9px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Equipamento já solicitado</span>
                                </div>
                            @endif

                            <h4 class="text-sm font-black text-gray-900 dark:text-gray-50 leading-tight mb-1 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors truncate">
                                {{ $proposta->client?->name ?? 'Cliente não definido' }}
                            </h4>

                            <p class="text-[11px] text-gray-500 dark:text-gray-400 font-semibold mb-1.5 truncate">
                                Vendedor: {{ $proposta->sellerUser?->name ?? '—' }}
                            </p>

                            <div class="flex items-center justify-between pt-2 mt-1 border-t border-gray-100 dark:border-gray-800">
                                <span class="text-[12px] font-black text-gray-700 dark:text-gray-300">
                                    R$ {{ number_format($proposta->total_value, 2, ',', '.') }}
                                </span>
                                <span class="text-[10px] font-black uppercase text-blue-600 dark:text-blue-400 tracking-wider shrink-0">Abrir</span>
                            </div>
                        </a>
                            @if($proposta->status === \App\Models\PropostaComercial::STATUS_RASCUNHO && auth()->user()?->can('update', $proposta))
                                <button type="button" wire:click="enviar('{{ $proposta->id }}')" wire:confirm="Enviar esta proposta ao Comercial?"
                                        class="mt-2 w-full rounded-md bg-emerald-600 px-2 py-1.5 text-[11px] font-black uppercase tracking-wide text-white hover:bg-emerald-700">
                                    Enviar ao Comercial
                                </button>
                            @endif
                            @if($proposta->status === \App\Models\PropostaComercial::STATUS_APROVADA_INTERNA && ($whatsapp = $proposta->linkWhatsapp()))
                                <a href="{{ $whatsapp }}" target="_blank" rel="noopener"
                                   class="mt-2 block w-full rounded-md bg-emerald-600 px-2 py-1.5 text-center text-[11px] font-black uppercase tracking-wide text-white hover:bg-emerald-700">
                                    Enviar por WhatsApp
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="text-center py-10 text-[10px] text-gray-400 dark:text-gray-600 uppercase font-bold italic tracking-wide border border-dashed border-gray-300 dark:border-gray-700 rounded-xl">Sem propostas</div>
                    @endforelse
                </div>
            </div>
        @endforeach
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
    </style>
</x-filament-panels::page>
