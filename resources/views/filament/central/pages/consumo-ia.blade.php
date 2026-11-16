@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $brl = fn ($v) => 'R$ '.number_format((float) $v, $v >= 1 ? 2 : 4, ',', '.');
    $k = fn ($v) => $v >= 1_000_000 ? number_format($v / 1_000_000, 2, ',', '.').' mi' : ($v >= 10_000 ? number_format($v / 1_000, 1, ',', '.').' mil' : number_format($v, 0, ',', '.'));

    // Barras do tempo (SVG): entrada (azul) + saida (ambar) empilhadas.
    $totais = array_map(fn ($e, $s) => $e + $s, $serie['entrada'], $serie['saida']);
    $maxBarra = max(1, ...($totais ?: [1]));
    $qtd = max(1, count($totais));
    $largura = 1000; $altura = 150; $folga = 2;
    $w = ($largura / $qtd);
    $pctEntrada = $resumo['tokens'] > 0 ? round($resumo['entrada'] / $resumo['tokens'] * 100) : 0;
@endphp

<x-filament-panels::page>
    {{-- Atualiza sozinho a cada 5 s (tempo real). --}}
    <div wire:poll.5s class="space-y-6">

        {{-- Barra superior: ao vivo + filtros --}}
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </span>
                AO VIVO
            </span>
            <span class="text-xs text-gray-500">atualizado às {{ now()->format('H:i:s') }} · a cada 5 s</span>

            <div class="ms-auto flex flex-wrap items-center gap-2">
                <select wire:model.live="periodo" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" aria-label="Período">
                    @foreach (\App\Services\IaConsumoPainel::PERIODOS as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
                <select wire:model.live="area" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" aria-label="Aplicativo">
                    <option value="">Todos os aplicativos</option>
                    @foreach (\App\Support\Ia\ConsumoIa::AREAS as $chave => $rotulo)
                        <option value="{{ $chave }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
                <select wire:model.live="cliente" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" aria-label="Cliente">
                    <option value="">Todos os clientes</option>
                    @foreach ($clientes as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
                <select wire:model.live="usuario" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" aria-label="Usuário">
                    <option value="">Todos os usuários</option>
                    @foreach ($usuarios as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Indicadores --}}
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tokens no período</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">{{ $k($resumo['tokens']) }}</div>
                <div class="mt-3 flex h-2 overflow-hidden rounded-full bg-amber-400/70" title="Entrada x saída">
                    <div class="bg-sky-500" style="width: {{ $pctEntrada }}%"></div>
                </div>
                <div class="mt-1 flex justify-between text-xs text-gray-500">
                    <span><span class="inline-block h-2 w-2 rounded-full bg-sky-500"></span> entrada {{ $n($resumo['entrada']) }}</span>
                    <span><span class="inline-block h-2 w-2 rounded-full bg-amber-400"></span> saída {{ $n($resumo['saida']) }}</span>
                </div>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Chamadas à IA</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">{{ $n($resumo['chamadas']) }}</div>
                <div class="mt-3 text-xs text-gray-500">
                    média {{ $resumo['chamadas'] ? $n($resumo['tokens'] / $resumo['chamadas']) : 0 }} tokens por chamada
                    @if ($resumo['retries'] > 0) · <span class="text-amber-600 dark:text-amber-400">{{ $resumo['retries'] }} {{ $resumo['retries'] === 1 ? 'correção automática' : 'correções automáticas' }}</span>@endif
                </div>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Custo estimado no período</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">{{ $brl($resumo['custo_brl']) }}</div>
                <div class="mt-3 text-xs text-gray-500">US$ {{ number_format($resumo['custo_usd'], $resumo['custo_usd'] >= 1 ? 2 : 4, ',', '.') }} · dólar a R$ {{ number_format($dolar, 2, ',', '.') }}</div>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hoje</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">{{ $k($hoje['tokens']) }} <span class="text-base font-medium text-gray-500">tokens</span></div>
                <div class="mt-3 text-xs text-gray-500">{{ $n($hoje['chamadas']) }} {{ $hoje['chamadas'] === 1 ? 'chamada' : 'chamadas' }} · {{ $brl($hoje['custo_brl']) }}</div>
            </div>
        </div>

        {{-- Tempo --}}
        <x-filament::section heading="Consumo ao longo do tempo" :description="match($serie['passo']) { 'minute' => 'um intervalo por minuto', 'hour' => 'um intervalo por hora', default => 'um intervalo por dia' }">
            @if ($resumo['chamadas'] === 0)
                <p class="py-6 text-center text-sm text-gray-500">Nenhuma chamada à IA neste filtro ainda.</p>
            @else
                <svg viewBox="0 0 {{ $largura }} {{ $altura }}" preserveAspectRatio="none" class="h-40 w-full" role="img" aria-label="Tokens ao longo do tempo">
                    @foreach ($serie['rotulos'] as $i => $rotulo)
                        @php
                            $hE = $serie['entrada'][$i] / $maxBarra * ($altura - 6);
                            $hS = $serie['saida'][$i] / $maxBarra * ($altura - 6);
                            $x = $i * $w + $folga / 2;
                            $bw = max(1, $w - $folga);
                        @endphp
                        <g>
                            <title>{{ $rotulo }} — {{ $n($serie['entrada'][$i] + $serie['saida'][$i]) }} tokens ({{ $n($serie['entrada'][$i]) }} entrada, {{ $n($serie['saida'][$i]) }} saída)</title>
                            <rect x="{{ $x }}" y="{{ $altura - $hS - $hE }}" width="{{ $bw }}" height="{{ max(0, $hS) }}" class="fill-amber-400" rx="1"/>
                            <rect x="{{ $x }}" y="{{ $altura - $hE }}" width="{{ $bw }}" height="{{ max(0, $hE) }}" class="fill-sky-500" rx="1"/>
                        </g>
                    @endforeach
                </svg>
                <div class="mt-1 flex justify-between text-[11px] text-gray-500">
                    <span>{{ $serie['rotulos'][0] ?? '' }}</span>
                    <span>{{ $serie['rotulos'][intdiv(count($serie['rotulos']), 2)] ?? '' }}</span>
                    <span>{{ end($serie['rotulos']) ?: '' }}</span>
                </div>
            @endif
        </x-filament::section>

        {{-- Por aplicativo --}}
        <x-filament::section heading="Por aplicativo" description="Os aplicativos sem uso aparecem zerados: ainda não foram ligados.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-500">
                        <th class="py-2 pr-4">Aplicativo</th><th class="py-2 pr-4 text-right">Chamadas</th><th class="py-2 pr-4 text-right">Tokens</th><th class="py-2 pr-4 text-right">Custo</th><th class="w-1/3 py-2">Participação</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($porArea as $a)
                        <tr class="border-t border-gray-200 dark:border-white/10 {{ $a['chamadas'] ? '' : 'text-gray-400' }}">
                            <td class="py-2 pr-4 font-medium">{{ $a['rotulo'] }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $n($a['chamadas']) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $n($a['tokens']) }}</td>
                            <td class="py-2 pr-4 text-right tabular-nums">{{ $brl($a['custo_brl']) }}</td>
                            <td class="py-2">
                                <div class="flex items-center gap-2">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"><div class="h-2 rounded-full bg-sky-500" style="width: {{ $a['percentual'] }}%"></div></div>
                                    <span class="w-12 text-right text-xs tabular-nums">{{ number_format($a['percentual'], 1, ',', '.') }}%</span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <div class="grid gap-6 xl:grid-cols-2">
            @foreach ([['Por cliente', $porCliente, false], ['Por usuário', $porUsuario, true]] as [$titulo, $linhas, $mostraEmail])
                <x-filament::section :heading="$titulo" description="Os 15 que mais consumiram no período.">
                    @forelse ($linhas as $l)
                        <div class="border-t border-gray-200 py-2 first:border-0 dark:border-white/10">
                            <div class="flex items-baseline justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium">{{ $l['nome'] }}</div>
                                    @if ($mostraEmail && $l['email'])<div class="truncate text-xs text-gray-500">{{ $l['email'] }}</div>@endif
                                </div>
                                <div class="shrink-0 text-right text-sm tabular-nums">
                                    <div class="font-semibold">{{ $k($l['tokens']) }} tokens</div>
                                    <div class="text-xs text-gray-500">{{ $n($l['chamadas']) }} {{ $l['chamadas'] === 1 ? 'chamada' : 'chamadas' }} · {{ $brl($l['custo_brl']) }}</div>
                                </div>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10"><div class="h-1.5 rounded-full bg-sky-500" style="width: {{ $l['barra'] }}%"></div></div>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-gray-500">Sem consumo neste filtro.</p>
                    @endforelse
                </x-filament::section>
            @endforeach
        </div>

        {{-- Ultimas chamadas (linha do tempo ao vivo) --}}
        <x-filament::section heading="Últimas chamadas" description="Em tempo real, independente do período escolhido.">
            @forelse ($ultimas as $u)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-gray-200 py-2 text-sm first:border-0 dark:border-white/10">
                    <span class="w-20 shrink-0 tabular-nums text-gray-500" title="{{ $u['quando']->format('d/m/Y H:i:s') }}">{{ $u['quando']->isToday() ? $u['quando']->format('H:i:s') : $u['quando']->format('d/m H:i') }}</span>
                    <span class="rounded-md bg-sky-500/10 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:text-sky-300">{{ $u['area'] }}</span>
                    @if ($u['etapa'] === 'retry')<span class="rounded-md bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300">correção</span>@endif
                    <span class="min-w-0 flex-1 truncate text-gray-700 dark:text-gray-300">{{ $u['cliente'] }} · {{ $u['usuario'] }}</span>
                    <span class="text-xs text-gray-500">{{ $u['modelo'] }}</span>
                    <span class="tabular-nums">{{ $n($u['entrada']) }} <span class="text-gray-400">→</span> {{ $n($u['saida']) }}</span>
                    <span class="w-20 text-right tabular-nums text-gray-500">{{ $brl($u['custo_brl']) }}</span>
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500">Nenhuma chamada registrada ainda.</p>
            @endforelse
        </x-filament::section>

        <p class="text-xs text-gray-500">
            Custo é uma <strong>estimativa</strong> (preço de tabela de cada modelo × dólar a R$ {{ number_format($dolar, 2, ',', '.') }}); a fatura real da Anthropic é em dólar.
            O painel guarda só números (quem, onde, quantos tokens), nunca o texto das perguntas e respostas.
            @if ($desde) Registros desde {{ $desde->format('d/m/Y H:i') }}. @endif
        </p>
    </div>
</x-filament-panels::page>
