@php
    $r = $this->resumo();
    $cor = fn ($faixa) => ['Excelente' => '#16a34a', 'Bom' => '#2563eb', 'Atenção' => '#d97706', 'Crítico' => '#dc2626'][$faixa] ?? '#6b7280';
    $fmt = fn ($v, $u = '') => number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.').($u !== '' ? ' '.$u : '');
    $spark = function (array $serie, string $cor) {
        if (count($serie) < 2) return '';
        $min = min($serie); $max = max($serie); $faixa = ($max - $min) ?: 1;
        $pts = collect($serie)->map(fn ($v, $i) => round($i / (count($serie) - 1) * 100, 1).','.round(24 - (($v - $min) / $faixa) * 22 - 1, 1))->implode(' ');
        return '<svg viewBox="0 0 100 24" preserveAspectRatio="none" style="width:100%;height:28px"><polyline fill="none" stroke="'.$cor.'" stroke-width="2" vector-effect="non-scaling-stroke" points="'.$pts.'"/></svg>';
    };
    $seta = fn ($d) => $d === null ? '' : ($d > 0 ? '▲' : ($d < 0 ? '▼' : '■'));
    $corSentido = fn ($s) => ['melhor' => '#16a34a', 'pior' => '#dc2626'][$s] ?? '#6b7280';
    $areas = config('intelligence.areas');
@endphp

<x-filament-panels::page>
    @if (! $r)
        <x-filament::section>
            <div style="text-align:center;padding:24px 0">
                <div style="font-weight:600;font-size:1.05rem">Nenhuma fotografia da empresa ainda</div>
                <p style="margin:8px 0 16px;opacity:.7">Importe a planilha do modelo para calcular indicadores e o diagnóstico. A cada nova importação, a evolução aparece aqui.</p>
                <x-filament::button tag="a" :href="$this->urlImportacoes()">Ir para Importações</x-filament::button>
            </div>
        </x-filament::section>
    @else
        @php $g = $r['atual']['geral']; @endphp

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
            @if ($g)
                <x-filament::section>
                    <div style="font-size:.75rem;opacity:.7">Índice geral da empresa</div>
                    <div style="display:flex;align-items:baseline;gap:10px;margin-top:4px">
                        <span style="font-size:2.6rem;font-weight:700;line-height:1;color:{{ $cor($g['faixa']) }}">{{ number_format($g['nota'], 0) }}</span>
                        <span style="font-weight:600;color:{{ $cor($g['faixa']) }}">{{ $g['faixa'] }}</span>
                    </div>
                    <div style="font-size:.8rem;margin-top:6px;opacity:.8">
                        Referência {{ \Carbon\Carbon::parse($r['atual']['data'])->format('d/m/Y') }}
                        @if ($g['delta'] !== null) · <span style="color:{{ $g['delta'] >= 0 ? '#16a34a' : '#dc2626' }}">{{ $seta($g['delta']) }} {{ number_format(abs($g['delta']), 1, ',', '.') }} pts desde {{ \Carbon\Carbon::parse($r['anterior'])->format('d/m/Y') }}</span>@endif
                    </div>
                    <div style="margin-top:8px">{!! $spark(array_values(array_filter(array_column($r['historico'], 'geral'), fn ($v) => $v !== null)), $cor($g['faixa'])) !!}</div>
                    <div style="font-size:.7rem;opacity:.6;margin-top:4px">{{ $r['total_snapshots'] }} {{ $r['total_snapshots'] == 1 ? 'fotografia' : 'fotografias' }} no histórico</div>
                </x-filament::section>
            @endif

            <x-filament::section>
                <div style="font-weight:600;margin-bottom:6px">O que mudou</div>
                @if ($r['destaques'])
                    <ul style="margin:0;padding-left:18px;font-size:.85rem;line-height:1.5">
                        @foreach ($r['destaques'] as $f) <li>{{ $f }}</li> @endforeach
                    </ul>
                @else
                    <p style="font-size:.85rem;opacity:.7;margin:0">
                        {{ $r['anterior'] ? 'Nenhum indicador mudou desde a fotografia anterior.' : 'Com a próxima importação, aqui aparece o que melhorou e o que piorou.' }}
                    </p>
                @endif
            </x-filament::section>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
            @foreach ($r['areas'] as $a)
                @continue($a['chave'] === 'geral')
                <x-filament::section>
                    <div style="font-size:.75rem;opacity:.7">{{ $a['rotulo'] }}</div>
                    <div style="display:flex;align-items:baseline;gap:8px">
                        <span style="font-size:1.8rem;font-weight:700;color:{{ $cor($a['faixa']) }}">{{ number_format($a['nota'], 0) }}</span>
                        <span style="font-size:.8rem;color:{{ $cor($a['faixa']) }}">{{ $a['faixa'] }}</span>
                        @if ($a['delta'] !== null)<span style="font-size:.75rem;margin-left:auto;color:{{ $a['delta'] >= 0 ? '#16a34a' : '#dc2626' }}">{{ $seta($a['delta']) }} {{ number_format(abs($a['delta']), 1, ',', '.') }}</span>@endif
                    </div>
                    {!! $spark($a['serie'], $cor($a['faixa'])) !!}
                </x-filament::section>
            @endforeach
        </div>

        @foreach ($areas as $chave => $rotulo)
            @php $itens = collect($r['indicadores'])->where('area', $chave); @endphp
            @continue($itens->isEmpty())
            <x-filament::section :heading="$rotulo" :compact="true">
                <table style="width:100%;font-size:.85rem;border-collapse:collapse">
                    <thead><tr style="text-align:left;opacity:.6;font-size:.72rem"><th style="padding:4px 0">Indicador</th><th style="text-align:right">Atual</th><th style="text-align:right">Anterior</th><th style="text-align:right">Variação</th><th style="width:120px;padding-left:12px">Evolução</th></tr></thead>
                    <tbody>
                        @foreach ($itens as $i)
                            <tr style="border-top:1px solid rgba(128,128,128,.2)">
                                <td style="padding:6px 0">{{ $i['titulo'] }}</td>
                                <td style="text-align:right;font-weight:600">{{ $fmt($i['valor'], $i['unidade']) }}</td>
                                <td style="text-align:right;opacity:.7">{{ $i['anterior'] !== null ? $fmt($i['anterior'], $i['unidade']) : '—' }}</td>
                                <td style="text-align:right;color:{{ $corSentido($i['sentido']) }}">
                                    @if ($i['delta'] !== null){{ $seta($i['delta']) }} {{ $fmt(abs($i['delta']), $i['unidade'] === '%' ? 'pts' : $i['unidade']) }}@else — @endif
                                </td>
                                <td style="padding-left:12px">{!! $spark($i['serie'], $corSentido($i['sentido'])) !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
