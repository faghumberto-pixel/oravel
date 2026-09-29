<?php
// Central de Ajuda — página-índice. Lista os artigos publicados;
// hoje só o de Horímetro, mas a estrutura já comporta novos itens
// no mesmo padrão (adicionar ao array $artigos).

$site_name    = "Oravel";
$slogan       = "Gestão de Operações em Campo";
$contact_mail = "contato@oravel.com.br";

$page_title       = "Central de Ajuda | Oravel";
$page_description = "Artigos e guias sobre como cada parte da Oravel funciona no dia a dia da sua operação.";

$artigos = [
  [
    "url"   => "/central-de-conhecimento/horimetro.php",
    "titulo" => "Como funciona o apontamento de horímetro",
    "resumo" => "As várias formas de registrar, as conferências automáticas que evitam erro no histórico, e como isso avisa quando a manutenção preventiva vence.",
  ],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?></title>
<meta name="description" content="<?= $page_description ?>">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23101010'/%3E%3Ctext x='32' y='45' font-family='Arial, Helvetica, sans-serif' font-size='30' font-weight='800' text-anchor='middle'%3E%3Ctspan fill='%23ffffff'%3EO%3C/tspan%3E%3Ctspan fill='%23E8541A'%3ER%3C/tspan%3E%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css?v=1789306343">
</head>
<body>
<?php include __DIR__ . '/../inc/nav.php'; ?>

<header class="page-header">
  <div class="page-header-inner">
    <span class="pill-badge">Central de Ajuda</span>
    <h1>Guias pra tirar o<br>máximo da Oravel</h1>
    <p>Artigos em linguagem simples sobre como cada parte da Oravel funciona no dia a dia da sua
    operação.</p>
  </div>
</header>

<section class="zone-light" style="background:var(--bg);color:var(--fg)">
  <div class="wrap-wide" style="padding-top:0">
    <div class="gw-list">
      <?php foreach ($artigos as $a): ?>
      <a href="<?= $a['url'] ?>" class="gw-item" style="text-decoration:none">
        <div class="gw-num">→</div>
        <div>
          <h4><?= $a['titulo'] ?></h4>
          <p><?= $a['resumo'] ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/../inc/footer.php'; ?>
