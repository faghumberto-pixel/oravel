<?php
// Central de Ajuda — "Como funciona o apontamento de horímetro"
// Usa o mesmo sistema de componentes de faq.php/novidades.php
// (/assets/style.css, inc/nav.php, inc/footer.php).

$site_name    = "Oravel";
$slogan       = "Gestão de Operações em Campo";
$contact_mail = "contato@oravel.com.br";

$page_title       = "Como funciona o apontamento de horímetro | Central de Ajuda Oravel";
$page_description = "Entenda como a Oravel registra o horímetro dos seus equipamentos: as várias formas de apontar, as conferências automáticas e como isso avisa quando a manutenção preventiva vence.";
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
    <h1>O horímetro não é só<br>um número no painel.</h1>
    <p>É ele que avisa quando um equipamento precisa de manutenção — e no Oravel, não importa quem
    registrou a leitura, ela sempre passa pelas mesmas conferências.</p>
  </div>
</header>

<section class="zone-light" style="background:var(--bg);color:var(--fg)">

  <div class="wrap-wide" style="padding-top:0;padding-bottom:48px">
    <p style="font-size:15.5px;color:var(--muted);line-height:1.8;max-width:720px">
      No Oravel, todo apontamento de horímetro — feito por um técnico no pátio, pelo próprio cliente
      locatário no celular, ou automaticamente quando uma Ordem de Serviço é fechada — fica guardado no
      mesmo histórico e passa pelas mesmas conferências. É isso que permite que a manutenção preventiva
      avise automaticamente, sem ninguém precisar ficar de olho em planilha.
    </p>
  </div>

  <div class="divider"><div class="div-line"></div><div class="div-dot"></div><div class="div-line"></div></div>

  <div class="wrap-wide" style="padding-top:0">
    <span class="pill-badge">Onde registrar</span>
    <h2 class="section-title">Várias formas de registrar,<br>um único histórico</h2>
    <p class="section-sub" style="margin-bottom:32px">Cada pessoa registra do jeito que faz sentido pra
    sua rotina — mas todo apontamento termina no mesmo lugar, sempre com a informação de quem
    registrou.</p>

    <div class="gw-list">
      <div class="gw-item">
        <div class="gw-num">01</div>
        <div>
          <h4>Apontamento manual</h4>
          <p>O time comercial ou de manutenção escolhe o equipamento, vê a última leitura registrada
          antes de digitar a nova, e pode anexar uma foto do painel.</p>
        </div>
      </div>
      <div class="gw-item">
        <div class="gw-num">02</div>
        <div>
          <h4>Direto pela ficha do equipamento</h4>
          <p>O técnico que já está olhando o equipamento no pátio registra a leitura na hora, pelo
          celular, sem precisar voltar pro computador.</p>
        </div>
      </div>
      <div class="gw-item">
        <div class="gw-num">03</div>
        <div>
          <h4>Pelo Portal do Cliente</h4>
          <p>O cliente que está com o equipamento alugado consegue, ele mesmo, atualizar o horímetro
          pelo próprio acesso — só enquanto o contrato estiver ativo.</p>
        </div>
      </div>
      <div class="gw-item">
        <div class="gw-num">04</div>
        <div>
          <h4>Por um link, sem precisar de senha</h4>
          <p>Pra quem opera o equipamento na obra e não tem acesso ao sistema, um link simples abre
          direto a tela de apontamento daquele equipamento.</p>
        </div>
      </div>
      <div class="gw-item">
        <div class="gw-num">05</div>
        <div>
          <h4>"Registrar Horímetro", no App do Colaborador</h4>
          <p>Um atalho pensado pra funcionar mesmo sem sinal de internet — explicamos como funciona mais
          abaixo.</p>
        </div>
      </div>
      <div class="gw-item">
        <div class="gw-num">06</div>
        <div>
          <h4>No checklist de embarque do equipamento</h4>
          <p>Quando o equipamento sai ou volta pro pátio, o checklist já pede o horímetro — essa
          informação vira automaticamente uma leitura registrada.</p>
        </div>
      </div>
      <div class="gw-item">
        <div class="gw-num">07</div>
        <div>
          <h4>Ao fechar uma Ordem de Serviço</h4>
          <p>Se o horímetro foi anotado na manutenção, essa leitura entra automaticamente no histórico
          do equipamento.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="divider"><div class="div-line"></div><div class="div-dot"></div><div class="div-line"></div></div>

  <div class="wrap-wide" style="padding-top:0">
    <span class="pill-badge">Sem erro no histórico</span>
    <h2 class="section-title">Duas conferências automáticas,<br>em toda leitura</h2>
    <p class="section-sub" style="margin-bottom:32px">Não importa por onde a leitura chegou: antes de
    entrar no histórico, ela passa pelas mesmas duas conferências, sempre.</p>

    <div class="module-list" style="max-width:780px">
      <div class="module-item"><span class="module-dot"></span>O horímetro nunca "volta no tempo" sem
      confirmação — pensado pra quando o painel do equipamento é trocado e o contador zera.</div>
      <div class="module-item"><span class="module-dot"></span>Um salto grande demais entre uma leitura e
      a outra também pede confirmação — pensado pra pegar um erro de digitação antes que ele atrapalhe o
      cálculo da próxima manutenção.</div>
      <div class="module-item"><span class="module-dot"></span>Mesmo quando uma troca de painel é
      confirmada, o número usado pra calcular a manutenção nunca diminui — o cronograma nunca fica
      bagunçado por causa da troca de uma peça.</div>
    </div>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-num">2</div><div class="stat-lbl">Conferências automáticas</div><div class="stat-desc">Em toda leitura, não importa de onde veio</div></div>
      <div class="stat-card"><div class="stat-num">7</div><div class="stat-lbl">Formas de registrar</div><div class="stat-desc">Todas com a mesma regra de confiança</div></div>
      <div class="stat-card"><div class="stat-num">500h</div><div class="stat-lbl">Salto que já avisa</div><div class="stat-desc">Evita erro de digitação no histórico</div></div>
      <div class="stat-card"><div class="stat-num">100%</div><div class="stat-lbl">Fica no histórico</div><div class="stat-desc">Toda leitura registrada, sempre</div></div>
    </div>
  </div>

  <div class="divider"><div class="div-line"></div><div class="div-dot"></div><div class="div-line"></div></div>

  <div class="wrap-wide" style="padding-top:0">
    <span class="pill-badge">Pra que serve, de verdade</span>
    <h2 class="section-title">O horímetro avisa quando<br>a manutenção vence</h2>
    <p class="section-sub" style="margin-bottom:32px">Uma locadora de equipamentos não pode fazer
    manutenção só por calendário. Por isso o plano de manutenção preventiva olha pra três coisas ao mesmo
    tempo, e avisa assim que a primeira delas vencer.</p>

    <div class="module-list" style="max-width:780px">
      <div class="module-item"><span class="module-dot"></span><strong>Horas de uso</strong> — compara
      quantas horas o equipamento já rodou desde a última manutenção. Exemplo: troca de óleo a cada
      250h.</div>
      <div class="module-item"><span class="module-dot"></span><strong>Tempo corrido</strong> — compara
      quanto tempo já passou, mesmo que o equipamento não tenha rodado muito. Exemplo: inspeção
      obrigatória a cada 6 meses.</div>
      <div class="module-item"><span class="module-dot"></span><strong>Ciclos de bateria</strong> — mesma
      ideia das horas, pra equipamentos elétricos. Exemplo: manutenção de bateria a cada 300 cargas.</div>
    </div>
  </div>

  <div class="divider"><div class="div-line"></div><div class="div-dot"></div><div class="div-line"></div></div>

  <div class="wrap-wide" style="padding-top:0">
    <span class="pill-badge">Onde a internet falha</span>
    <h2 class="section-title">Funciona mesmo sem sinal,<br>de verdade</h2>
    <p class="section-sub" style="margin-bottom:32px">Boa parte dos apontamentos acontece dentro de uma
    obra, num canteiro isolado ou num pátio sem cobertura. Por isso o atalho "Registrar Horímetro", no
    App do Colaborador, foi pensado desde o início pra funcionar sem internet.</p>

    <div class="module-list" style="max-width:780px">
      <div class="module-item"><span class="module-dot"></span>A lista de equipamentos, com a última
      leitura de cada um, já fica salva no celular do técnico assim que ele tem conexão.</div>
      <div class="module-item"><span class="module-dot"></span>O técnico registra a leitura, tira a foto
      e salva — tudo fica guardado no próprio celular, sem precisar de internet nesse momento.</div>
      <div class="module-item"><span class="module-dot"></span>Quando a conexão volta, tudo é enviado de
      uma vez — sem duplicar nada, mesmo que o envio precise ser tentado de novo.</div>
      <div class="module-item"><span class="module-dot"></span>Se uma leitura precisar de confirmação, só
      ela fica pendente — todas as outras são enviadas normalmente.</div>
    </div>
  </div>

</section>

<div class="cta-section" id="cta">
  <div class="cta-inner">
    <span class="pill-badge">Quer ver na prática?</span>
    <h2 class="section-title">Fale com<br>o nosso time</h2>
    <p>A gente mostra como o controle de horímetro e a manutenção preventiva funcionam na sua
    operação.</p>
    <div class="cta-btns">
      <a href="/contato.php" class="btn-cta-dark">Falar com o Time →</a>
      <a href="/central-de-conhecimento/" class="btn-cta-out">Ver outros artigos</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../inc/footer.php'; ?>
