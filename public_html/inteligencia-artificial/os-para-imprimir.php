<?php
/**
 * Ordem de serviço em branco para imprimir (A4), numerada, pensada para ser preenchida à mão e depois lida pela IA da Oravel.
 *
 * Parâmetros (GET), todos opcionais:
 *   copias  quantas folhas gerar (1 a 20)            padrão 1
 *   inicio  número da primeira folha (1 a 99999)     padrão 1
 *   empresa nome da locadora impresso no topo        padrão em branco, para escrever à mão
 *
 * Sem banco, sem sessão, sem gravar nada: só monta o HTML. Compatível com PHP 7.4 em diante.
 */
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

function inteiro($nome, $min, $max, $padrao)
{
    $v = isset($_GET[$nome]) ? filter_var($_GET[$nome], FILTER_VALIDATE_INT) : false;
    if ($v === false || $v === null) {
        return $padrao;
    }
    return max($min, min($max, (int) $v));
}

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$copias = inteiro('copias', 1, 20, 1);
$inicio = inteiro('inicio', 1, 99999, 1);
$empresa = isset($_GET['empresa']) ? trim(preg_replace('/\s+/u', ' ', (string) $_GET['empresa'])) : '';
if (function_exists('mb_substr')) {
    $empresa = mb_substr($empresa, 0, 60, 'UTF-8');
} else {
    $empresa = substr($empresa, 0, 60);
}

/** Uma fileira de quadradinhos para escrever letra por letra (datas, números). */
function quadros($n)
{
    return '<span class="q">' . str_repeat('<i></i>', $n) . '</span>';
}

function folha($numero, $empresa)
{
    $num = str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    ob_start(); ?>
    <section class="folha">
        <i class="canto tl"></i><i class="canto tr"></i><i class="canto bl"></i><i class="canto br"></i>
        <header class="cab">
            <div class="marca"><span class="pts"><b></b><b></b><b></b></span>Oravel</div>
            <div class="emp"><small>Empresa</small><?php if ($empresa !== '') { ?><strong><?= h($empresa) ?></strong><?php } else { ?><span class="linha"></span><?php } ?></div>
            <div class="nro"><small>Ordem de serviço nº</small><strong><?= h($num) ?></strong></div>
        </header>
        <p class="dica">Escreva em <b>LETRA DE FORMA</b>, dentro dos quadros, com caneta azul ou preta. Depois, fotografe a folha inteira e envie pelo sistema.</p>

        <div class="linha2">
            <div class="campo grande"><label>Cliente</label><span class="cx"></span></div>
            <div class="campo"><label>Data</label><?= quadros(2) ?><em>/</em><?= quadros(2) ?><em>/</em><?= quadros(4) ?></div>
        </div>
        <div class="linha2">
            <div class="campo grande"><label>Equipamento (código, patrimônio ou placa)</label><span class="cx"></span></div>
            <div class="campo"><label>Horímetro ou km</label><span class="cx"></span></div>
        </div>
        <div class="campo tipo"><label>Tipo de serviço</label>
            <span class="ck"><i></i> Preventiva</span><span class="ck"><i></i> Corretiva</span><span class="ck"><i></i> Troca</span>
        </div>
        <div class="campo"><label>Problema relatado</label><span class="cx alto"></span></div>
        <div class="campo"><label>Serviço executado</label><span class="cx mais-alto"></span></div>

        <table class="pecas">
            <thead><tr><th>Peças e materiais usados</th><th class="n">Qtd.</th><th class="n">Valor unit. (R$)</th></tr></thead>
            <tbody><?php for ($i = 0; $i < 6; $i++) { ?><tr><td></td><td></td><td></td></tr><?php } ?></tbody>
        </table>

        <div class="linha2 tres">
            <div class="campo"><label>Início (data e hora)</label><span class="cx"></span></div>
            <div class="campo"><label>Fim (data e hora)</label><span class="cx"></span></div>
            <div class="campo"><label>Mão de obra (R$)</label><span class="cx"></span></div>
        </div>
        <div class="campo"><label>Técnico responsável</label><span class="cx"></span></div>
        <div class="campo"><label>Observações</label><span class="cx alto"></span></div>

        <div class="assin">
            <div><span class="traco"></span><small>Assinatura do técnico</small></div>
            <div><span class="traco"></span><small>Assinatura do cliente</small></div>
        </div>
        <footer class="pe">oravel.com.br/inteligencia-artificial · Foto da folha inteira em Ordens de serviço &rsaquo; Nova &rsaquo; Preencher com IA</footer>
    </section>
    <?php
    return ob_get_clean();
}
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Ordem de serviço para imprimir | Oravel</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#e9edf3;color:#0f172a;font-family:Arial,Helvetica,sans-serif}
.barra{position:sticky;top:0;z-index:10;background:#08142c;color:#fff;padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px 16px;align-items:center}
.barra strong{font-size:15px}
.barra a,.barra button{font:700 14px Arial,sans-serif;border-radius:8px;border:1.5px solid rgba(255,255,255,.45);background:transparent;color:#fff;padding:9px 14px;text-decoration:none;cursor:pointer}
.barra button{background:#f97316;border-color:#f97316;color:#0f172a}
.barra span{font-size:13px;color:#cbd5e1}
.folha{position:relative;width:210mm;min-height:297mm;margin:14px auto;background:#fff;padding:12mm 13mm 10mm;box-shadow:0 8px 30px rgba(15,23,42,.18);page-break-after:always;break-after:page}
.canto{position:absolute;width:7mm;height:7mm;border:0 solid #0f172a}
.tl{left:5mm;top:5mm;border-left-width:.5mm;border-top-width:.5mm}.tr{right:5mm;top:5mm;border-right-width:.5mm;border-top-width:.5mm}
.bl{left:5mm;bottom:5mm;border-left-width:.5mm;border-bottom-width:.5mm}.br{right:5mm;bottom:5mm;border-right-width:.5mm;border-bottom-width:.5mm}
.cab{display:grid;grid-template-columns:34mm 1fr 52mm;gap:6mm;align-items:end;border-bottom:.6mm solid #0f172a;padding-bottom:3mm}
.marca{font-weight:900;font-size:6.2mm;letter-spacing:-.02em;display:flex;align-items:center;gap:2mm}
.pts{display:inline-flex;gap:1mm}.pts b{width:2.2mm;height:2.2mm;border-radius:50%;display:block}.pts b:nth-child(1){background:#ef4444}.pts b:nth-child(2){background:#f59e0b}.pts b:nth-child(3){background:#22c55e}
.emp small,.nro small{display:block;font-size:2.6mm;text-transform:uppercase;letter-spacing:.06em;color:#475569;font-weight:700}
.emp strong{font-size:4.2mm}.emp .linha{display:block;height:7mm;border-bottom:.3mm solid #475569}
.nro{text-align:right}.nro strong{font-size:8mm;letter-spacing:.02em;font-variant-numeric:tabular-nums}
.dica{margin:3mm 0 4mm;font-size:3.1mm;color:#334155;background:#f1f5f9;border-left:1.2mm solid #f97316;padding:2mm 3mm}
.campo{margin-bottom:3.2mm;min-width:0}
.campo label{display:block;font-size:2.7mm;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#334155;margin-bottom:1mm}
.cx{display:block;height:9.5mm;border:.35mm solid #0f172a;border-radius:1mm}
.cx.alto{height:16mm}.cx.mais-alto{height:22mm}
.linha2{display:grid;grid-template-columns:1fr auto;gap:5mm}.linha2.tres{grid-template-columns:1fr 1fr 1fr}
.campo.grande{min-width:0}
.q{display:inline-flex;gap:1mm;vertical-align:middle}.q i{display:block;width:6.6mm;height:9.5mm;border:.35mm solid #0f172a;border-radius:1mm}
.campo em{font-style:normal;font-weight:700;margin:0 1mm;vertical-align:middle}
.tipo .ck{display:inline-flex;align-items:center;gap:2mm;margin-right:9mm;font-size:4mm;font-weight:700}
.tipo .ck i{display:inline-block;width:6mm;height:6mm;border:.4mm solid #0f172a;border-radius:1mm}
.pecas{width:100%;border-collapse:collapse;margin:1mm 0 4mm}
.pecas th{font-size:2.7mm;text-transform:uppercase;letter-spacing:.05em;text-align:left;border:.35mm solid #0f172a;background:#f1f5f9;padding:1.4mm 2mm}
.pecas th.n,.pecas td:nth-child(n+2){width:30mm}
.pecas td{height:8mm;border:.35mm solid #0f172a}
.assin{display:grid;grid-template-columns:1fr 1fr;gap:12mm;margin-top:5mm}
.assin .traco{display:block;height:12mm;border-bottom:.4mm solid #0f172a}
.assin small{display:block;margin-top:1mm;font-size:2.8mm;text-transform:uppercase;letter-spacing:.05em;color:#475569;text-align:center}
.pe{position:absolute;left:13mm;right:13mm;bottom:8mm;text-align:center;font-size:2.6mm;color:#64748b}
@page{size:A4;margin:0}
@media print{
  body{background:#fff}
  .barra{display:none}
  .folha{margin:0;box-shadow:none;width:210mm;height:297mm;min-height:0;overflow:hidden}
}
@media (max-width:820px){ .folha{width:100%;min-height:0;padding:16px} .cab{grid-template-columns:1fr;gap:8px} .nro{text-align:left} }
</style>
</head>
<body>
<div class="barra">
    <strong><?= (int) $copias ?> folha<?= $copias > 1 ? 's' : '' ?> · nº <?= h(str_pad((string) $inicio, 4, '0', STR_PAD_LEFT)) ?><?= $copias > 1 ? ' a ' . h(str_pad((string) ($inicio + $copias - 1), 4, '0', STR_PAD_LEFT)) : '' ?></strong>
    <button type="button" onclick="window.print()">Imprimir</button>
    <a href="/inteligencia-artificial/#os-manuscrita">Voltar para a página de IA</a>
    <span>Dica: na janela de impressão, escolha tamanho A4 e escala 100%.</span>
</div>
<?php for ($i = 0; $i < $copias; $i++) { echo folha($inicio + $i, $empresa); } ?>
</body>
</html>
