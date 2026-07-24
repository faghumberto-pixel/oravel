<x-filament-panels::page>
    @php
        $record = $this->getRecord();
        $eventsByDay = $this->getEventsByDay();

        $sources = [
            'comercial' => ['label' => 'Comercial', 'text' => 'text-indigo-600 dark:text-indigo-400', 'chip' => 'bg-indigo-50 dark:bg-indigo-500/10', 'border' => 'border-indigo-500', 'dot' => 'bg-indigo-500'],
            'manutencao' => ['label' => 'Manutenção', 'text' => 'text-amber-600 dark:text-amber-400', 'chip' => 'bg-amber-50 dark:bg-amber-500/10', 'border' => 'border-amber-500', 'dot' => 'bg-amber-500'],
            'logistica' => ['label' => 'Logística', 'text' => 'text-sky-600 dark:text-sky-400', 'chip' => 'bg-sky-50 dark:bg-sky-500/10', 'border' => 'border-sky-500', 'dot' => 'bg-sky-500'],
            'portaria' => ['label' => 'Portaria', 'text' => 'text-violet-600 dark:text-violet-400', 'chip' => 'bg-violet-50 dark:bg-violet-500/10', 'border' => 'border-violet-500', 'dot' => 'bg-violet-500'],
        ];

        $statusLabels = \App\Models\SolicitacaoLocacao::statusComercialLabels();
        $assetNames = $record->assetIds()->isNotEmpty()
            ? \App\Models\Asset::whereIn('id', $record->assetIds())->pluck('name')->implode(', ')
            : ($record->category?->name ? 'Categoria: '.$record->category->name : '—');
    @endphp

    <div class="max-w-3xl mx-auto space-y-6">

        {{-- ===================== RESUMO DA SOLICITAÇÃO ===================== --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-1">Solicitação de Locação</p>
                    <h2 class="text-lg font-black text-gray-900 dark:text-white">{{ $record->customer?->name ?? 'Cliente não informado' }}</h2>
                </div>
                <span class="text-[10.5px] font-bold uppercase tracking-wide px-2.5 py-1 rounded-md {{ $sources['comercial']['chip'] }} {{ $sources['comercial']['text'] }}">
                    {{ $statusLabels[$record->status_comercial] ?? $record->status_comercial }}
                </span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-[12.5px]">
                <div>
                    <p class="text-gray-400 dark:text-gray-500 uppercase text-[10px] font-bold tracking-wide mb-0.5">Equipamento</p>
                    <p class="text-gray-700 dark:text-gray-300 font-semibold">{{ $assetNames }}</p>
                </div>
                <div>
                    <p class="text-gray-400 dark:text-gray-500 uppercase text-[10px] font-bold tracking-wide mb-0.5">Criada em</p>
                    <p class="text-gray-700 dark:text-gray-300 font-semibold">{{ $record->created_at?->format('d/m/Y H:i') }}</p>
                </div>
                <div>
                    <p class="text-gray-400 dark:text-gray-500 uppercase text-[10px] font-bold tracking-wide mb-0.5">Prazo da reserva</p>
                    <p class="text-gray-700 dark:text-gray-300 font-semibold">{{ $record->data_saida_prevista?->format('d/m/Y') ?? '—' }}</p>
                </div>
            </div>
        </div>

        {{-- ===================== LEGENDA ===================== --}}
        <div class="flex flex-wrap gap-2">
            @foreach($sources as $source)
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full {{ $source['chip'] }} {{ $source['text'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $source['dot'] }}"></span>
                    {{ $source['label'] }}
                </span>
            @endforeach
        </div>

        {{-- ===================== LINHA DO TEMPO ===================== --}}
        @if($eventsByDay->isEmpty())
            <div class="text-center py-16 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                <x-heroicon-o-clock class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-700 mb-3" />
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400">Nenhum evento registrado ainda.</p>
            </div>
        @else
            <div class="space-y-8">
                @foreach($eventsByDay as $day => $dayEvents)
                    <div>
                        <p class="text-[10.5px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-3">{{ $day }}</p>
                        <div class="space-y-2.5">
                            @foreach($dayEvents as $event)
                                @php $source = $sources[$event['source']] ?? $sources['comercial']; @endphp
                                <div class="flex gap-3">
                                    <div class="w-14 shrink-0 text-right pt-3">
                                        <span class="text-[11px] font-mono text-gray-400 dark:text-gray-500">{{ $event['at']->format('H:i') }}</span>
                                    </div>
                                    <div class="flex-1 bg-white dark:bg-gray-900 rounded-lg border-l-4 {{ $source['border'] }} border-t border-r border-b border-gray-200 dark:border-gray-700 p-3">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-[9.5px] font-bold uppercase tracking-wide {{ $source['text'] }}">{{ $source['label'] }}</span>
                                        </div>
                                        <p class="text-[13px] font-bold text-gray-900 dark:text-gray-100">{{ $event['title'] }}</p>
                                        @if(!empty($event['body']))
                                            <p class="text-[11.5px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $event['body'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="text-[11px] text-gray-400 dark:text-gray-600 italic text-center pt-2">
            Eventos de Manutenção e Portaria são correlacionados por Ativo + janela de tempo
            (sem vínculo direto no cadastro) — podem não pertencer exclusivamente a esta locação.
        </p>
    </div>
</x-filament-panels::page>
