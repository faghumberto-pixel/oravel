<x-filament-panels::page>
    {{--
        Cores vêm de App\Support\CrmPalette (fonte única, mesma do Kanban --
        ver comentário lá). clip-path e' calculado por linha (a largura
        reflete quantos leads reais tem em cada estagio, acumulado ate' o
        final do funil), entao vai via style inline mesmo -- nao tem como
        isso ser uma classe Tailwind.

        Visual: piramide invertida de verdade, cor solida (sem
        brilho/gradiente nem alvo -- versao anterior parecia foguete, nao
        piramide, feedback direto do usuario, referencia:
        blog.hookdig.com/como-integrar-as-metricas-de-inbound-marketing-com-o-ciclo-de-vendas).
        Mesma paleta do Kanban, pra reconhecer o estagio de uma tela pra
        outra. Cada faixa e' um link pra lista de leads ja filtrada por
        aquele estagio -- clip-path tambem no <a>, nao so' num div interno,
        pra area clicavel bater exatamente com o trapezio visivel (canto
        "invisivel" fora do clip-path nao deve roubar clique da faixa vizinha).
    --}}
    @php
        $rows = $this->getFunnelStages();
        $lostCount = $this->getLostCount();
        $conversionRate = $this->getConversionRate($rows);
        $openPipelineValue = $this->getOpenPipelineValue();
        $averageTicket = $this->getAverageTicket();
        // Taxa de conversao colorida por faixa (nao mais cinza fixo) --
        // pedido do usuario 2026-07-28 ("mais cor nas fontes"). Faixas sao
        // as mesmas usadas informalmente em funil B2B (>=20% saudavel,
        // 10-20% atencao, <10% critico).
        $conversionColorClass = match (true) {
            $conversionRate === null => 'text-gray-400 dark:text-gray-500',
            $conversionRate >= 20 => 'text-emerald-600 dark:text-emerald-400',
            $conversionRate >= 10 => 'text-amber-600 dark:text-amber-400',
            default => 'text-red-600 dark:text-red-400',
        };
    @endphp

    {{--
        Quadro bem maior de proposito -- pedido explicito do usuario
        ("aumentar pra que caixa os titulos, nao tem problema se ficar
        muito grande"), titulo longo ("Demonstração Realizada") precisa
        caber sem quebrar feio numa faixa estreita perto da ponta.
    --}}
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
                <a
                    href="{{ $row['url'] }}"
                    class="relative block w-full h-[150px] -mt-px group {{ $bandColor }} hover:brightness-110 transition-[filter]"
                    style="clip-path: {{ $clipPath }};"
                    title="Ver leads em {{ $row['label'] }}"
                >
                    <div class="relative h-full flex flex-col items-center justify-center text-center pointer-events-none px-6">
                        <span class="text-base font-black uppercase tracking-wide text-white leading-tight">{{ $row['label'] }}</span>
                        <span class="text-4xl font-black text-white leading-tight mt-1">{{ $row['count'] }}</span>
                        <span class="text-xs font-bold uppercase tracking-wide text-white/0 group-hover:text-white/80 transition-colors leading-tight mt-1">Ver leads →</span>
                    </div>
                </a>
            @endforeach
        </div>

        {{--
            4 cards (era 2) -- pedido do usuario 2026-07-28 ("mesmo padrao
            [do resto], mais colorido e vivo"): estilo resumo de pipeline do
            Pipedrive, valor em jogo e ticket medio ao lado da taxa de
            conversao/perdidos que ja existiam.
        --}}
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
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Prospecção até Ganho</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center hover:shadow-md transition-shadow">
                <div class="flex items-center justify-center gap-1.5 mb-1">
                    <x-heroicon-m-calculator class="w-3.5 h-3.5 text-blue-500" />
                    <p class="text-[10px] font-black uppercase tracking-wide text-gray-400 dark:text-gray-500">Ticket Médio</p>
                </div>
                <p class="text-2xl font-black text-blue-600 dark:text-blue-400">
                    {{ $averageTicket !== null ? 'R$ '.number_format($averageTicket, 0, ',', '.') : '—' }}
                </p>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">Média dos leads ganhos</p>
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
