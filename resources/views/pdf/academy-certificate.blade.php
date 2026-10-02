<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #1e293b; }
    .frame { margin: 28px; border: 6px solid #1e3a5f; padding: 6px; height: 500px; }
    .inner { border: 1px solid #94a3b8; height: 478px; text-align: center; padding-top: 34px; }
    .brand { font-size: 13px; letter-spacing: 4px; color: #1e3a5f; font-weight: bold; }
    h1 { font-size: 34px; margin: 18px 0 4px; color: #1e3a5f; letter-spacing: 2px; }
    .sub { font-size: 13px; color: #64748b; }
    .name { font-size: 30px; margin: 26px 0 6px; font-weight: bold; }
    .text { font-size: 15px; line-height: 1.6; padding: 0 70px; }
    .course { font-size: 22px; font-weight: bold; color: #1e3a5f; margin: 8px 0; }
    .meta { margin-top: 28px; font-size: 12px; color: #475569; }
    .code { margin-top: 26px; font-size: 11px; color: #64748b; }
    .line { width: 240px; border-top: 1px solid #334155; margin: 34px auto 4px; }
</style>
</head>
<body>
<div class="frame"><div class="inner">
    <div class="brand">ORAVEL ACADEMY</div>
    <h1>CERTIFICADO DE CONCLUSÃO</h1>
    <div class="sub">Sistema de Gestão de Locação e Manutenção de Equipamentos</div>

    <div class="text" style="margin-top:22px;">Certificamos que</div>
    <div class="name">{{ $c->user_name }}</div>
    <div class="text">
        da empresa <strong>{{ $c->tenant_name }}</strong>, concluiu com aproveitamento o curso
        <div class="course">{{ $c->course_title }}</div>
    </div>

    <div class="meta">
        Concluído em {{ $c->issued_at->format('d/m/Y') }}
        @if ($c->study_minutes > 0) · {{ $c->study_minutes }} min de estudo registrados @endif
    </div>

    <div class="line"></div>
    <div class="sub">Oravel Academy</div>

    <div class="code">
        Código de verificação: <strong>{{ $c->code }}</strong><br>
        Confira a autenticidade em {{ $verifyUrl }}
    </div>
</div></div>
</body>
</html>
