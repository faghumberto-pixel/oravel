<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f2f4; color: #1f2937; font-family: 'Helvetica', 'Arial', sans-serif; }
        .barra { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 16px; padding: 12px 20px; background: #111827; color: #fff; }
        .barra h1 { margin: 0; font-size: 16px; flex: 1 1 260px; }
        .barra small { display: block; font-weight: normal; color: #9ca3af; font-size: 12px; }
        .barra a { color: #fff; text-decoration: none; font-size: 13px; font-weight: bold; padding: 8px 14px; border-radius: 6px; background: #374151; }
        .barra a.principal { background: #E8541A; }
        .resumo { display: flex; flex-wrap: wrap; gap: 8px 28px; max-width: 900px; margin: 18px auto 0; padding: 0 16px; font-size: 13px; }
        .resumo b { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .folha { max-width: 900px; margin: 14px auto 28px; padding: 0 16px; }
        .folha h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: #4b5563; margin: 0 0 6px; }
        iframe { width: 100%; height: 1100px; border: 1px solid #d1d5db; background: #fff; border-radius: 6px; display: block; }
        @page { margin: 12mm; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .resumo, .folha { max-width: none; padding: 0; margin-left: 0; margin-right: 0; }
            .folha { margin-bottom: 10px; }
            iframe { border: 0; border-radius: 0; }
            .folha.nova-pagina { break-before: page; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <h1>{{ $titulo }}<small>{{ $subtitulo ?? 'Use Imprimir para papel ou para salvar em PDF pelo navegador.' }}</small></h1>
        <a href="{{ $voltar }}">Voltar</a>
        <a class="principal" href="#" onclick="window.print(); return false;">Imprimir</a>
    </div>

    <div class="resumo">
        @foreach($resumo as $rotulo => $valor)
            <div><b>{{ $rotulo }}</b>{{ $valor }}</div>
        @endforeach
    </div>

    @foreach($secoes as $secao)
        <div class="folha {{ !empty($secao['nova_pagina']) ? 'nova-pagina' : '' }}">
            @if(filled($secao['titulo']))<h2>{{ $secao['titulo'] }}</h2>@endif
            <iframe srcdoc="{{ $secao['html'] }}" title="{{ $secao['titulo'] }}"></iframe>
        </div>
    @endforeach
    <script>
        // Cada documento aparece inteiro (sem barra de rolagem interna), para
        // a impressão sair com todas as páginas.
        function ajustar(f) {
            try { f.style.height = (f.contentDocument.documentElement.scrollHeight + 4) + 'px'; } catch (e) {}
        }
        document.querySelectorAll('iframe').forEach(function (f) {
            f.addEventListener('load', function () { ajustar(f); });
            ajustar(f);
        });
        window.addEventListener('beforeprint', function () { document.querySelectorAll('iframe').forEach(ajustar); });
    </script>
</body>
</html>
