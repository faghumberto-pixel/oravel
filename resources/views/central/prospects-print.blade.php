<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Prospects — Oravel</title>
    <style>
        @page { margin: 12mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; font-size: 8px; line-height: 1.25; color: #111; margin: 0; }
        .header { display: flex; justify-content: space-between; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid #1a3a52; }
        .header h1 { margin: 0; font-size: 14px; color: #1a3a52; }
        .meta { font-size: 7px; color: #666; text-align: right; }
        .aviso { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 3px 6px; font-size: 7px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 7px; }
        th, td { padding: 2px 3px; border: 1px solid #ddd; vertical-align: top; }
        th { background: #1a3a52; color: #fff; font-weight: 600; font-size: 6px; text-transform: uppercase; }
        .nome { font-weight: 700; }
        .cnpj { font-size: 6px; color: #666; font-family: monospace; }
        .badge { display: inline-block; padding: 1px 4px; border-radius: 999px; font-size: 5.5px; font-weight: 700; }
        @media print { .no-print { display: none !important; } thead { display: table-header-group; } tr { page-break-inside: avoid; } }
        .print-btn { position: fixed; top: 8px; right: 8px; background: #1a3a52; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Imprimir / PDF</button>

    <div class="header">
        <h1>Prospects — Oravel</h1>
        <div class="meta">
            {{ now()->format('d/m/Y H:i') }} · {{ $prospects->count() }}/{{ $total }}
            @if ($descricao) — {{ $descricao }} @endif
        </div>
    </div>

    @if ($total > $prospects->count())
        <div class="aviso no-print">Mostra {{ $prospects->count() }} de {{ $total }}. Filtre mais.</div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width:18%">Empresa</th>
                <th style="width:10%">Cidade/Porte</th>
                <th style="width:14%">Atividade</th>
                <th style="width:5%">Aberta</th>
                <th style="width:3%">Nota</th>
                <th style="width:12%">Telefone/Email</th>
                <th style="width:12%">Sócio</th>
                <th style="width:11%">Situação</th>
                <th style="width:15%">Resultado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($prospects as $p)
                <tr>
                    <td><div class="nome">{{ $p->nome }}</div><div class="cnpj">{{ $p->cnpj }}</div></td>
                    <td>{{ $p->cidade }}<br><span style="font-size:6px">{{ $p->porte }}</span></td>
                    <td>{{ $p->atividade }}</td>
                    <td style="text-align:center">{{ $p->ano_abertura }}</td>
                    <td style="text-align:center">{{ $p->nota }}</td>
                    <td>{{ $p->telefone }}<br><span style="font-size:6px">{{ $p->email }}</span></td>
                    <td>{{ $p->socio }}</td>
                    <td><span class="badge" style="background:{{ $coresStatus[$p->status]['bg'] }};color:{{ $coresStatus[$p->status]['text'] }}">{{ \App\Models\Prospect::STATUS[$p->status] ?? $p->status }}</span></td>
                    <td>@if($p->resultado)<span class="badge" style="background:{{ $coresResultado[$p->resultado]['bg'] }};color:{{ $coresResultado[$p->resultado]['text'] }}">{{ \App\Models\Prospect::RESULTADOS[$p->resultado] }}</span>@else<span style="color:#999;font-size:6px">—</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>