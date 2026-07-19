<x-filament-panels::page>
    {{--
        Classes de cor literais aqui de proposito (mesmo motivo documentado
        em kanban.blade.php: tailwind.config.js so escaneia .blade.php).
        clip-path e' calculado por linha (dado dinamico), entao vai via
        style inline mesmo -- nao tem como isso ser uma classe Tailwind.
    --}}
    @php
        $rows = $this->getFunnelStages();
        $lostCount = $this->getLostCount();
        $conversionRate = $this->getConversionRate($rows);
        $bandColors = [
            'prospeccao' => 'bg-slate-600',
            'contato_qualificado' => 'bg-blue-600',
            'demonstracao_realizada' => 'bg-purple-600',
            'proposta_enviada' => 'bg-orange-500',
            'ganho' => 'bg-emerald-600',
        ];
    @endphp

    <div class="max-w-2xl mx-auto">
        <div class="flex flex-col items-center gap-0.5">
            @foreach($rows as $row)
                @php
                    $top = $row['topWidth'];
                    $bottom = $row['bottomWidth'];
                    $clipPath = 'polygon('
                        . (50 - $top / 2) . '% 0%, '
                        . (50 + $top / 2) . '% 0%, '
                        . (50 + $bottom / 2) . '% 100%, '
                        . (50 - $bottom / 2) . '% 100%)';
                @endphp
                <div class="relative w-full h-[92px]">
                    <div
                        class="absolute inset-0 {{ $bandColors[$row['stage']] ?? 'bg-gray-600' }} shadow-sm"
                        style="clip-path: {{ $clipPath }};"
                    ></div>
                    <div class="relative h-full flex flex-col items-center justify-center text-center pointer-events-none">
                        <span class="text-xs font-black uppercase tracking-wide text-white drop-shadow">{{ $row['label'] }}</span>
                        <span class="text-2xl font-black text-white drop-shadow leading-tight">{{ $row['count'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8 grid grid-cols-2 gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center">
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Taxa de Conversão</p>
                <p class="text-2xl font-black text-gray-900 dark:text-gray-50">
                    {{ $conversionRate !== null ? number_format($conversionRate, 1, ',', '.').'%' : '—' }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Prospecção até Ganho</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center">
                <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Perdidos</p>
                <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ $lostCount }}</p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Fora do funil acima</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
