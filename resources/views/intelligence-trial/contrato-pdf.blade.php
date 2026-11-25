<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11px;line-height:1.5;color:#111}
pre{white-space:pre-wrap;font-family:DejaVu Sans,sans-serif}
.box{margin-top:18px;padding:8px;border:1px solid #999;font-size:9px}
</style></head><body>
<pre>{{ $c->texto }}</pre>
<div class="box">
    <strong>Registro do aceite eletrônico</strong><br>
    Aceito por: {{ $c->responsavel }} ({{ $c->email }})<br>
    Data/hora: {{ $c->aceite_em->format('d/m/Y H:i:s') }} | IP: {{ $c->ip }}<br>
    Dispositivo: {{ \Illuminate\Support\Str::limit($c->user_agent, 120) }}<br>
    Versão do contrato: {{ $c->versao }} | SHA-256 do texto: {{ $c->hash }}
</div>
</body></html>
