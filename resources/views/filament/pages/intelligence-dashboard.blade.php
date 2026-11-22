@php
    $r = $this->resumo();
    $g = $r ? $this->graficos($r) : [];
    $cor = fn ($faixa) => ['Excelente' => '#16a34a', 'Bom' => '#2563eb', 'Atenção' => '#d97706', 'Crítico' => '#dc2626'][$faixa] ?? '#6b7280';
    $seta = fn ($d) => $d === null ? '' : ($d > 0 ? '▲' : ($d < 0 ? '▼' : '■'));
    $grade = 'display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));align-items:stretch';
@endphp

<x-filament-panels::page>
    @if (! $r)
        <x-intelligence.vazio
            titulo="O Índice Oravel de Saúde da sua locadora"
            texto="Uma nota de 0 a 100 que resume como a sua empresa está, com a leitura de cada área e a comparação com o mês anterior. Você envia uma planilha simples e o sistema faz o resto."
            :itens="[['Nota geral e por área', 'Frota, finanças, comercial, oficina e operação em um só índice, com cores de semáforo.'], ['Evolução mês a mês', 'Veja se a empresa está melhorando ou piorando, e onde.'], ['Números do mês', 'Receita, resultado, disponibilidade e atraso, sempre na tela inicial.']]"
            :url="$this->urlImportacoes()" />
    @else
        @php $geral = $r['atual']['geral']; @endphp

        {{-- Números do mês --}}
        @if ($g['kpis'])
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr))">
                @foreach ($g['kpis'] as $k) <x-intelligence.kpi :k="$k" /> @endforeach
            </div>
        @endif

        {{-- Panorama --}}
        <div style="{{ $grade }}">
            @if ($geral && $g['geral'])
                <x-filament::section heading="Índice geral">
                    <x-intelligence.grafico :cfg="$g['geral']" :altura="190">
                        <div style="position:absolute;left:0;right:0;bottom:6px;text-align:center;pointer-events:none">
                            <div style="font-size:2.7rem;font-weight:800;line-height:1;color:{{ $cor($geral['faixa']) }}">{{ number_format($geral['nota'], 0) }}</div>
                            <div style="font-weight:600;color:{{ $cor($geral['faixa']) }}">{{ $geral['faixa'] }}</div>
                        </div>
                    </x-intelligence.grafico>
                    <div style="font-size:.8rem;margin-top:10px;opacity:.85;text-align:center">
                        Referência {{ \Carbon\Carbon::parse($r['atual']['data'])->format('d/m/Y') }}
                        @if ($geral['delta'] !== null)
                            · <span style="color:{{ $geral['delta'] >= 0 ? '#16a34a' : '#dc2626' }}">{{ $seta($geral['delta']) }} {{ number_format(abs($geral['delta']), 1, ',', '.') }} pts desde {{ \Carbon\Carbon::parse($r['anterior'])->format('d/m/Y') }}</span>
                        @endif
                        <div style="font-size:.7rem;opacity:.6">{{ $r['total_snapshots'] }} {{ $r['total_snapshots'] == 1 ? 'fotografia' : 'fotografias' }} no histórico</div>
                    </div>
                </x-filament::section>
            @endif
            @if ($g['radar'])
                <x-filament::section heading="Saúde por área"><x-intelligence.grafico :cfg="$g['radar']" :altura="270" /></x-filament::section>
            @endif
            @if ($g['polar'])
                <x-filament::section heading="Peso de cada área" description="Quanto cada área contribui para a saúde da empresa.">
                    <x-intelligence.grafico :cfg="$g['polar']" :altura="270" />
                </x-filament::section>
            @endif
        </div>

        {{-- O que mudou + áreas --}}
        <div style="{{ $grade }}">
            <x-filament::section heading="O que mudou desde a última fotografia">
                @if ($r['destaques'])
                    <ul style="margin:0;padding-left:18px;font-size:.9rem;line-height:1.7">@foreach ($r['destaques'] as $f) <li>{{ $f }}</li> @endforeach</ul>
                @else
                    <p style="font-size:.88rem;opacity:.7;margin:0">{{ $r['anterior'] ? 'Nenhum indicador mudou desde a fotografia anterior.' : 'Com a próxima importação, aqui aparece o que melhorou e o que piorou.' }}</p>
                @endif
            </x-filament::section>
            <x-filament::section heading="Nota de cada área">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px">
                    @foreach ($r['areas'] as $a)
                        @continue($a['chave'] === 'geral')
                        <div style="border-radius:12px;padding:10px 12px;border:1px solid {{ $cor($a['faixa']) }}55;background:{{ $cor($a['faixa']) }}12">
                            <div style="font-size:.68rem;opacity:.75">{{ $a['rotulo'] }}</div>
                            <div style="display:flex;align-items:baseline;gap:6px">
                                <b style="font-size:1.5rem;color:{{ $cor($a['faixa']) }}">{{ number_format($a['nota'], 0) }}</b>
                                <span style="font-size:.7rem;color:{{ $cor($a['faixa']) }}">{{ $a['faixa'] }}</span>
                            </div>
                            @if ($a['delta'] !== null)<div style="font-size:.7rem;color:{{ $a['delta'] >= 0 ? '#16a34a' : '#dc2626' }}">{{ $seta($a['delta']) }} {{ number_format(abs($a['delta']), 1, ',', '.') }} pts</div>@endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- Evolução --}}
        @if ($g['evolucao'])
            <x-filament::section heading="Evolução do índice e das áreas" description="Clique na legenda para mostrar ou esconder uma área.">
                <x-intelligence.grafico :cfg="$g['evolucao']" :altura="320" />
            </x-filament::section>
        @endif
        @if ($g['calor'])
            <x-filament::section heading="Mapa de calor" description="A nota de cada área em cada fotografia: onde a cor melhora ou piora ao longo do tempo.">
                <x-intelligence.calor :calor="$g['calor']" />
            </x-filament::section>
        @endif
    @endif

    @if ($this->podeUsarChat())
        <x-filament::section heading="Pergunte ao assistente" description="Respostas sobre a saúde e a evolução da empresa, com base nas planilhas importadas. Só consulta.">
            <livewire:assistente.intelligence-chat :embutido="true" />
        </x-filament::section>
    @endif
</x-filament-panels::page>
