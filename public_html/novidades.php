<?php
// Oravel — Novidades (changelog de produto em linguagem comercial)

$site_name    = "Oravel";
$slogan       = "Gestão de Operações em Campo";
$app_url      = "https://app.oravel.com.br";
$contact_mail = "contato@oravel.com.br";

$page_title       = "Novidades | $site_name";
$page_description = "Do pátio ao financeiro, sem digitar duas vezes. Veja o que há de novo no Oravel: orçamentos com aprovação online, ciclo completo de compras, mapa de equipamentos e mais.";
$nav_active       = "novidades";

$secondary = [
  ["tag" => "Logística",  "kind" => "route",   "title" => "Todo equipamento, rastreado do início ao fim", "desc" => "Motoristas e movimentação de equipamento sob controle. A chegada no pátio já nasce documentada com laudo de recebimento — a prova de qualquer avaria fica registrada no exato momento em que ela acontece."],
  ["tag" => "Suprimentos","kind" => "compras", "title" => "Requisição vira compra sem planilha paralela", "desc" => "Requisição, cotação, ordem de compra e recebimento — tudo dentro do sistema, com aprovação em cada etapa. Ninguém mais controla por fora o que a oficina precisa comprar."],
  ["tag" => "Ativos",     "kind" => "map",     "title" => "Saiba onde cada ativo está, antes que ele quebre", "desc" => "Mapa de equipamentos, alertas automáticos de manutenção preventiva e gestão organizada por número de patrimônio. Exporte os dados quando precisar prestar contas."],
  ["tag" => "Comercial",  "kind" => "funnel",  "title" => "O funil de vendas mora dentro do Oravel", "desc" => "Gerencie prospects e acompanhe o funil comercial no mesmo lugar onde já roda a operação. O mapa comercial junta clientes ativos e leads por região."],
];

require __DIR__ . '/inc/journey.php';
include __DIR__ . '/inc/head.php';
include __DIR__ . '/inc/nav.php';
?>

<header class="page-header">
  <div class="page-header-inner">
    <span class="pill-badge">Atualização da Plataforma</span>
    <h1>Orçamento que se aprova<br>sozinho.</h1>
    <p>Orçamento, avaria e cobrança agora conversam entre si. O que o técnico registra no pátio vira orçamento; o que o cliente aprova vira conta a receber — sozinho.</p>
  </div>
</header>

<section class="zone-light" style="background:var(--bg);color:var(--fg)">

  <div class="wrap-wide">
    <?php
      // Trilha única cobrindo todo o changelog -- antes só o módulo de
      // orçamento tinha texto corrido sem mockup, e o resto (rastreamento,
      // compras, mapa, funil, 2FA) era coluna única sem visual nenhum.
      // +1 extra no fim: o cabeçalho "O que mais mudou" no meio da trilha não
      // tem nó próprio, mas ocupa altura real -- sem essa folga a curva
      // esticava só naquele trecho e desalinhava (visto no screenshot de
      // teste). É só pra matemática da curva, não conta pro $gi (esquerda/
      // direita), que só incrementa em cena de verdade.
      $journey_total = 1 + count($secondary) + 1 + 1; // orçamento + secundárias + 2FA + folga do cabeçalho
      $gi = 0;
    ?>
    <div class="journey">
      <?= journey_svg($journey_total) ?>

      <div class="path-block<?= $gi % 2 === 1 ? ' reverse' : '' ?> anim">
        <div class="path-node"></div>
        <div class="feat-text">
          <span class="pill-badge">Novo Módulo</span>
          <h3>Orçamento que se aprova sozinho</h3>
          <p>Chega de planilha solta e PDF montado na mão. Monte o orçamento com peças do almoxarifado e serviços direto na Ordem de Serviço ou na avaria, gere o PDF na hora e envie por e-mail com um link de aprovação. O cliente aprova ou reprova pela internet — sem imprimir, sem assinar papel, sem esperar retornar pro pátio.</p>
          <div class="module-list">
            <div class="module-item"><span class="module-dot"></span>Orçamento a terceiro guarda o laudo técnico prévio junto, pronto pra consulta</div>
            <div class="module-item"><span class="module-dot"></span>A causa da avaria — desgaste natural, mau uso ou dano do cliente — já direciona se vira cobrança</div>
            <div class="module-item"><span class="module-dot"></span>Aprovação do cliente vira conta a receber sozinha, com valor e vencimento prontos</div>
          </div>
        </div>
        <div class="feat-visual">
          <div class="app-window">
            <div class="aw-top"><div class="aw-dot r"></div><div class="aw-dot y"></div><div class="aw-dot g"></div><span class="aw-title">Oravel — Orçamento</span></div>
            <div class="aw-body">
              <span class="pill-status">Aprovado pelo cliente</span>
              <div class="info-row"><span class="info-label">Cliente</span><span class="info-val">Construtora Vega</span></div>
              <div class="info-row"><span class="info-label">Valor</span><span class="info-val">R$ 3.240,00</span></div>
              <div class="info-row"><span class="info-label">Vencimento</span><span class="info-val">10/08/2026</span></div>
              <div class="fm-flow" style="margin-top:14px">
                <?php
                  $flow_steps = ["Avaria constatada", "Causa classificada", "Orçamento gerado", "Cliente aprova"];
                  $flow_widths = [100, 82, 60, 38];
                  foreach ($flow_steps as $fi => $fs):
                ?>
                <div class="fm-band<?= $fi === count($flow_steps) - 1 ? ' good' : '' ?>" style="width:<?= $flow_widths[$fi] ?>%"><?= $fs ?></div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php $gi++; ?>

      <div class="wrap-narrow-header">
        <span class="pill-badge">O que mais mudou</span>
        <h2 class="section-title">Mais controle,<br>menos retrabalho</h2>
      </div>

      <?php foreach ($secondary as $s): ?>
      <div class="path-block<?= $gi % 2 === 1 ? ' reverse' : '' ?> anim">
        <div class="path-node"></div>
        <div class="feat-text">
          <span class="pill-badge"><?= $s['tag'] ?></span>
          <h3><?= $s['title'] ?></h3>
          <p><?= $s['desc'] ?></p>
        </div>
        <div class="feat-visual">
          <div class="app-window">
            <div class="aw-top"><div class="aw-dot r"></div><div class="aw-dot y"></div><div class="aw-dot g"></div><span class="aw-title">Oravel — <?= $s['tag'] ?></span></div>
            <div class="aw-body">

              <?php if ($s['kind'] === 'route'): ?>
                <div class="mock-map">
                  <div class="mock-map-pin live" style="top:60%;left:20%"></div>
                  <div class="mock-map-pin" style="top:30%;left:70%"></div>
                </div>
                <div class="info-row"><span class="info-label">Laudo de recebimento</span><span class="info-val">Sem avarias</span></div>

              <?php elseif ($s['kind'] === 'compras'): ?>
                <div class="fm-flow">
                  <div class="fm-band" style="width:100%">Requisição</div>
                  <div class="fm-band" style="width:76%">Cotação</div>
                  <div class="fm-band" style="width:52%">Ordem de Compra</div>
                  <div class="fm-band good" style="width:30%">Recebimento</div>
                </div>

              <?php elseif ($s['kind'] === 'map'): ?>
                <div class="mock-map">
                  <div class="mock-map-pin" style="top:24%;left:24%"></div>
                  <div class="mock-map-pin live" style="top:56%;left:60%"></div>
                  <div class="mock-map-pin" style="top:72%;left:30%"></div>
                </div>
                <div class="mock-row" style="margin-top:10px">
                  <div class="mock-track"><div class="mock-fill" style="width:87%"></div></div>
                  <span class="mock-val">87%</span>
                </div>
                <p style="font-size:11px;color:var(--muted);margin-top:6px">Preventiva em dia na frota</p>

              <?php elseif ($s['kind'] === 'funnel'): ?>
                <div class="fm-flow">
                  <div class="fm-band" style="width:100%">Novo</div>
                  <div class="fm-band" style="width:78%">Contato</div>
                  <div class="fm-band" style="width:54%">Qualificado</div>
                  <div class="fm-band good" style="width:30%">Convertido</div>
                </div>

              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
      <?php $gi++; endforeach; ?>

      <div class="path-block<?= $gi % 2 === 1 ? ' reverse' : '' ?> anim">
        <div class="path-node"></div>
        <div class="feat-text">
          <span class="pill-badge">Segurança</span>
          <h3>Autenticação em duas etapas</h3>
          <p>Uma camada extra de proteção pras contas de administração da plataforma — segurança que não depende só de senha.</p>
        </div>
        <div class="feat-visual">
          <div class="app-window">
            <div class="aw-top"><div class="aw-dot r"></div><div class="aw-dot y"></div><div class="aw-dot g"></div><span class="aw-title">Oravel — Verificação</span></div>
            <div class="aw-body">
              <span class="pill-status">Verificado</span>
              <div class="info-row"><span class="info-label">Método</span><span class="info-val">App autenticador</span></div>
              <div style="display:flex;gap:6px;margin-top:14px">
                <?php for ($d = 0; $d < 6; $d++): ?>
                <div style="flex:1;aspect-ratio:1;border-radius:6px;background:#f7f5f1;border:1.5px solid #e7e3db;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:15px;color:#181510">•</div>
                <?php endfor; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php $gi++; ?>
    </div>
  </div>

  <div class="divider"><div class="div-line"></div><div class="div-dot"></div><div class="div-line"></div></div>

  <div class="wrap-wide" style="max-width:800px;text-align:center">
    <p style="font-family:var(--font-display);font-size:clamp(20px,3vw,28px);line-height:1.4;margin:0 auto;max-width:600px">
      Cada informação é registrada <span style="color:var(--orange)">uma vez só</span> — e flui sozinha pro próximo setor.
    </p>
  </div>

</section>

<div class="cta-section" id="cta">
  <div class="cta-inner">
    <span class="pill-badge">Vamos conversar</span>
    <h2 class="section-title">Quer ver o orçamento<br>rodando na prática?</h2>
    <p>Agendamos uma demonstração ao vivo com os dados da sua operação, sem compromisso.</p>
    <div class="cta-btns">
      <a href="/contato.php" class="btn-cta-dark">Agendar Demonstração →</a>
      <a href="<?= $app_url ?>" target="_blank" rel="noopener" class="btn-cta-out">Acessar o Sistema</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
