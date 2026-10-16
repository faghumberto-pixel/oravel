<x-filament-panels::page>
    @php
        $rotulos = ['valor' => 'Vendas (R$)', 'ganhos' => 'Negócios ganhos', 'visitas' => 'Visitas realizadas'];
        $fmt = fn ($ind, $v) => $v === null ? '—' : ($ind === 'valor' ? 'R$ '.number_format($v, 2, ',', '.') : number_format($v, 0, ',', '.'));
        $corBarra = fn ($p) => $p === null ? 'bg-gray-300' : ($p >= 100 ? 'bg-emerald-500' : ($p >= 60 ? 'bg-amber-500' : 'bg-red-500'));
        $btn = 'px-3 py-1.5 text-xs font-bold rounded-lg border border-gray-300 dark:border-gray-600';
    @endphp

    <div class="flex items-center gap-2">
        <button type="button" wire:click="mesAnterior" class="{{ $btn }}">←</button>
        <input type="month" wire:model.live="mes" class="text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
        <button type="button" wire:click="mesSeguinte" class="{{ $btn }}">→</button>
    </div>

    @foreach (array_merge([['titulo' => 'Meu desempenho', 'd' => $this->meus]], $this->empresa ? [['titulo' => 'Empresa / equipe', 'd' => $this->empresa]] : []) as $bloco)
        @php $d = $bloco['d']; @endphp
        <x-filament::section :heading="$bloco['titulo'].' — '.$d['mes']->format('m/Y')">
            @unless($d['tem_meta'])
                <p class="mb-3 text-sm text-amber-600">Nenhuma meta definida para este mês. @if($this->veTudo()) Cadastre em Comercial → Gestão CRM → Metas. @else Peça ao gestor para definir. @endif</p>
            @endunless

            <div class="grid gap-4 md:grid-cols-3">
                @foreach($rotulos as $ind => $rotulo)
                    @php $p = $d['atingimento'][$ind]; @endphp
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <p class="text-xs font-bold uppercase text-gray-500">{{ $rotulo }}</p>
                        <p class="mt-1 text-2xl font-black">{{ $fmt($ind, $d['realizado'][$ind]) }}</p>
                        <p class="text-xs text-gray-500">Meta: {{ $fmt($ind, $d['meta'][$ind]) }}</p>
                        <div class="mt-2 h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-2 rounded-full {{ $corBarra($p) }}" style="width: {{ min(100, max(0, $p ?? 0)) }}%"></div>
                        </div>
                        <p class="mt-1 text-xs">{{ $p === null ? 'sem meta' : number_format($p, 0, ',', '.').'% da meta' }}
                            @if($d['falta'][$ind] !== null && $d['falta'][$ind] > 0) · falta {{ $fmt($ind, $d['falta'][$ind]) }} @endif</p>
                        @if($d['ritmo'][$ind] !== null && $d['falta'][$ind] > 0)
                            <p class="text-xs text-gray-500">Ritmo: {{ $ind === 'valor' ? 'R$ '.number_format($d['ritmo'][$ind], 2, ',', '.') : number_format($d['ritmo'][$ind], 1, ',', '.') }} por dia útil ({{ $d['dias_uteis_restantes'] }} restantes)</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                Previsto no pipeline para o mês (ponderado pela etapa): <b>R$ {{ number_format($d['previsto'], 2, ',', '.') }}</b>
                · vendido + previsto: <b>R$ {{ number_format($d['realizado_mais_previsto'], 2, ',', '.') }}</b>
            </p>
        </x-filament::section>
    @endforeach

    @if($this->veTudo())
        <x-filament::section heading="Equipe">
            @if(count($this->equipe) === 0)
                <p class="text-sm text-gray-500">Nenhum vendedor com meta ou venda neste mês.</p>
            @else
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="py-1">Vendedor</th><th>Vendido</th><th>Meta</th><th>Atingimento</th><th>Ganhos</th><th>Visitas</th></tr></thead>
                    <tbody>
                        @foreach($this->equipe as $l)
                            @php $p = $l['atingimento']['valor']; @endphp
                            <tr class="border-t border-gray-100 dark:border-gray-800" wire:key="eq-{{ $l['usuario']->id }}">
                                <td class="py-1.5 font-semibold">{{ $l['usuario']->name }}</td>
                                <td>R$ {{ number_format($l['realizado']['valor'], 2, ',', '.') }}</td>
                                <td>{{ $fmt('valor', $l['meta']['valor']) }}</td>
                                <td class="w-48">
                                    <div class="h-2 w-full rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-2 rounded-full {{ $corBarra($p) }}" style="width: {{ min(100, max(0, $p ?? 0)) }}%"></div></div>
                                    <span class="text-xs">{{ $p === null ? 'sem meta' : number_format($p, 0, ',', '.').'%' }}</span>
                                </td>
                                <td>{{ $l['realizado']['ganhos'] }}{{ $l['meta']['ganhos'] ? ' / '.$l['meta']['ganhos'] : '' }}</td>
                                <td>{{ $l['realizado']['visitas'] }}{{ $l['meta']['visitas'] ? ' / '.$l['meta']['visitas'] : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
