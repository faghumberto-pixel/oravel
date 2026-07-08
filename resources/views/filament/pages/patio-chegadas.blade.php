<x-filament-panels::page>
    @php $pending = $this->pending; @endphp

    @if($pending->isEmpty())
        <div class="text-center py-16 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <x-heroicon-o-building-storefront class="w-10 h-10 mx-auto text-emerald-500 mb-3" />
            <p class="text-sm font-bold text-gray-500 dark:text-gray-400">Nenhuma desmobilização aguardando confirmação de chegada no pátio.</p>
        </div>
    @endif

    <div class="space-y-3">
        @foreach($pending as $movement)
            @php $isOpen = $this->confirmingId === $movement->id; @endphp

            <div class="bg-white dark:bg-gray-900 rounded-xl border-2 border-blue-400 shadow-sm overflow-hidden">
                <button wire:click="confirm('{{ $movement->id }}')" type="button" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-400 shrink-0"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-gray-900 dark:text-white truncate">{{ $movement->asset?->name ?? 'Equipamento' }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                {{ $movement->maintenanceOrder?->client?->name ?? 'Cliente não informado' }}
                                &middot; Checklist concluído {{ $movement->completed_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 shrink-0">Aguardando Chegada</span>
                </button>

                @if($isOpen)
                    <div class="border-t border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50 dark:bg-gray-950/40">
                        <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1.5">Condição inicial do equipamento (vistoria rápida de entrada)</label>
                        <textarea wire:model="conditionNotes" rows="2" placeholder="Ex: sem avarias aparentes, tanque cheio..."
                                  class="w-full mb-2 py-2 px-3 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500"></textarea>

                        <button wire:click="registerArrival('{{ $movement->id }}')" type="button"
                                class="w-full py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-black uppercase tracking-wider transition">
                            Confirmar Chegada no Pátio
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
