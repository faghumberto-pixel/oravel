<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Folha da O.S. {{ $os->os_number }}</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#e9edf3;color:#0f172a;font-family:Arial,Helvetica,sans-serif}
.barra{position:sticky;top:0;z-index:10;background:#08142c;color:#fff;padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center}
.barra a,.barra button{font:700 14px Arial,sans-serif;border-radius:8px;border:1.5px solid rgba(255,255,255,.45);background:transparent;color:#fff;padding:9px 14px;text-decoration:none;cursor:pointer}
.barra button{background:#f97316;border-color:#f97316;color:#0f172a}.barra span{font-size:13px;color:#cbd5e1}
.folha{position:relative;width:210mm;min-height:297mm;margin:14px auto;background:#fff;padding:11mm 13mm 10mm;box-shadow:0 8px 30px rgba(15,23,42,.18)}
.canto{position:absolute;width:7mm;height:7mm;border:0 solid #0f172a}
.tl{left:5mm;top:5mm;border-left-width:.5mm;border-top-width:.5mm}.tr{right:5mm;top:5mm;border-right-width:.5mm;border-top-width:.5mm}
.bl{left:5mm;bottom:5mm;border-left-width:.5mm;border-bottom-width:.5mm}.br{right:5mm;bottom:5mm;border-right-width:.5mm;border-bottom-width:.5mm}
.cab{display:grid;grid-template-columns:34mm 1fr 52mm 20mm;gap:5mm;align-items:end;border-bottom:.6mm solid #0f172a;padding-bottom:3mm}
.marca{font-weight:900;font-size:6.2mm;letter-spacing:-.02em;display:flex;align-items:center;gap:2mm}
.pts{display:inline-flex;gap:1mm}.pts b{width:2.2mm;height:2.2mm;border-radius:50%;display:block}.pts b:nth-child(1){background:#ef4444}.pts b:nth-child(2){background:#f59e0b}.pts b:nth-child(3){background:#22c55e}
.emp small,.nro small{display:block;font-size:2.6mm;text-transform:uppercase;letter-spacing:.06em;color:#475569;font-weight:700}
.emp strong{font-size:4mm}.nro{text-align:right}.nro strong{font-size:6.6mm;letter-spacing:.01em;font-variant-numeric:tabular-nums}
.qr svg{width:18mm;height:18mm;display:block}
.sec{margin:3.2mm 0 1.6mm;font-size:2.8mm;font-weight:800;text-transform:uppercase;letter-spacing:.07em;padding:1.2mm 2.4mm;border-radius:1mm}
.sec.imp{background:#e2e8f0;color:#334155}.sec.mao{background:#fff1e4;color:#c2410c;border-left:1.2mm solid #f97316}
.campo{margin-bottom:2.6mm;min-width:0}
.campo label{display:block;font-size:2.6mm;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#334155;margin-bottom:.8mm}
.imp-v{display:block;min-height:7.5mm;padding:1.6mm 2.4mm;background:#f1f5f9;border-radius:1mm;font-size:3.7mm;font-weight:700;line-height:1.25}
.cx{display:block;height:9mm;border:.35mm solid #0f172a;border-radius:1mm}.cx.alto{height:14mm}.cx.grande{height:24mm}
.l2{display:grid;grid-template-columns:1fr 1fr;gap:5mm}.l3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:5mm}
.l2.a{grid-template-columns:1.6fr 1fr}
.tipo .ck{display:inline-flex;align-items:center;gap:2mm;margin-right:8mm;font-size:3.8mm;font-weight:700}
.tipo .ck i{display:inline-flex;width:5.6mm;height:5.6mm;border:.4mm solid #0f172a;border-radius:1mm;align-items:center;justify-content:center;font-style:normal;font-weight:900;font-size:4.4mm;line-height:1}
.pecas{width:100%;border-collapse:collapse;margin:0 0 2.6mm}
.pecas th{font-size:2.6mm;text-transform:uppercase;letter-spacing:.05em;text-align:left;border:.35mm solid #0f172a;background:#fff7ed;padding:1.2mm 2mm}
.pecas th.n,.pecas td:nth-child(2){width:26mm}
.pecas td{height:7.4mm;border:.35mm solid #0f172a}
.assin{display:grid;grid-template-columns:1fr 1fr;gap:12mm;margin-top:4mm}
.assin .traco{display:block;height:11mm;border-bottom:.4mm solid #0f172a}
.assin small{display:block;margin-top:1mm;font-size:2.7mm;text-transform:uppercase;letter-spacing:.05em;color:#475569;text-align:center}
.pe{position:absolute;left:13mm;right:13mm;bottom:7mm;text-align:center;font-size:2.5mm;color:#64748b}
@page{size:A4;margin:0}
@media print{body{background:#fff}.barra{display:none}.folha{margin:0;box-shadow:none;width:210mm;height:297mm;min-height:0;overflow:hidden}}
@media (max-width:820px){.folha{width:100%;min-height:0;padding:16px}.cab{grid-template-columns:1fr 1fr}.l2,.l3,.l2.a{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="barra">
    <strong>Folha da O.S. {{ $os->os_number }}</strong>
    <button type="button" onclick="window.print()">Imprimir</button>
    <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $os]) }}">Voltar para a O.S.</a>
    <span>Na janela de impressão, escolha A4 e escala 100%.</span>
</div>

<section class="folha">
    <i class="canto tl"></i><i class="canto tr"></i><i class="canto bl"></i><i class="canto br"></i>
    <header class="cab">
        <div class="marca"><span class="pts"><b></b><b></b><b></b></span>Oravel</div>
        <div class="emp"><small>Empresa</small><strong>{{ \App\Support\Tenancy::current()?->name ?? '' }}</strong></div>
        <div class="nro"><small>Ordem de serviço nº</small><strong>{{ $os->os_number }}</strong></div>
        <div class="qr">{!! $qr !!}</div>
    </header>

    <div class="sec imp">Dados da O.S. (já impressos pelo sistema)</div>
    <div class="l2 a">
        <div class="campo"><label>Cliente</label><span class="imp-v">{{ $cliente }}</span></div>
        <div class="campo"><label>Emitida em</label><span class="imp-v">{{ now()->format('d/m/Y H:i') }}</span></div>
    </div>
    <div class="l2 a">
        <div class="campo"><label>Equipamento</label><span class="imp-v">{{ $equipamento }}</span></div>
        <div class="campo"><label>Horímetro ou km anterior</label><span class="imp-v">{{ $horimetroAnterior !== null && $horimetroAnterior !== '' ? rtrim(rtrim(number_format((float) $horimetroAnterior, 2, ',', '.'), '0'), ',') : '—' }}</span></div>
    </div>
    <div class="campo tipo"><label>Tipo de serviço</label>
        @foreach (['Preventiva', 'Corretiva', 'Troca'] as $t)
            <span class="ck"><i>{{ $tipo === $t ? 'X' : '' }}</i> {{ $t }}</span>
        @endforeach
    </div>
    <div class="campo"><label>Problema relatado</label><span class="imp-v" style="min-height:13mm">{{ $problema !== '' ? $problema : '—' }}</span></div>
    <div class="l2">
        <div class="campo"><label>Agendada para</label><span class="imp-v">{{ $os->scheduled_at ? $os->scheduled_at->format('d/m/Y H:i') : '—' }}</span></div>
        <div class="campo"><label>Técnico responsável</label><span class="imp-v">{{ $tecnico ?: '—' }}</span></div>
    </div>

    <div class="sec mao">Preencha à mão, em letra de forma, dentro dos quadros</div>
    <div class="l2 a">
        <div class="campo"><label>Serviço executado</label><span class="cx grande"></span></div>
        <div>
            <div class="campo"><label>Horímetro ou km atual</label><span class="cx"></span></div>
            <div class="campo"><label>Horas trabalhadas</label><span class="cx"></span></div>
        </div>
    </div>
    <table class="pecas">
        <thead><tr><th>Peças e materiais usados</th><th class="n">Qtd.</th></tr></thead>
        <tbody>@for ($i = 0; $i < 6; $i++)<tr><td></td><td></td></tr>@endfor</tbody>
    </table>
    <div class="l2">
        <div class="campo"><label>Início (data e hora)</label><span class="cx"></span></div>
        <div class="campo"><label>Fim (data e hora)</label><span class="cx"></span></div>
    </div>
    <div class="campo"><label>Observações</label><span class="cx alto"></span></div>
    <div class="assin">
        <div><span class="traco"></span><small>Assinatura do técnico</small></div>
        <div><span class="traco"></span><small>Assinatura do cliente</small></div>
    </div>
    <footer class="pe">Depois de preenchida, fotografe a folha inteira e envie em Ordens de serviço › {{ $os->os_number }} › Mais ações › Atualizar com a folha preenchida (IA)</footer>
</section>
</body>
</html>
