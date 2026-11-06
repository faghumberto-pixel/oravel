@php
    $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $corTipo = ['Preventiva' => 'bg-sky-500', 'Emergência' => 'bg-red-500'];
    $saldoCor = $linha['saldo'] >= 0 ? 'text-emerald-600 dark:text-emerald-300' : 'text-red-600 dark:text-red-300';
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Linha do tempo (últimos {{ $linha['meses'] }} meses)</h3>
        <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-4 rounded bg-emerald-500"></span>Locado agora</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-4 rounded bg-sky-600/70"></span>Locação encerrada</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>Preventiva</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>Corretiva</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>Emergência</span>
            <span class="inline-flex items-center gap-1"><span class="h-3 w-3 rounded-full border-2 border-red-500"></span>Retrabalho</span>
            <span>Tamanho do ponto = custo da OS</span>
        </div>
    </div>

    <div class="space-y-2">
        {{-- Locacoes --}}
        <div class="flex items-center gap-3">
            <div class="w-24 shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">Locações</div>
            <div class="relative h-10 flex-1 overflow-hidden rounded-md bg-gray-100 dark:bg-white/5">
                @foreach ($linha['eixo'] as $t)
                    <span class="absolute inset-y-0 w-px bg-gray-200 dark:bg-white/10" style="left: {{ $t['esq'] }}%"></span>
                @endforeach
                @foreach ($linha['contratos'] as $c)
                    <div class="absolute inset-y-1 flex items-center overflow-hidden whitespace-nowrap rounded px-2 text-xs font-medium text-white {{ $c['ativo'] ? 'bg-emerald-500' : 'bg-sky-600/70' }}"
                         style="left: {{ $c['esq'] }}%; width: {{ $c['larg'] }}%"
                         title="{{ $c['cliente'] }}{{ $c['cidade'] ? ' — '.$c['cidade'] : '' }} · {{ $c['de']->format('d/m/Y') }} a {{ $c['ate']?->format('d/m/Y') ?? 'em aberto' }} · {{ $brl($c['valor']) }} ({{ $c['numero'] }})">
                        {{ $c['larg'] > 7 ? \Illuminate\Support\Str::limit($c['cliente'], 18) : '' }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Manutencao --}}
        <div class="flex items-center gap-3">
            <div class="w-24 shrink-0 text-xs font-medium text-gray-500 dark:text-gray-400">Manutenção</div>
            <div class="relative h-12 flex-1 overflow-hidden rounded-md bg-gray-100 dark:bg-white/5">
                @foreach ($linha['eixo'] as $t)
                    <span class="absolute inset-y-0 w-px bg-gray-200 dark:bg-white/10" style="left: {{ $t['esq'] }}%"></span>
                @endforeach
                @foreach ($linha['ordens'] as $o)
                    <span class="absolute top-1/2 rounded-full {{ $corTipo[$o['tipo']] ?? 'bg-amber-500' }} {{ $o['retrabalho'] ? 'ring-2 ring-red-500 ring-offset-1 ring-offset-transparent' : '' }} opacity-90"
                          style="left: {{ $o['esq'] }}%; width: {{ $o['tamanho'] }}px; height: {{ $o['tamanho'] }}px; transform: translate(-50%, -50%)"
                          title="{{ $o['data']->format('d/m/Y') }} · {{ $o['numero'] }} · {{ $o['tipo'] }}{{ $o['retrabalho'] ? ' (retrabalho)' : '' }} · {{ $brl($o['custo']) }} — {{ \Illuminate\Support\Str::limit($o['descricao'], 70) }}"></span>
                @endforeach
            </div>
        </div>

        {{-- Eixo --}}
        <div class="flex items-center gap-3">
            <div class="w-24 shrink-0"></div>
            <div class="relative h-4 flex-1">
                @foreach ($linha['eixo'] as $t)
                    @if ($t['mostrar'])
                        <span class="absolute text-[10px] text-gray-500 dark:text-gray-400" style="left: {{ $t['esq'] }}%">{{ $t['rotulo'] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Locado no período</div>
            <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $linha['taxa_locacao'] }}% do tempo</div>
        </div>
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Receita de locações (histórico)</div>
            <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $brl($linha['receita']) }}</div>
        </div>
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Manutenção (histórico)</div>
            <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $brl($linha['custo']) }}</div>
        </div>
        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <div class="text-xs text-gray-500 dark:text-gray-400">Receita menos manutenção</div>
            <div class="text-lg font-semibold {{ $saldoCor }}">{{ $brl($linha['saldo']) }}</div>
        </div>
    </div>
</div>
