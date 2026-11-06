<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas do estoque</title>
    @php
        // Tamanhos pensados para impressora de etiqueta ou folha A4 recortada.
        $tam = ['grande' => ['w' => '90mm', 'h' => '55mm', 'qr' => '42mm', 'f' => '13pt'], 'media' => ['w' => '60mm', 'h' => '38mm', 'qr' => '26mm', 'f' => '9pt'], 'pequena' => ['w' => '38mm', 'h' => '22mm', 'qr' => '17mm', 'f' => '6.5pt']][$modelo];
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f4f5; color: #111; }
        .barra { position: sticky; top: 0; z-index: 5; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; padding: 12px 16px; background: #fff; border-bottom: 1px solid #ddd; }
        .barra a, .barra button { padding: 8px 14px; border-radius: 8px; border: 1px solid #ccc; background: #fff; font-size: 14px; text-decoration: none; color: #111; cursor: pointer; }
        .barra button { background: #f97316; border-color: #f97316; color: #fff; font-weight: 700; }
        .folha { display: flex; flex-wrap: wrap; gap: 3mm; padding: 6mm; }
        .etq { width: {{ $tam['w'] }}; height: {{ $tam['h'] }}; padding: 1.6mm; display: flex; gap: 1.8mm; align-items: center; background: #fff; border: 0.3mm solid #111; border-radius: 1.2mm; overflow: hidden; page-break-inside: avoid; break-inside: avoid; }
        .etq .qr { flex: 0 0 {{ $tam['qr'] }}; width: {{ $tam['qr'] }}; height: {{ $tam['qr'] }}; }
        .etq .qr svg { width: 100%; height: 100%; display: block; }
        .etq .txt { min-width: 0; flex: 1; font-size: {{ $tam['f'] }}; line-height: 1.15; }
        .etq .titulo { font-weight: 800; font-size: 1.15em; word-break: break-word; }
        .etq .l { color: #333; margin-top: 0.6mm; word-break: break-word; }
        .etq .rod { margin-top: 0.8mm; font-size: 0.78em; font-weight: 700; letter-spacing: .04em; color: #555; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .folha { padding: 0; gap: 2mm; }
            @page { margin: 6mm; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <strong>{{ $total }} etiqueta(s)</strong>
        @foreach (['grande' => 'Grande (prateleira)', 'media' => 'Média (caixa)', 'pequena' => 'Pequena (saquinho, parafuso)'] as $m => $rotulo)
            <a href="{{ request()->fullUrlWithQuery(['modelo' => $m]) }}" @if ($m === $modelo) style="background:#111;color:#fff" @endif>{{ $rotulo }}</a>
        @endforeach
        @if ($total > 600)<span style="color:#b45309">Mostrando só as 600 primeiras: use os filtros.</span>@endif
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>
    <div class="folha">
        @forelse ($etiquetas as $e)
            <div class="etq">
                <div class="qr">{!! $e['qr'] !!}</div>
                <div class="txt">
                    <div class="titulo">{{ $e['titulo'] }}</div>
                    @if (! empty($e['linha1']))<div class="l">{{ $e['linha1'] }}</div>@endif
                    @if (! empty($e['linha2']))<div class="l">{{ $e['linha2'] }}</div>@endif
                    <div class="rod">{{ $e['rodape'] }}</div>
                </div>
            </div>
        @empty
            <p style="padding:24px">Nenhuma etiqueta para os filtros escolhidos.</p>
        @endforelse
    </div>
</body>
</html>
