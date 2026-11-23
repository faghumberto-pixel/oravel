@php
    $d = $this->dados();
    $e = $d['atual'];
    $g = $this->graficos($d['historico']);
    $rot = \App\Domain\Intelligence\Services\AnaliseDeEquipamentos::ROTULOS;
    $cc = \App\Domain\Intelligence\Support\Graficos::CLASSE_COR;
    $cor = $cc[$e['classificacao']] ?? '#94a3b8';
    $brl = fn ($v) => $v === null ? '—' : 'R$ '.number_format($v, 0, ',', '.');
    $pct = fn ($v, $c = 0) => $v === null ? '—' : number_format($v, $c, ',', '.').'%';
    $grade = 'display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));align-items:stretch';
    $tend = ['piorando' => ['▼ piorando', '#dc2626'], 'melhorando' => ['▲ melhorando', '#16a34a'], 'estavel' => ['■ estável', '#64748b']][$e['tendencia'] ?? ''] ?? null;
    $tiles = [
        ['Utilização', $pct($e['utilizacao_pct']), '#8b5cf6'], ['Disponibilidade', $pct($e['disponibilidade_pct']), '#0ea5e9'],
        ['Retorno anualizado', $pct($e['retorno_anual_pct']), ($e['retorno_anual_pct'] ?? 0) < 0 ? '#dc2626' : '#16a34a'],
        ['Custo acumulado ÷ valor', $e['razao_custo_valor'] === null ? '—' : $pct($e['razao_custo_valor'] * 100), ($e['razao_custo_valor'] ?? 0) >= 0.6 ? '#dc2626' : (($e['razao_custo_valor'] ?? 0) >= 0.4 ? '#d97706' : '#4f46e5')],
        ['Reincidência', $pct($e['reincidencia_pct']), ($e['reincidencia_pct'] ?? 0) >= 30 ? '#d97706' : '#64748b'], ['Idade', $e['idade_anos'] === null ? '—' : $e['idade_anos'].' anos', '#475569'],
    ];
@endphp

<x-filament-panels::page>
    <div><a href="{{ $this->urlLista() }}" style="font-size:.82rem;text-decoration:none;color:#4f46e5">← Todos os equipamentos</a></div>

    <x-filament::section>
        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:center;justify-content:space-between">
            <div>
                <div style="font-size:.7rem;font-weight:700;letter-spacing:.05em;opacity:.6;text-transform:uppercase">{{ $e['categoria'] ?? 'Equipamento' }}</div>
                <div style="font-size:1.4rem;font-weight:800">{{ $e['patrimonio'] }} <span style="font-weight:600;opacity:.75;font-size:1.05rem">· {{ $e['nome'] }}</span></div>
                <div style="font-size:.8rem;opacity:.7;margin-top:2px">Valor {{ $brl($e['valor']) }} · Receita no mês {{ $brl($e['receita']) }} · Custo no mês {{ $brl($e['custo_mes']) }} · Acumulado {{ $brl($e['custo_acumulado']) }}</div>
            </div>
            <div style="text-align:right">
                <span style="display:inline-block;font-size:.85rem;font-weight:800;color:#fff;background:{{ $cor }};border-radius:999px;padding:5px 16px">{{ $rot[$e['classificacao']] ?? $e['classificacao'] }}</span>
                @if ($tend)<div style="font-size:.78rem;margin-top:5px;color:{{ $tend[1] }}">{{ $tend[0] }} desde a fotografia anterior</div>@endif
            </div>
        </div>
        <div style="margin-top:14px;border-radius:12px;padding:12px 14px;border:1px solid {{ $cor }}55;border-left:5px solid {{ $cor }};background:{{ $cor }}10;font-size:.9rem;line-height:1.5"><b>Por que está aqui:</b> {{ $e['motivo'] }}</div>
    </x-filament::section>

    <div style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(min(100%,160px),1fr))">
        @foreach ($tiles as [$t, $v, $c])
            <div style="border-radius:14px;padding:12px 14px;color:#fff;background:linear-gradient(135deg,{{ $c }},{{ $c }}bb)"><div style="font-size:.7rem;opacity:.92;font-weight:600">{{ $t }}</div><div style="font-size:1.45rem;font-weight:800">{{ $v }}</div></div>
        @endforeach
    </div>

    @if ($this->acoes($e['classificacao']))
        <x-filament::section heading="O que fazer">
            <ul style="margin:0;padding-left:18px;font-size:.9rem;line-height:1.7">@foreach ($this->acoes($e['classificacao']) as $a) <li>{{ $a }}</li> @endforeach</ul>
        </x-filament::section>
    @endif

    @if ($g['uso'])
        <div style="{{ $grade }}">
            <x-filament::section heading="Utilização e disponibilidade"><x-intelligence.grafico :cfg="$g['uso']" :altura="260" /></x-filament::section>
            <x-filament::section heading="Receita e custo de manutenção"><x-intelligence.grafico :cfg="$g['dinheiro']" :altura="260" /></x-filament::section>
            <x-filament::section heading="Corretivas, preventivas e reincidência"><x-intelligence.grafico :cfg="$g['manutencao']" :altura="260" /></x-filament::section>
        </div>
        <x-filament::section heading="Histórico fotografia a fotografia" :compact="true">
            <table style="width:100%;font-size:.82rem;border-collapse:collapse">
                <thead><tr style="text-align:left;opacity:.6;font-size:.7rem"><th style="padding:5px 0">Data</th><th>Situação</th><th style="text-align:right">Utilização</th><th style="text-align:right">Custo no mês</th><th style="text-align:right">Custo ÷ valor</th></tr></thead>
                <tbody>
                    @foreach (array_reverse($d['historico']) as $h)
                        <tr style="border-top:1px solid rgba(128,128,128,.2)">
                            <td style="padding:6px 0">{{ \Carbon\Carbon::parse($h['data'])->format('d/m/Y') }}</td>
                            <td><span style="font-size:.66rem;font-weight:700;color:#fff;background:{{ $cc[$h['classificacao']] ?? '#94a3b8' }};border-radius:999px;padding:2px 9px">{{ $rot[$h['classificacao']] ?? $h['classificacao'] }}</span></td>
                            <td style="text-align:right">{{ $pct($h['utilizacao_pct']) }}</td><td style="text-align:right">{{ $brl($h['custo_mes']) }}</td>
                            <td style="text-align:right">{{ $h['razao_custo_valor'] === null ? '—' : $pct($h['razao_custo_valor'] * 100) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @else
        <x-filament::section><p style="margin:0;opacity:.75;font-size:.9rem">Com a próxima importação aparece a evolução deste equipamento mês a mês.</p></x-filament::section>
    @endif
</x-filament-panels::page>
