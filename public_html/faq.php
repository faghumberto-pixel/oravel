<?php
// Oravel — Perguntas Frequentes
// Página institucional pública, linkada no menu principal e usada como
// sitelink das campanhas de Ads. Reescrita 2026-08-21 (pedido do usuário):
// a primeira versão só tinha perguntas de migração/planilha (herdadas das
// landing pages segmentadas de Ads), dando a impressão de que a Oravel só
// serve pra quem já tem sistema. Agora cobre 3 blocos: o que o sistema
// resolve (incluindo os módulos reais, confirmados no código antes de
// publicar), preço/dificuldade de implantar, e só por último as perguntas
// específicas de quem já tem sistema ou usa planilha.
//
// Módulos citados abaixo foram checados direto no código (app/Models,
// app/Filament/Resources, AdminPanelProvider) antes de publicar --
// Financeiro (contas a pagar/receber) e Departamento Pessoal (cadastro de
// colaboradores + ponto eletrônico) são reais. Fiscal e Contábil formal
// (plano de contas, lançamento contábil) NÃO existem hoje -- não afirmar
// isso aqui. Departamento Pessoal não inclui folha de pagamento nem férias
// -- não prometer isso também.

$site_name    = "Oravel";
$slogan       = "Gestão de Operações em Campo";
$app_url      = "https://app.oravel.com.br";
$contact_mail = "contato@oravel.com.br";

$page_title       = "Perguntas Frequentes | $site_name";
$page_description = "Tire suas dúvidas sobre o que a Oravel resolve, preço, implantação, migração de sistema e suporte.";

$faq_grupos = [
  [
    "titulo" => "Sobre a Oravel",
    "itens"  => [
      ["q" => "Pra quem é a Oravel?", "a" => "Pra empresas que operam com equipamentos, equipes e serviços em campo: locação de equipamentos, manutenção industrial, montagem, logística e transportes, gestão de serviços e distribuição. Não importa se você já usa um sistema, controla por planilha, ou ainda não tem nenhum controle formal."],
      ["q" => "Que áreas da minha operação a Oravel controla?", "a" => "Ativos e manutenção (preventiva e corretiva), logística de saída e retorno, comercial e contratos, suprimentos e estoque, equipe e departamento pessoal (cadastro de colaboradores e ponto eletrônico), financeiro (contas a pagar e a receber) e relatórios."],
      ["q" => "A Oravel emite nota fiscal ou faz contabilidade?", "a" => "Não. A Oravel organiza o financeiro operacional da sua operação — contas a pagar, contas a receber, centro de custo — mas não substitui seu sistema fiscal ou sua contabilidade."],
      ["q" => "Dá pra usar pelo celular, em campo?", "a" => "Sim. A interface foi pensada pra quem está no pátio ou na obra, não só no escritório — checklist, foto e ponto eletrônico funcionam direto do celular, inclusive offline."],
    ],
  ],
  [
    "titulo" => "Preço e implantação",
    "itens"  => [
      ["q" => "A Oravel é cara?", "a" => "Você começa só com o que precisa — equipamentos e contratos — e ativa mais módulos conforme sua operação cresce. Não existe pacote único obrigatório caro demais pra quem está começando."],
      ["q" => "Quanto custa a Oravel?", "a" => "Depende do tamanho da sua operação e dos módulos que fizerem sentido pra você. Fale com nosso time e a gente monta uma proposta sob medida."],
      ["q" => "É difícil de implantar?", "a" => "Não. O cadastro inicial é simples e sua equipe já opera com dados reais da sua operação logo na primeira semana — sem curva de aprendizado de semanas."],
      ["q" => "Minha equipe não é boa com tecnologia, vai dar certo?", "a" => "A interface foi pensada pra quem está em campo, com o celular na mão — não é preciso ser \"da área de TI\" pra usar no dia a dia."],
      ["q" => "Preciso pagar por algo que talvez eu não use tudo?", "a" => "Não. Você começa com o básico e ativa o resto — preventiva, QR Code, relatórios — no seu ritmo, conforme sua operação cresce."],
      ["q" => "Tem contrato de fidelidade?", "a" => "Não. Você não fica preso a um contrato de permanência mínima — pode cancelar quando quiser."],
      ["q" => "Como funciona o suporte depois que eu começo a usar?", "a" => "Suporte dedicado em português nas primeiras semanas de uso, pra sua equipe não travar na virada. Depois disso, atendimento contínuo por e-mail e chat."],
    ],
  ],
  [
    "titulo" => "Trocando de sistema ou saindo da planilha",
    "itens"  => [
      ["q" => "Já uso outro sistema. Vou perder meus dados na migração?", "a" => "Não. A migração assistida traz contratos, clientes e equipamentos junto — nosso time cuida da importação com você acompanhando cada etapa."],
      ["q" => "Quanto tempo leva pra migrar de outro sistema?", "a" => "Varia com o tamanho da operação, mas a implantação roda em paralelo ao sistema atual — você só desliga o antigo quando tiver segurança de que o Oravel está redondo."],
      ["q" => "Hoje controlo tudo em planilha. Vale a pena trocar?", "a" => "Se você já perdeu contrato por esquecer renovação, ou teve equipamento parado sem saber a causa, provavelmente sim. Comece simples — cadastro de equipamentos e contratos já resolve boa parte disso."],
    ],
  ],
];
?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-L79HHRE3ZC"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-L79HHRE3ZC');
</script>
<!-- Microsoft Clarity -->
<script type="text/javascript">
    (function(c,l,a,r,i,t,y){
        c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    })(window, document, "clarity", "script", "y52dp76ece");
</script>
<?php include __DIR__ . '/inc/head.php'; ?>
<?php include __DIR__ . '/inc/nav.php'; ?>

<header class="page-header">
  <div class="page-header-inner">
    <span class="pill-badge">Perguntas Frequentes</span>
    <h1>Ficou com dúvidas<br>sobre a Oravel?</h1>
    <p>O que a Oravel resolve, quanto custa, como implantar — e, se for o seu caso, como migrar de outro sistema ou sair da planilha.</p>
  </div>
</header>

<section class="zone-light" style="background:var(--bg);color:var(--fg)">
  <?php foreach ($faq_grupos as $gi => $grupo): ?>
  <div class="wrap-wide" style="<?= $gi === 0 ? 'padding-top:0' : 'padding-top:0;padding-bottom:0' ?>" <?= $gi === 0 ? 'id="faq"' : '' ?>>
    <span class="pill-badge"><?= $grupo['titulo'] ?></span>
    <div class="faq-grid">
      <?php
      $mid = ceil(count($grupo['itens']) / 2);
      $cols = [array_slice($grupo['itens'], 0, $mid), array_slice($grupo['itens'], $mid)];
      foreach ($cols as $col): ?>
      <div>
        <?php foreach ($col as $f): ?>
        <div class="faq-item">
          <div class="faq-q"><?= $f['q'] ?></div>
          <div class="faq-a"><?= $f['a'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($gi < count($faq_grupos) - 1): ?>
  <div class="divider"><div class="div-line"></div><div class="div-dot"></div><div class="div-line"></div></div>
  <?php endif; ?>
  <?php endforeach; ?>
</section>

<div class="cta-section" id="cta">
  <div class="cta-inner">
    <span class="pill-badge">Ainda com dúvidas?</span>
    <h2 class="section-title">Fale direto com<br>o nosso time</h2>
    <p>A gente responde rápido e mostra, na prática, como o Oravel se encaixa na sua operação.</p>
    <div class="cta-btns">
      <a href="/contato.php" class="btn-cta-dark">Falar com o Time →</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
