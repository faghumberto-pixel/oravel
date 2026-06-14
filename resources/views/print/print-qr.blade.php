<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Etiqueta {{ $asset->patrimonio }}</title>
    <style>
        body { font-family: sans-serif; text-align: center; padding-top: 20px; }
    </style>
</head>
<body onload="window.print()">
    <h3>Patrimônio: {{ $asset->patrimonio }}</h3>
    
    <div class="qr-container">
        {!! $qrCodeSvg !!}
    </div>
</body>
</html>