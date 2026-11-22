@php
    $opcoes = $this->opcoes();
    $c = count($opcoes) >= 2 ? $this->comparacao() : null;
    $g = $c ? $this->graficos($c) : [];
    $seta = fn ($d) => $d === null ? '' : ($d > 0 ? '▲' : ($d < 0 ? '▼' : '■'));
    $f = fn ($v, $u = '') => $v === null ? '—' : number_format($v, abs($v) >= 100 ? 0 : 1, ',', '.').($u !== '' ? ' '.$u : '');
    $corS = fn ($s) => ['melhor' => '#16a34a', 'pior' => '#dc2626'][$s] ?? '#6b7280';
    $grade = 'display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));align-items:stretch';
    $areas = config('intelligence.areas');
@endphp

<x-filament-panels::page>
    @if (count($opcoes) < 2)
        <x-intelligence.vazio
            titulo="A sua locadora contra ela mesma"
            texto="A comparação interna coloca duas fotografias lado a lado, por exemplo este mês contra o mesmo mês do ano passado, e mostra o que evoluiu e o que ficou para trás. Precisa de pelo menos duas importações."
            cor="#0ea5e9"
            :itens="[['Escolha dois momentos', 'Compare qualquer par de fotografias já importadas.'], ['Lado a lado', 'Radar e barras por área, e todos os indicadores com a variação.'], ['Sem expor ninguém', 'É só a sua empresa: nenhum dado de outra locadora aparece.']]"
            :url="$this->urlImportacoes()" />
        @if (count($opcoes) === 1)
            <x-filament::section><p style="margin:0;opacity:.8;font-size:.9rem">Você já tem 1 fotografia. Importe mais uma planilha para liberar a comparação.</p></x-filament::section>
        @endif
    @else
        <x-filament::section>
            <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end">
                <label style="font-size:.78rem;font-weight:600">Comparar
                    <select wire:model.live="de" style="display:block;margin-top:4px;padding:8px 12px;border-radius:10px;border:1px solid rgba(128,128,128,.4);background:transparent;min-width:170px">
                        @foreach ($opcoes as $id => $data) <option value="{{ $id }}">{{ $data }}</option> @endforeach
                    </select>
                </label>
                <span style="padding-bottom:10px;font-weight:800;opacity:.5">contra</span>
                <label style="font-size:.78rem;font-weight:600">Fotografia mais recente
                    <select wire:model.live="para" style="display:block;margin-top:4px;padding:8px 12px;border-radius:10px;border:1px solid rgba(128,128,128,.4);background:transparent;min-width:170px">
                        @foreach ($opcoes as $id => $data) <option value="{{ $id }}">{{ $data }}</option> @endforeach
                    </select>
                </label>
            </div>
        </x-filament::section>

        @if (! $c)
            <x-filament::section><p style="margin:0;opacity:.8">Escolha duas fotografias diferentes para comparar.</p></x-filament::section>
        @else
            <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr))">
                @foreach ($c['areas'] as $a)
                    @continue($a['delta'] === null)
                    @php $cr = $a['delta'] > 0 ? '#16a34a' : ($a['delta'] < 0 ? '#dc2626' : '#64748b'); @endphp
                    <div style="border-radius:14px;padding:14px 16px;color:#fff;background:linear-gradient(135deg,{{ $cr }},{{ $cr }}bb)">
                        <div style="font-size:.72rem;opacity:.9;font-weight:600">{{ $a['rotulo'] }}</div>
                        <div style="font-size:1.6rem;font-weight:800">{{ $seta($a['delta']) }} {{ number_format(abs($a['delta']), 1, ',', '.') }} pts</div>
                        <div style="font-size:.72rem;opacity:.9">{{ number_format($a['de'], 0) }} → {{ number_format($a['para'], 0) }}</div>
                    </div>
                @endforeach
            </div>

            <div style="{{ $grade }}">
                @if ($g['radar']) <x-filament::section heading="Radar das áreas"><x-intelligence.grafico :cfg="$g['radar']" :altura="300" /></x-filament::section> @endif
                @if ($g['barras']) <x-filament::section heading="Nota por área"><x-intelligence.grafico :cfg="$g['barras']" :altura="300" /></x-filament::section> @endif
            </div>

            <x-filament::section heading="Todos os indicadores" description="Verde melhorou, vermelho piorou, cinza é só informação.">
                @foreach ($areas as $chave => $rotulo)
                    @php $itens = collect($c['indicadores'])->where('area', $chave); @endphp
                    @continue($itens->isEmpty())
                    <div style="font-weight:700;margin:12px 0 4px;font-size:.85rem">{{ $rotulo }}</div>
                    <table style="width:100%;font-size:.84rem;border-collapse:collapse">
                        <thead><tr style="text-align:left;opacity:.6;font-size:.72rem"><th style="padding:4px 0">Indicador</th><th style="text-align:right">{{ \Carbon\Carbon::parse($c['de'])->format('d/m/Y') }}</th><th style="text-align:right">{{ \Carbon\Carbon::parse($c['para'])->format('d/m/Y') }}</th><th style="text-align:right">Variação</th></tr></thead>
                        <tbody>
                            @foreach ($itens as $i)
                                <tr style="border-top:1px solid rgba(128,128,128,.2)">
                                    <td style="padding:7px 0">{{ $i['titulo'] }}</td>
                                    <td style="text-align:right;opacity:.75">{{ $f($i['de'], $i['unidade']) }}</td>
                                    <td style="text-align:right;font-weight:700">{{ $f($i['para'], $i['unidade']) }}</td>
                                    <td style="text-align:right"><span style="color:{{ $corS($i['sentido']) }};font-weight:600">{{ $i['delta'] === null ? '—' : $seta($i['delta']).' '.$f(abs($i['delta']), $i['unidade'] === '%' ? 'pts' : $i['unidade']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
