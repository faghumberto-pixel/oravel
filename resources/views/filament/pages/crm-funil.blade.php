<x-filament-panels::page>
    @php
        $rows = $this->getFunnelStages();
        $lostCount = $this->getLostCount();
        $conversionRate = $this->getConversionRate($rows);
        $openPipelineValue = $this->getOpenPipelineValue();
        $averageTicket = $this->getAverageTicket();
        $conversionColorClass = match (true) {
            $conversionRate === null => 'text-gray-400 dark:text-gray-500',
            $conversionRate >= 20 => 'text-emerald-600 dark:text-emerald-400',
            $conversionRate >= 10 => 'text-amber-600 dark:text-amber-400',
            default => 'text-red-600 dark:text-red-400',
        };
    @endphp

    <div class="max-w-5xl mx-auto">
        <div class="flex flex-col items-center">
            @foreach($rows as $row)
                @php
                    $top = $row['topWidth'];
                    $bottom = $row['bottomWidth'];
                    $clipPath = 'polygon('
                        . (50 - $top / 2) . '% 0%, '
                        . (50 + $top / 2) . '% 0%, '
                        . (50 + $bottom / 2) . '% 100%, '
                        . (50 - $bottom / 2) . '% 100%)';
                    $bandColor = \App\Support\CrmPalette::stage($row['stage'])['bg'];
                @endphp
                <button
                    wire:click="selectStage('{{ $row['stage'] }}')"
                    class="relative block w-full h-[150px] -mt-px group {{ $bandColor }} hover:brightness-110 transition-[filter] border-none cursor-pointer"
                    style="clip-path: {{ $clipPath }};"
                    title="Ver leads em {{ $row['label'] }}"
                >
                    <div class="relative h-full flex flex-col items-center justify-center text-center pointer-events-none px-6">
                        <span class="text-base font-black uppercase tracking-wide text-white leading-tight">{{ $row['label'] }}</span>
                        <span class="text-4xl font-black text-white leading-tight mt-1">{{ $row['count'] }}</span>
                        <span class="text-xs font-bold uppercase tracking-wide text-white/0 group-hover:text-white/80 transition-colors leading-tight mt-1">Ver leads →</span>
                    </div>
                </button>
            @endforeach
        </div>

        <div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-banknotes class="w-3.5 h-3.5 text-emerald-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Em Pipeline</p>
                </div>
                <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                    R$ {{ number_format($openPipelineValue, 0, ',', '.') }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Soma dos leads abertos</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-arrow-trending-up class="w-3.5 h-3.5 {{ $conversionColorClass }}" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Taxa de Conversão</p>
                </div>
                <p class="text-2xl font-black {{ $conversionColorClass }}">
                    {{ $conversionRate !== null ? number_format($conversionRate, 1, ',', '.').'%' : '—' }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Prospecção até Convertido</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-calculator class="w-3.5 h-3.5 text-blue-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Ticket Médio</p>
                </div>
                <p class="text-2xl font-black text-blue-600 dark:text-blue-400">
                    {{ $averageTicket !== null ? 'R$ '.number_format($averageTicket, 0, ',', '.') : '—' }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Média dos leads convertidos</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-x-circle class="w-3.5 h-3.5 text-red-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Perdidos</p>
                </div>
                <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ $lostCount }}</p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Fora do funil acima</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
