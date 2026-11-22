@php
    $r = $this->resumo();
    $d = $r ? $this->diagnostico($r) : null;
    $g = $r ? $this->graficos($r) : [];
    $cor = fn ($faixa) => ['Excelente' => '#16a34a', 'Bom' => '#2563eb', 'Atenção' => '#d97706', 'Crítico' => '#dc2626'][$faixa] ?? '#6b7280';
    $grade = 'display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));align-items:stretch';
    $f = fn ($v) => number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.');
    $contagem = $r ? collect($r['indicadores'])->whereNotNull('nota')->groupBy(fn ($i) => $i['nota'] >= 80 ? 'Excelente' : ($i['nota'] >= 60 ? 'Bom' : ($i['nota'] >= 35 ? 'Atenção' : 'Crítico')))->map->count() : collect();
@endphp

<x-filament-panels::page>
    @if (! $r)
        <x-intelligence.vazio
            titulo="Onde a sua locadora precisa de atenção"
            texto="O Diagnóstico lê os seus números e aponta, em ordem de gravidade, o que está fora do esperado, por que isso importa e o que já está bem."
            cor="#dc2626"
            :itens="[['Pontos de atenção', 'Os indicadores mais fracos, com a distância até a meta e o impacto no negócio.'], ['Pontos fortes', 'O que está funcionando e deve ser mantido.'], ['Mapa de prioridades', 'Veja o que é urgente: nota baixa e piorando.']]"
            :url="$this->urlImportacoes()" />
    @else
        {{-- Termômetro geral: quantos indicadores em cada faixa --}}
        <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(100%,170px),1fr))">
            @foreach (['Crítico' => 'Críticos', 'Atenção' => 'Em atenção', 'Bom' => 'Bons', 'Excelente' => 'Excelentes'] as $faixa => $rotulo)
                <div style="border-radius:14px;padding:14px 16px;color:#fff;background:linear-gradient(135deg,{{ $cor($faixa) }},{{ $cor($faixa) }}bb);box-shadow:0 6px 16px -8px {{ $cor($faixa) }}">
                    <div style="font-size:.72rem;opacity:.9;font-weight:600">{{ $rotulo }}</div>
                    <div style="font-size:2rem;font-weight:800;line-height:1.1">{{ $contagem[$faixa] ?? 0 }}</div>
                    <div style="font-size:.7rem;opacity:.85">{{ ($contagem[$faixa] ?? 0) == 1 ? 'indicador' : 'indicadores' }}</div>
                </div>
            @endforeach
        </div>

        <div style="{{ $grade }}">
            @if ($g['prioridade'])
                <x-filament::section heading="Mapa de prioridades" description="Quanto mais à esquerda e mais abaixo, mais urgente. Bolha maior = mais longe da meta.">
                    <x-intelligence.grafico :cfg="$g['prioridade']" :altura="320" />
                </x-filament::section>
            @endif
            @if ($g['polar'])
                <x-filament::section heading="Áreas em ordem de saúde">
                    <x-intelligence.grafico :cfg="$g['polar']" :altura="320" />
                </x-filament::section>
            @endif
        </div>

        {{-- Pontos de atenção --}}
        <x-filament::section heading="Principais pontos de atenção" :description="$d['atencao'] ? 'Do mais grave para o menos grave. Cada um mostra por que importa e para onde caminha.' : null">
            @if (! $d['atencao'])
                <div style="text-align:center;padding:18px;opacity:.85"><b style="color:#16a34a;font-size:1.05rem">Nenhum ponto crítico agora.</b><div style="font-size:.85rem;margin-top:4px">Todos os indicadores com nota estão em 60 ou mais. Continue acompanhando a cada importação.</div></div>
            @else
                <div style="display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr))">
                    @foreach ($d['atencao'] as $i)
                        <div style="border-radius:14px;padding:14px 16px;border:1px solid {{ $cor($i['faixa']) }}66;border-left:6px solid {{ $cor($i['faixa']) }};background:{{ $cor($i['faixa']) }}0d">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:8px">
                                <span style="font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;opacity:.65">{{ $i['area_rotulo'] }}</span>
                                <span style="font-size:.7rem;font-weight:700;color:#fff;background:{{ $cor($i['faixa']) }};border-radius:999px;padding:2px 10px">{{ $i['faixa'] }} · nota {{ number_format($i['nota'], 0) }}</span>
                            </div>
                            <x-intelligence.termometro :i="$i" />
                            <p style="font-size:.82rem;line-height:1.5;margin:10px 0 4px;opacity:.9"><b>Por que importa:</b> {{ $i['impacto'] }}</p>
                            @if ($i['delta'] !== null && $i['sentido'])
                                <div style="font-size:.76rem;color:{{ ['melhor' => '#16a34a', 'pior' => '#dc2626'][$i['sentido']] ?? '#6b7280' }}">
                                    {{ ['melhor' => '▲ Melhorou', 'pior' => '▼ Piorou', 'igual' => '■ Igual'][$i['sentido']] ?? '' }} desde a fotografia anterior ({{ $f($i['anterior']) }} → {{ $f($i['valor']) }} {{ $i['unidade'] }})
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <div style="{{ $grade }}">
            <x-filament::section heading="Pontos fortes" description="O que está bem e deve ser mantido.">
                @forelse ($d['fortes'] as $i)
                    <div style="display:flex;gap:12px;align-items:center;margin-bottom:10px">
                        <span style="flex:none;width:42px;height:42px;border-radius:12px;background:#16a34a;color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center">{{ number_format($i['nota'], 0) }}</span>
                        <div><b style="font-size:.88rem">{{ $i['titulo'] }}</b><div style="font-size:.76rem;opacity:.7">{{ $f($i['valor']) }} {{ $i['unidade'] }} · {{ $i['area_rotulo'] }}</div></div>
                    </div>
                @empty
                    <p style="opacity:.7;font-size:.88rem;margin:0">Ainda não há indicador com nota 80 ou mais. É por onde o plano de evolução começa.</p>
                @endforelse
            </x-filament::section>
            @if ($g['notas'])
                <x-filament::section heading="Nota de cada indicador" description="Do mais fraco ao mais forte.">
                    <x-intelligence.grafico :cfg="$g['notas']" :altura="max(260, count($g['notas']['data']['labels']) * 30)" />
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
