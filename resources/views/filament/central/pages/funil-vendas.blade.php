<x-filament-panels::page>
    {{--
        Classes de cor/gradiente literais aqui de proposito (mesmo motivo
        documentado em kanban.blade.php: tailwind.config.js so escaneia
        .blade.php). clip-path e' calculado por linha (dado dinamico -- a
        largura reflete quantos leads reais tem em cada estagio), entao vai
        via style inline mesmo -- nao tem como isso ser uma classe Tailwind.

        Visual pedido pelo usuario: funil "de vidro" (cada faixa com brilho/
        gradiente, tipo as imagens de estoque de funil de vendas 3D),
        afunilando ate' um ponto de verdade embaixo, com um alvo (bullseye)
        simbolizando o negocio fechado.
    --}}
    @php
        $rows = $this->getFunnelStages();
        $lostCount = $this->getLostCount();
        $conversionRate = $this->getConversionRate($rows);
        // from/to do gradiente (claro em cima, escuro embaixo -- efeito de
        // brilho/vidro) por estagio. Mesma familia de cor do Kanban, pra
        // continuar reconhecendo o estagio de uma tela pra outra.
        $bandGradients = [
            'prospeccao' => 'linear-gradient(180deg, #94a3b8 0%, #64748b 45%, #475569 100%)',
            'contato_qualificado' => 'linear-gradient(180deg, #60a5fa 0%, #3b82f6 45%, #1d4ed8 100%)',
            'demonstracao_realizada' => 'linear-gradient(180deg, #c084fc 0%, #9333ea 45%, #6b21a8 100%)',
            'proposta_enviada' => 'linear-gradient(180deg, #fdba74 0%, #f97316 45%, #c2410c 100%)',
            'ganho' => 'linear-gradient(180deg, #6ee7b7 0%, #059669 45%, #047857 100%)',
        ];
        $lastStage = end($rows)['stage'] ?? null;
    @endphp

    <div class="max-w-xl mx-auto">
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
                    $gradient = $bandGradients[$row['stage']] ?? 'linear-gradient(180deg, #9ca3af 0%, #4b5563 100%)';
                @endphp
                <div class="relative w-full h-[86px] -mt-px">
                    <div
                        class="absolute inset-0 shadow-lg"
                        style="clip-path: {{ $clipPath }}; background: {{ $gradient }};"
                    ></div>

                    {{-- brilho: faixa clara desbotando nas pontas, simulando o reflexo de vidro da referencia --}}
                    <div
                        class="absolute inset-x-0 top-0 h-3 opacity-60"
                        style="clip-path: {{ $clipPath }}; background: linear-gradient(90deg, transparent 5%, rgba(255,255,255,0.85) 50%, transparent 95%);"
                    ></div>

                    <div class="relative h-full flex flex-col items-center justify-center text-center pointer-events-none px-4">
                        <span class="text-xs font-black uppercase tracking-wide text-white drop-shadow-md leading-tight">{{ $row['label'] }}</span>
                        <span class="text-2xl font-black text-white drop-shadow-md leading-tight">{{ $row['count'] }}</span>
                    </div>
                </div>
            @endforeach

            {{-- alvo (bullseye) na ponta do funil -- referencia visual pedida, simboliza o negocio fechado --}}
            <div class="relative w-56 h-24 -mt-1 flex items-start justify-center">
                <div class="absolute top-0 w-56 h-14 rounded-[50%] border-2 border-gray-300 dark:border-gray-600"></div>
                <div class="absolute top-[10px] w-40 h-10 rounded-[50%] border-2 border-gray-400 dark:border-gray-500"></div>
                <div class="absolute top-[18px] w-24 h-6 rounded-[50%] border-2 border-gray-500 dark:border-gray-400"></div>
                <div
                    class="absolute top-[23px] w-4 h-4 rounded-full shadow-[0_0_12px_2px_rgba(5,150,105,0.6)]"
                    style="background: {{ $bandGradients[$lastStage] ?? '#059669' }};"
                ></div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-4">
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
