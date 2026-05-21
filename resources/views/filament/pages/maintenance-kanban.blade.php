<x-filament-panels::page>
    {{-- Forçamos o título aqui para ignorar qualquer tradução automática --}}
    <x-slot name="heading">
        Quadro de Gestão Kanban
    </x-slot>

    <div class="flex flex-row gap-4 overflow-x-auto pb-4 custom-scrollbar">
        @foreach($this->getStatuses() as $statusId => $statusData)
            <div class="flex-shrink-0 w-80 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden">
                
                {{-- Títulos coloridos com Azul Petróleo para Peças --}}
                <div style="background-color: {{ 
                    match($statusId) {
                        'aguardando_diagnostico' => '#b91c1c', // Vermelho Escuro
                        'em_manutencao'          => '#d97706', // Laranja/Âmbar
                        'aguardando_peca'        => '#0891b2', // Azul Petróleo (Logística)
                        'teste_qualidade'        => '#4338ca', // Índigo/Roxo Fechado
                        'disponivel_comercial'   => '#047857', // Verde Floresta
                        default                  => '#374151'
                    }
                }};" class="p-3 mb-4 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-widest text-white flex justify-between items-center">
                        {{ $statusData['title'] }}
                        <span class="bg-white/20 text-white px-2 py-0.5 rounded-full text-[10px]">
                            {{ $this->getRecords()->get($statusId)?->count() ?? 0 }}
                        </span>
                    </h3>
                </div>

                <div class="px-4 pb-4 space-y-3">
                    @foreach($this->getRecords()->get($statusId, []) as $record)
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700 hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-xs font-mono font-bold text-primary-600 dark:text-primary-400">#{{ $record->os_number }}</span>
                                <span class="text-[10px] uppercase font-bold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 dark:text-gray-300">
                                    {{ $record->maintenance_type }}
                                </span>
                            </div>
                            
                            <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 mb-1">{{ $record->asset?->name }}</h4>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Pat: {{ $record->asset?->patrimonio }}</p>
                            
                            <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-50 dark:border-gray-700">
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 italic">
                                    Tec: {{ $record->technician?->name ?? 'Sem técnico' }}
                                </span>
                                
                                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $record]) }}" 
                                   class="text-xs text-primary-600 dark:text-primary-400 font-bold hover:underline">
                                    Abrir
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <style> 
        .custom-scrollbar::-webkit-scrollbar { height: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
    </style>
</x-filament-panels::page>