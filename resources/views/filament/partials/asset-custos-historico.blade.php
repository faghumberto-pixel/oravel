@php
    // Classes literais por cor (o Tailwind do painel so' gera o que aparece escrito aqui).
    $faixa = [
        'red' => 'border-red-500/40 bg-red-500/10 text-red-700 dark:text-red-300',
        'amber' => 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        'emerald' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        'gray' => 'border-gray-500/40 bg-gray-500/10 text-gray-700 dark:text-gray-300',
    ][$resumo['situacao']['cor']];
    $barra = [
        'red' => 'bg-red-500', 'amber' => 'bg-amber-500', 'emerald' => 'bg-emerald-500', 'gray' => 'bg-gray-400',
    ][$resumo['situacao']['cor']];
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $pct = $resumo['razao'] !== null ? number_format($resumo['razao'] * 100, 0, ',', '.').'%' : '—';
    $maxMes = max(1, collect($meses)->max('total'));
    $tipoCor = ['Preventiva' => 'text-sky-600 dark:text-sky-300', 'Emergência' => 'text-red-600 dark:text-red-300'];
@endphp

<div class="space-y-6 text-sm">
    {{-- Sinalizacao --}}
    <div class="rounded-xl border p-4 {{ $faixa }}">
        <div class="text-base font-semibold">{{ $resumo['situacao']['rotulo'] }}</div>
        <div class="mt-1 opacity-90">
            @if ($resumo['razao'] !== null)
                O custo acumulado de manutenção ({{ $brl($resumo['total']) }}) equivale a <b>{{ $pct }}</b> do valor de aquisição ({{ $brl($resumo['valor_aquisicao']) }}).
                Revisar a partir de {{ number_format(config('custo_manutencao.limiar_revisar') * 100, 0) }}% e considerar trocar ou vender a partir de {{ number_format(config('custo_manutencao.limiar_trocar_vender') * 100, 0) }}%.
            @else
                Informe o valor de aquisição na aba "Informações Gerais" para comparar o custo com o valor do equipamento.
            @endif
        </div>
    </div>

    {{-- Linha do tempo: locacoes e manutencao no mesmo eixo --}}
    @include('filament.partials.asset-linha-do-tempo', ['linha' => $linha])

    {{-- Numeros --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([
            ['Custo acumulado', $brl($resumo['total']), $resumo['os_total'].' ordens de serviço'],
            ['% do valor de aquisição', $pct, 'Valor atual (depreciado): '.$brl($resumo['valor_atual'])],
            ['Custo médio por OS', $brl($resumo['custo_medio_os']), $resumo['os_preventivas'].' preventivas · '.$resumo['os_corretivas'].' corretivas'],
            ['Retrabalho', (string) $resumo['retrabalho'], $resumo['em_aberto'] ? $resumo['em_aberto'].' OS em aberto' : 'Nenhuma OS em aberto'],
        ] as [$rotulo, $valor, $nota])
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $rotulo }}</div>
                <div class="text-xl font-semibold text-gray-950 dark:text-white">{{ $valor }}</div>
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $nota }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        @foreach ([
            ['Mão de obra', $brl($resumo['mao_de_obra'])],
            ['Material', $brl($resumo['material'])],
            ['Logística', $brl($resumo['logistica'])],
            ['Últimos '.$resumo['janela_meses'].' meses', $brl($resumo['custo_recente']).($resumo['variacao_tendencia'] !== null ? ' ('.($resumo['variacao_tendencia'] > 0 ? '+' : '').$resumo['variacao_tendencia'].'% vs. os '.$resumo['janela_meses'].' anteriores)' : '')],
        ] as [$rotulo, $valor])
            <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $rotulo }}</div>
                <div class="font-semibold text-gray-950 dark:text-white">{{ $valor }}</div>
            </div>
        @endforeach
    </div>

    {{-- Mes a mes --}}
    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Custo mês a mês (últimos {{ count($meses) }} meses)</h3>
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">Mês</th>
                        <th class="px-3 py-2 text-right">OS</th>
                        <th class="px-3 py-2 text-right">Mão de obra</th>
                        <th class="px-3 py-2 text-right">Material</th>
                        <th class="px-3 py-2 text-right">Logística</th>
                        <th class="px-3 py-2 text-right">Total do mês</th>
                        <th class="w-40 px-3 py-2"></th>
                        <th class="px-3 py-2 text-right">Acumulado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($meses as $m)
                        <tr class="{{ $m['total'] > 0 ? '' : 'text-gray-400 dark:text-gray-500' }}">
                            <td class="px-3 py-1.5 font-medium">{{ $m['rotulo'] }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $m['os'] ?: '—' }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $m['mao_de_obra'] > 0 ? $brl($m['mao_de_obra']) : '—' }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $m['material'] > 0 ? $brl($m['material']) : '—' }}</td>
                            <td class="px-3 py-1.5 text-right">{{ $m['logistica'] > 0 ? $brl($m['logistica']) : '—' }}</td>
                            <td class="px-3 py-1.5 text-right font-semibold">{{ $m['total'] > 0 ? $brl($m['total']) : '—' }}</td>
                            <td class="px-3 py-1.5">
                                @if ($m['total'] > 0)
                                    <div class="h-2 rounded-full bg-gray-100 dark:bg-white/10"><div class="h-2 rounded-full {{ $barra }}" style="width: {{ max(3, round($m['total'] / $maxMes * 100)) }}%"></div></div>
                                @endif
                            </td>
                            <td class="px-3 py-1.5 text-right">{{ $brl($m['acumulado']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Historico de OS --}}
    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Histórico de ordens de serviço</h3>
        @if ($ordens->isEmpty())
            <div class="rounded-xl border border-gray-200 p-4 text-gray-500 dark:border-white/10 dark:text-gray-400">Este equipamento ainda não tem ordens de serviço.</div>
        @else
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Data</th>
                            <th class="px-3 py-2">OS</th>
                            <th class="px-3 py-2">Tipo</th>
                            <th class="px-3 py-2">O que foi feito</th>
                            <th class="px-3 py-2">Situação</th>
                            <th class="px-3 py-2 text-right">Custo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($ordens as $o)
                            <tr>
                                <td class="whitespace-nowrap px-3 py-1.5">{{ $o['data']->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5"><a href="{{ $o['url'] }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $o['numero'] ?: 'Abrir' }}</a></td>
                                <td class="whitespace-nowrap px-3 py-1.5 font-medium {{ $tipoCor[$o['tipo']] ?? 'text-gray-700 dark:text-gray-200' }}">{{ $o['tipo'] }}@if ($o['retrabalho']) <span class="ml-1 rounded bg-red-500/15 px-1.5 py-0.5 text-xs text-red-600 dark:text-red-300">retrabalho</span>@endif</td>
                                <td class="px-3 py-1.5 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($o['descricao'], 90) }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5">{{ $o['status'] }}</td>
                                <td class="whitespace-nowrap px-3 py-1.5 text-right font-semibold">{{ $brl($o['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
