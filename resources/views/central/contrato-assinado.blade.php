<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contrato assinado - {{ $cliente->name }}</title>
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
        iframe { width: 100%; height: 1100px; border: 1px solid #d1d5db; background: #fff; border-radius: 6px; }
        iframe.curta { height: 760px; }
    </style>
</head>
<body>
    <div class="barra">
        <h1>Contrato assinado — {{ $cliente->name }}<small>Documento e comprovante de assinatura. Nada é baixado ao abrir esta página.</small></h1>
        <a href="{{ \App\Filament\Central\Resources\ContratoAssinadoResource::getUrl('index', panel: 'central') }}">Voltar</a>
        <a class="principal" href="{{ route('central.contrato-assinado', ['signature' => $assinatura->id, 'baixar' => 1]) }}">Baixar PDF</a>
    </div>

    <div class="resumo">
        <div><b>Assinado por</b>{{ $assinatura->signer_name }}</div>
        <div><b>CPF/CNPJ</b>{{ $assinatura->signer_document ?: '—' }}</div>
        <div><b>E-mail</b>{{ $assinatura->signer_email ?: '—' }}</div>
        <div><b>Assinado em</b>{{ $assinatura->signed_at?->format('d/m/Y H:i') }}</div>
        <div><b>IP</b>{{ $assinatura->ip_address ?: '—' }}</div>
        <div><b>Código de segurança</b>{{ $assinatura->document_hash ? substr($assinatura->document_hash, 0, 24).'…' : '—' }}</div>
    </div>

    <div class="folha">
        <h2>Contrato</h2>
        <iframe srcdoc="{{ $contrato }}" title="Contrato"></iframe>
    </div>
    <div class="folha">
        <h2>Comprovante de assinatura</h2>
        <iframe class="curta" srcdoc="{{ $auditoria }}" title="Comprovante de assinatura"></iframe>
    </div>
</body>
</html>
