<x-filament-panels::page>
    @php $pending = $this->pending; @endphp

    @if($pending->isEmpty())
        <div class="text-center py-16 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
            <x-heroicon-o-shield-check class="w-10 h-10 mx-auto text-emerald-500 mb-3" />
            <p class="text-sm font-bold text-gray-500 dark:text-gray-400">Nenhuma movimentação aguardando aprovação no momento.</p>
        </div>
    @endif

    <div class="space-y-3">
        @foreach($pending as $movement)
            @php
                $progress = $movement->progressPercent();
                $isOpen = $this->reviewingId === $movement->id;
            @endphp

            <div class="bg-white dark:bg-gray-900 rounded-xl border-2 border-amber-400 shadow-sm overflow-hidden animate-pulse-slow">
                <button wire:click="review('{{ $movement->id }}')" type="button" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 shrink-0 animate-pulse"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-black text-gray-900 dark:text-white truncate">{{ $movement->asset?->name ?? 'Equipamento' }}</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                {{ $movement->solicitacaoLocacao?->customer?->name ?? 'Cliente não informado' }}
                                &middot; Enviado {{ $movement->completed_at?->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-600 dark:text-amber-400 shrink-0">Aguardando Análise</span>
                </button>

                @if($isOpen)
                    <div class="border-t border-gray-200 dark:border-gray-800 p-4 space-y-3 bg-gray-50 dark:bg-gray-950/40">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="flex-1 h-2 rounded-full bg-gray-200 dark:bg-gray-800 overflow-hidden">
                                <div class="h-2 bg-emerald-500 rounded-full" style="width: {{ $progress }}%"></div>
                            </div>
                            <span class="text-[11px] font-bold text-gray-500">{{ $progress }}%</span>
                        </div>

                        <div class="space-y-2">
                            @foreach($movement->items as $item)
                                <div class="flex items-start gap-2 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 px-3 py-2">
                                    <span @class([
                                        'mt-0.5 w-4 h-4 rounded-full shrink-0 flex items-center justify-center',
                                        'bg-emerald-500' => $item->is_checked,
                                        'bg-gray-300 dark:bg-gray-700' => ! $item->is_checked,
                                    ])>
                                        @if($item->is_checked)
                                            <x-heroicon-s-check class="w-2.5 h-2.5 text-white" />
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $item->label }}</p>
                                        @if($item->notes)
                                            <p class="text-[11px] text-gray-500 dark:text-gray-400 italic">{{ $item->notes }}</p>
                                        @endif
                                        @if($item->getMedia('photos')->isNotEmpty())
                                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                                @foreach($item->getMedia('photos') as $media)
                                                    <a href="{{ $media->getUrl() }}" target="_blank">
                                                        <img src="{{ $media->getUrl('thumb') }}" class="w-12 h-12 rounded-md object-cover border border-gray-200 dark:border-gray-700">
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if($movement->rejected_reason)
                            <p class="text-[11px] text-red-500 font-semibold">Última recusa: {{ $movement->rejected_reason }}</p>
                        @endif

                        @if($this->podeAprovar)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-3">
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1.5">Dar OK Técnico</label>
                                    <input type="password" wire:model="approvalPassword" placeholder="Confirme sua senha"
                                           class="w-full mb-2 py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500">
                                    <button wire:click="approve('{{ $movement->id }}')" type="button"
                                            class="w-full py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider transition">
                                        Liberar Saída
                                    </button>
                                </div>

                                <div class="bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 p-3">
                                    <label class="block text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1.5">Recusar</label>
                                    <textarea wire:model="rejectReason" rows="1" placeholder="Motivo da recusa"
                                              class="w-full mb-2 py-2 px-3 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500"></textarea>
                                    <button wire:click="reject('{{ $movement->id }}')" type="button"
                                            class="w-full py-2.5 rounded-lg border border-red-500 text-red-600 dark:text-red-400 hover:bg-red-500/10 text-xs font-black uppercase tracking-wider transition">
                                        Devolver ao Operador
                                    </button>
                                </div>
                            </div>
                        @else
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 italic pt-2">
                                Você não tem nível hierárquico suficiente na Logística (Supervisor ou acima) para aprovar ou recusar esta movimentação.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <style>
        @keyframes pulse-slow { 0%, 100% { box-shadow: 0 0 0 0 rgba(251,191,36,0.4); } 50% { box-shadow: 0 0 0 6px rgba(251,191,36,0); } }
        .animate-pulse-slow { animation: pulse-slow 2s ease-in-out infinite; }
    </style>
</x-filament-panels::page>
