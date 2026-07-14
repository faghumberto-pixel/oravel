<x-filament-panels::page>
    @php $pending = $this->pending; @endphp

    @if($pending->isEmpty())
        <div class="text-center py-16 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <x-heroicon-o-building-storefront class="w-10 h-10 mx-auto text-emerald-500 mb-3" />
            <p class="text-sm font-bold text-gray-500 dark:text-gray-400">Nenhuma desmobilização aguardando Laudo de Recebimento.</p>
        </div>
    @endif

    <div class="space-y-3">
        @foreach($pending as $movement)
            @php
                $arrival = $movement->patioArrival;
                $items = $arrival?->items;
                $total = $items?->count() ?? 0;
                $checked = $items?->where('is_checked', true)->count() ?? 0;
                $progress = $total > 0 ? (int) round($checked / $total * 100) : 0;
                $started = (bool) $arrival;
            @endphp

            <div class="bg-white dark:bg-gray-900 rounded-xl border-2 {{ $started ? 'border-amber-400' : 'border-blue-400' }} shadow-sm overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-2.5 h-2.5 rounded-full {{ $started ? 'bg-amber-400' : 'bg-blue-400' }} shrink-0"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-gray-900 dark:text-white truncate">{{ $movement->asset?->name ?? 'Equipamento' }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                {{ $movement->maintenanceOrder?->client?->name ?? 'Cliente não informado' }}
                                &middot; Checklist concluído {{ $movement->completed_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider {{ $started ? 'text-amber-600 dark:text-amber-400' : 'text-blue-600 dark:text-blue-400' }} shrink-0">
                        {{ $started ? 'Laudo em Andamento' : 'Aguardando Chegada' }}
                    </span>
                </div>

                @if($started)
                    <div class="px-4 pb-2">
                        <div class="flex items-center justify-between text-[10px] font-bold text-gray-500 dark:text-gray-400 mb-1">
                            <span>{{ $checked }}/{{ $total }} itens</span>
                            <span>{{ $progress }}%</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-1.5 rounded-full bg-amber-400" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                @endif

                <div class="border-t border-gray-200 dark:border-gray-800 p-3">
                    <a href="{{ route('equipment-movements.patio-arrival-mobile', $movement) }}"
                       class="block w-full py-2.5 rounded-lg text-center {{ $started ? 'bg-amber-500 hover:bg-amber-600' : 'bg-blue-600 hover:bg-blue-700' }} text-white text-xs font-black uppercase tracking-wider transition">
                        {{ $started ? 'Continuar Laudo de Recebimento' : 'Iniciar Laudo de Recebimento' }}
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
