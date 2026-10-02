<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Verificação de certificado | Oravel Academy</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f1f5f9; color: #1e293b; }
        .card { background: #fff; max-width: 460px; width: calc(100% - 32px); padding: 28px; border-radius: 14px; box-shadow: 0 4px 24px rgba(0,0,0,.08); text-align: center; }
        .ok { color: #15803d; font-weight: 700; font-size: 18px; }
        .bad { color: #b91c1c; font-weight: 700; font-size: 18px; }
        dl { text-align: left; margin: 18px 0 0; font-size: 14px; } dt { color: #64748b; font-size: 12px; margin-top: 10px; } dd { margin: 2px 0 0; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <p style="letter-spacing:3px;font-size:12px;color:#1e3a5f;font-weight:700;margin:0 0 14px;">ORAVEL ACADEMY</p>
    @if ($certificate)
        <p class="ok">✓ Certificado autêntico</p>
        <dl>
            <dt>Nome</dt><dd>{{ $certificate->user_name }}</dd>
            <dt>Empresa</dt><dd>{{ $certificate->tenant_name }}</dd>
            <dt>Curso</dt><dd>{{ $certificate->course_title }}</dd>
            <dt>Concluído em</dt><dd>{{ $certificate->issued_at->format('d/m/Y') }}</dd>
            <dt>Código</dt><dd>{{ $certificate->code }}</dd>
        </dl>
    @else
        <p class="bad">Certificado não encontrado</p>
        <p style="font-size:14px;color:#64748b;">Confira o código digitado. Se o problema continuar, fale com a Oravel.</p>
    @endif
</div>
</body>
</html>
