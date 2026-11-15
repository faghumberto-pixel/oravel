@php
    // Formata o valor de uma celula pelo nome da coluna e pelo tipo (pt-BR).
    $formatar = function ($valor, string $coluna) {
        if ($valor === null || $valor === '') return '—';
        if (is_bool($valor)) return $valor ? 'Sim' : 'Não';
        if (is_numeric($valor)) {
            if (preg_match('/(custo|valor|preco|preço|total|price|cost)/i', $coluna)) {
                return 'R$ '.number_format((float) $valor, 2, ',', '.');
            }
            $f = (float) $valor;
            return floor($f) == $f ? number_format($f, 0, ',', '.') : rtrim(rtrim(number_format($f, 2, ',', '.'), '0'), ',');
        }
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/', $valor)) {
            try { return \Illuminate\Support\Carbon::parse($valor)->format(strlen($valor) > 10 ? 'd/m/Y H:i' : 'd/m/Y'); } catch (\Throwable $e) {}
        }
        return (string) $valor;
    };
    $rotulo = fn (string $c) => \Illuminate\Support\Str::of($c)->replace('_', ' ')->ucfirst();
    $url = $r->url();
    $textoZap = "Relatório Oravel — {$r->titulo}\n{$url}";
    $resumoCurto = \Illuminate\Support\Str::limit(trim((string) $r->resumo) !== '' ? $r->resumo : $r->titulo, 180);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ea580c">
    <title>Relatório Oravel — {{ $r->titulo }}</title>
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Oravel">
    <meta property="og:title" content="Relatório Oravel — {{ $r->titulo }}">
    <meta property="og:description" content="{{ $resumoCurto }}">
    <meta property="og:image" content="{{ asset('icon-512.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('icon.png') }}">
    <style>
        *{box-sizing:border-box}
        body{margin:0;font:15px/1.45 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#111827;background:#f3f4f6}
        .pagina{max-width:960px;margin:0 auto;padding:16px}
        .topo{display:flex;align-items:center;gap:12px;margin-bottom:12px}
        .topo img{width:40px;height:40px;border-radius:10px}
        .topo b{font-size:16px}.topo small{display:block;color:#6b7280;font-size:12px}
        .cartao{background:#fff;border-radius:12px;padding:16px;box-shadow:0 1px 2px rgba(0,0,0,.06)}
        h1{font-size:18px;margin:0 0 4px}
        .meta{color:#6b7280;font-size:12px;margin-bottom:10px}
        .resumo{margin:10px 0 14px;white-space:pre-wrap}
        .acoes{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
        .btn{display:inline-block;padding:10px 14px;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none;border:0;cursor:pointer}
        .zap{background:#16a34a;color:#fff}.sec{background:#e5e7eb;color:#111827}
        .tabela{overflow-x:auto;-webkit-overflow-scrolling:touch;border:1px solid #e5e7eb;border-radius:10px}
        table{border-collapse:collapse;width:100%;min-width:480px;font-size:13px}
        th{background:#f9fafb;text-align:left;padding:8px 10px;white-space:nowrap;border-bottom:1px solid #e5e7eb;position:sticky;top:0}
        td{padding:8px 10px;border-bottom:1px solid #f3f4f6;vertical-align:top}
        tr:nth-child(even) td{background:#fcfcfd}
        .rodape{color:#6b7280;font-size:11px;margin-top:12px;text-align:center}
        @media print{body{background:#fff}.acoes{display:none}.cartao{box-shadow:none;padding:0}.tabela{overflow:visible;border:0}}
    </style>
</head>
<body>
<div class="pagina">
    <div class="topo">
        <img src="{{ asset('icon.png') }}" alt="Oravel">
        <div><b>Oravel</b><small>Relatório{{ $cliente ? ' · '.$cliente : '' }}</small></div>
    </div>

    <div class="cartao">
        <h1>{{ $r->titulo }}</h1>
        <div class="meta">Gerado em {{ $r->created_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · {{ number_format($r->total_linhas, 0, ',', '.') }} {{ $r->total_linhas === 1 ? 'registro' : 'registros' }}</div>

        @if (trim((string) $r->resumo) !== '')
            <div class="resumo">{{ $r->resumo }}</div>
        @endif

        <div class="acoes">
            <a class="btn zap" href="https://wa.me/?text={{ rawurlencode($textoZap) }}" target="_blank" rel="noopener">Compartilhar no WhatsApp</a>
            <button class="btn sec" type="button" onclick="window.print()">Imprimir / salvar PDF</button>
        </div>

        @if (count($r->linhas))
            <div class="tabela">
                <table>
                    <thead><tr>@foreach ($r->colunas as $c)<th>{{ $rotulo($c) }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($r->linhas as $linha)
                        <tr>@foreach ($linha as $i => $valor)<td>{{ $formatar($valor, (string) ($r->colunas[$i] ?? '')) }}</td>@endforeach</tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p>Nenhum registro para este critério.</p>
        @endif
    </div>

    <div class="rodape">Relatório de consulta, somente leitura · válido até {{ $r->expires_at->timezone(config('app.timezone'))->format('d/m/Y') }} · Oravel</div>
</div>
</body>
</html>
