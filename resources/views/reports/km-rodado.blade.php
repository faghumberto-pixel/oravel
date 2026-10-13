<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Km rodado da frota</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #999; padding-bottom: 3px; margin: 18px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; border-bottom: 1px solid #ddd; text-align: left; }
        th { border-bottom: 2px solid #111; }
        .n { text-align: right; }
        @media print { body { margin: 0; } table { break-inside: avoid; } }
    </style>
</head>
<body onload="window.print()">
    @include('partials.relatorio-cabecalho', ['tituloRelatorio' => 'Km rodado da frota', 'filtrosRelatorio' => ['Período: '.$periodo]])

    <h2>Por veículo</h2>
    <table>
        <thead><tr><th>Veículo</th><th class="n">Total rodado</th><th class="n">Em percursos</th><th class="n">Fora de percurso</th><th class="n">Viagens</th><th class="n">Custo estimado</th></tr></thead>
        <tbody>
            @foreach ($veiculos as $v)
                <tr><td>{{ $v['ativo']->placa ?? $v['ativo']->name }} — {{ $v['ativo']->name }}</td><td class="n">{{ number_format($v['total'], 0, ',', '.') }} km</td><td class="n">{{ number_format($v['em_percursos'], 0, ',', '.') }} km</td><td class="n">{{ number_format($v['fora_de_percurso'], 0, ',', '.') }} km</td><td class="n">{{ $v['viagens'] }}</td><td class="n">{{ $v['custo'] !== null ? 'R$ '.number_format($v['custo'], 2, ',', '.') : '—' }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Por percurso</h2>
    <table>
        <thead><tr><th>Percurso</th><th>Trajeto</th><th class="n">Previsto</th><th class="n">Viagens</th><th class="n">Km rodado</th><th class="n">Km médio</th><th class="n">Desvio</th></tr></thead>
        <tbody>
            @foreach ($percursos as $l)
                <tr><td>{{ $l['percurso']->nome }}</td><td>{{ $l['percurso']->trajeto() }}</td><td class="n">{{ $l['percurso']->km_previsto ? number_format((float) $l['percurso']->km_previsto, 1, ',', '.').' km' : '—' }}</td><td class="n">{{ $l['viagens'] }}</td><td class="n">{{ number_format($l['km_total'], 0, ',', '.') }} km</td><td class="n">{{ $l['km_medio'] !== null ? number_format($l['km_medio'], 1, ',', '.').' km' : '—' }}</td><td class="n">{{ $l['desvio_pct'] !== null ? (($l['desvio_pct'] > 0 ? '+' : '').number_format($l['desvio_pct'], 1, ',', '.').'%') : '—' }}</td></tr>
            @endforeach
        </tbody>
    </table>
    @include('partials.relatorio-rodape')
</body>
</html>
