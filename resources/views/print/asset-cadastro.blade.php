<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Cadastro do ativo {{ $asset->patrimonio ?: $asset->name }}</title>
    <style>
        body { font-family: sans-serif; font-size: 13px; color: #111; margin: 24px; }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .sub { color: #555; margin-bottom: 16px; }
        h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #999; padding-bottom: 3px; margin: 18px 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: top; }
        td.l { width: 32%; color: #555; }
        .obs { white-space: pre-wrap; }
        @media print { body { margin: 0; } h2 { break-after: avoid; } table { break-inside: avoid; } }
    </style>
</head>
<body onload="window.print()">
    @include('partials.relatorio-cabecalho', ['tituloRelatorio' => 'Cadastro do Ativo', 'filtrosRelatorio' => ['Ativo: '.$asset->name.($asset->patrimonio ? ' — Patrimônio '.$asset->patrimonio : '')]])

    @foreach($secoes as $titulo => $linhas)
        <h2>{{ $titulo }}</h2>
        <table>
            @foreach($linhas as $rotulo => $valor)
                <tr><td class="l">{{ $rotulo }}</td><td>{{ $valor }}</td></tr>
            @endforeach
        </table>
    @endforeach

    @if(filled($descricao))
        <h2>Observações</h2>
        <div class="obs">{{ $descricao }}</div>
    @endif

    @include('partials.relatorio-rodape')
</body>
</html>
