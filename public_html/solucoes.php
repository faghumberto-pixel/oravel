<?php
// Oravel — Soluções (visão completa dos módulos, em linguagem comercial)

$site_name    = "Oravel";
$slogan       = "Gestão de Operações em Campo";
$app_url      = "https://app.oravel.com.br";
$contact_mail = "contato@oravel.com.br";

$page_title       = "Soluções | $site_name";
$page_description = "Tudo que a Oravel organiza pra sua operação: ativos, manutenção, comercial, suprimentos, equipe e comunicação, numa plataforma só.";
$nav_active       = "solucoes";

require __DIR__ . '/inc/journey.php';

$areas = [
  [
    "tag"   => "Ativos e Frota",
    "title" => "Cada equipamento, com etiqueta e histórico próprio",
    "desc"  => "O cadastro central de cada equipamento da sua operação — geradores, plataformas, guindastes, compressores e demais equipamentos. Tudo mais no sistema gira em torno dele.",
    "items" => [
      "Cadastro completo — marca, patrimônio, número de série, status, horas trabalhadas",
      "QR Code próprio por equipamento, pra consulta instantânea em campo",
      "Grupos de equipamento — define o checklist básico e o plano de preventiva de cada tipo",
      "Frota leve (veículos próprios) separada dos demais equipamentos",
    ],
    "mock" => "qr",
  ],
  [
    "tag"   => "Manutenção",
    "title" => "Do chamado ao equipamento de volta ao pátio",
    "desc"  => "Toda intervenção — check-in, check-out, preventiva ou corretiva — vira uma Ordem de Serviço, acompanhada num quadro visual do início ao fim.",
    "items" => [
      "Ordens de Serviço com cronômetro, checklist, fotos e assinatura digital",
      "Kanban do pátio — arraste o atendimento entre as fases, veja tudo de relance",
      "Preventiva por horímetro — por equipamento ou por um grupo inteiro de uma vez",
      "Mobilização e desmobilização com checklist de saída e retorno, foto e geolocalização",
      "Avarias — do registro em campo até a decisão comercial sobre cobrança",
    ],
    "mock" => "kanban",
  ],
  [
    "tag"   => "Logística",
    "title" => "Do pátio até a obra, e de volta",
    "desc"  => "Frota leve própria, motoristas e o custo de cada viagem — tudo ligado à saída e ao retorno do equipamento, sem planilha separada de frete.",
    "items" => [
      "Veículos da frota leve e motoristas, com CNH e disponibilidade",
      "Chegada no pátio — conferência de retorno antes do equipamento ficar disponível de novo",
      "Custo de frete e pedágio calculado por viagem, ligado à Ordem de Serviço",
      "Programação de saídas e chegadas com antecedência, numa agenda só de logística",
    ],
    "mock" => "route",
  ],
  [
    "tag"   => "Comercial",
    "title" => "Da proposta ao contrato fechado",
    "desc"  => "Acompanhe a negociação, feche o contrato e deixe o sistema avisar a oficina — sem depender de ninguém lembrar de repassar a informação.",
    "items" => [
      "Contratos — ao fechar, o equipamento trava sozinho como locado pro cliente certo",
      "Clientes — carteira com histórico completo de contratos e serviços",
      "Funil de propostas — reserve um equipamento específico enquanto negocia",
      "Aviso automático pra oficina assim que uma proposta vira contrato",
    ],
    "mock" => "funnel",
  ],
  [
    "tag"   => "Suprimentos",
    "title" => "Peça em falta não pega ninguém de surpresa",
    "desc"  => "Estoque de materiais com fornecedor homologado, e uma fila de compras que se abre sozinha quando alguma peça fica abaixo do mínimo.",
    "items" => [
      "Catálogo de materiais e peças, com estoque mínimo e máximo por item",
      "Fornecedores homologados vinculados a cada peça",
      "Fila de compras gerada automaticamente a partir do consumo em campo",
    ],
    "mock" => "stock",
  ],
  [
    "tag"   => "Gestão e Equipe",
    "title" => "Cada funcionário vendo só o que precisa",
    "desc"  => "Organize sua equipe por departamento e defina, cargo por cargo, o que cada um pode ver, criar, editar ou apagar no sistema.",
    "items" => [
      "Departamentos — organize a equipe por área (oficina, comercial, logística...)",
      "Perfis de Acesso — permissão módulo por módulo, sem depender de TI",
      "Cadastro de funcionários com valor da hora pra custo de mão de obra",
    ],
    "mock" => "roles",
  ],
  [
    "tag"   => "Comunicação",
    "title" => "Tudo registrado, nada perdido em grupo de WhatsApp",
    "desc"  => "Chat da equipe, fotos e áudio direto onde o trabalho acontece — dentro da Ordem de Serviço, não espalhado em conversas soltas.",
    "items" => [
      "Chat interno — conversa 1-a-1 e um chat próprio por Ordem de Serviço",
      "Foto em cada etapa — checklist, avaria, mobilização",
      "Mensagem de áudio direto no chat, prático pra quem está em campo",
    ],
    "mock" => "chat",
  ],
  [
    "tag"   => "Programação",
    "title" => "A agenda de cada área, num só lugar",
    "desc"  => "Agenda técnica, agenda comercial e programação de logística — cada equipe vê os próprios compromissos sem precisar abrir planilha ou perguntar pro colega.",
    "items" => [
      "Agenda Técnica — atendimentos e preventivas programadas por técnico",
      "Agenda Comercial — follow-ups de leads, com atraso destacado automaticamente",
      "Programação de Logística — saídas e chegadas de equipamento planejadas com antecedência",
    ],
    "mock" => "agenda",
  ],
  [
    "tag"   => "Relatórios & Auditoria",
    "title" => "Quem fez o quê, e quando",
    "desc"  => "Um histórico completo por trás de cada tela — pra consultar quando precisar entender uma mudança, ou pra exportar e analisar fora do sistema.",
    "items" => [
      "Log de Atividades — cada tela aberta e cada ação de cada funcionário, com data e hora",
      "Log de Alterações — o valor de antes e de depois em cada mudança de Ativos, Avarias e Leads",
      "Central de Notificações — histórico de tudo que já foi avisado, sem nunca apagar",
      "Imprimir ou exportar pra Excel/CSV em um clique, em quase toda listagem do sistema",
    ],
    "mock" => "log",
  ],
  [
    "tag"   => "Portal do Cliente",
    "title" => "Seu cliente acompanha tudo, sem precisar te ligar",
    "desc"  => "Um acesso próprio pra cada locatário — abre chamado, vê o histórico do contrato, troca mensagem com sua equipe e assina documento digital, tudo com comprovação de leitura.",
    "items" => [
      "Abertura de chamados (SAC) direto pelo cliente, sem depender de WhatsApp ou telefone",
      "Histórico completo do relacionamento — contratos, atendimentos, equipamentos locados",
      "Mensagens trocadas com sua equipe, registradas no mesmo lugar",
      "Assinatura digital de documentos com carimbo de tempo e comprovação de leitura",
    ],
    "mock" => "portal",
  ],
  [
    "tag"   => "Planta de Pátio",
    "title" => "Ache qualquer equipamento no pátio em segundos",
    "desc"  => "O pátio em quadrantes, visual e clicável — filtra por unidade, status ou patrimônio e localiza o equipamento sem andar fileira por fileira.",
    "items" => [
      "Mapa do pátio dividido em quadrantes (Q1-01 a Q4-04, por exemplo)",
      "Filtro por unidade, status e patrimônio pra localização rápida",
      "Cor por status — disponível, em manutenção, reservado — de relance",
    ],
    "mock" => "yard",
  ],
  [
    "tag"   => "Análise de Avarias",
    "title" => "Avaria que se repete tem causa — a Oravel mostra qual",
    "desc"  => "Relatório dos últimos 90 dias agrupando avarias por tipo (elétrico, hidráulico, pneus...) pra investigar reincidência de peça ou de equipamento antes que vire prejuízo recorrente.",
    "items" => [
      "Janela de 90 dias com avarias agrupadas por tipo de falha",
      "Mapeamento de reincidência — mesma peça, mesmo equipamento, mesmo técnico",
      "Dados de apoio pra decidir troca preventiva antes da próxima quebra",
    ],
    "mock" => "damage",
  ],
  [
    "tag"   => "Central de IA",
    "title" => "Inteligência artificial olhando pra sua operação inteira",
    "desc"  => "Um hub só de IA: diagnóstico de avaria, risco de perda comercial, prioridade de compra de estoque e sugestão de plano preventivo — tudo num painel, sem precisar caçar informação espalhada.",
    "items" => [
      "Diagnóstico de avarias — cruza o histórico técnico e aponta a causa provável",
      "Risco de perda comercial — identifica lead ou contrato esfriando antes de perder",
      "Resumo executivo automático do estoque, com prioridade de compra",
      "Sugestão de plano preventivo com base no uso real de cada equipamento",
    ],
    "mock" => "ai",
  ],
  [
    "tag"   => "Estoque Inteligente",
    "title" => "Peça parada é capital parado — a IA aponta onde",
    "desc"  => "Cruza consumo real com itens críticos abaixo do mínimo e mostra, em reais, quanto está imobilizado em material que não gira — antes que vire ruptura de um lado ou prejuízo do outro.",
    "items" => [
      "Resumo executivo automático — itens críticos e risco de ruptura",
      "Valor financeiro imobilizado por item parado (filtros, correias, retentores...)",
      "Prioridade de compra sugerida, pra não faltar nem sobrar peça no almoxarifado",
    ],
    "mock" => "idle-stock",
  ],
  [
    "tag"   => "Dashboards Executivos",
    "title" => "Disponibilidade de frota e custo de manutenção, num olhar",
    "desc"  => "Status dos ativos, Ordens de Serviço abertas versus concluídas, custo de manutenção por mês e taxa de disponibilidade — a visão consolidada que orienta decisão de investimento.",
    "items" => [
      "Status de ativos e OS abertas vs. concluídas, atualizado em tempo real",
      "Custo de manutenção por mês, comparável entre equipamentos e grupos",
      "Taxa de disponibilidade da frota — o número que mais pesa na negociação de contrato",
    ],
    "mock" => "exec-dash",
  ],
  [
    "tag"   => "Comunicação",
    "title" => "E-mail integrado, sem sair do sistema pra negociar",
    "desc"  => "Caixa de entrada, enviados e rascunhos direto na plataforma — atendimento e negociação com cliente centralizados, sem depender de outra ferramenta aberta em paralelo.",
    "items" => [
      "Recebidos, Enviados e Rascunhos integrados à gestão da operação",
      "Histórico de e-mail ligado ao cliente e ao contrato, não solto numa caixa externa",
    ],
    "mock" => "inbox",
  ],
  [
    "tag"   => "Dashboard Comercial",
    "title" => "Leads e conversão, sem esperar o fim do mês pra saber",
    "desc"  => "Cadastrados, em andamento, convertidos e perdidos — com taxa de conversão e origem de cada lead, pra saber onde investir esforço de prospecção.",
    "items" => [
      "Leads cadastrados, em andamento, convertidos e perdidos, tudo num painel",
      "Taxa de conversão e origem do lead, pra entender o que está funcionando",
    ],
    "mock" => "sales-dash",
  ],
  [
    "tag"   => "Funil de Vendas",
    "title" => "Cada negociação, na etapa certa, com valor visível",
    "desc"  => "Novo, Contato Iniciado, Qualificado, Convertido — o pipeline inteiro visual, com o valor monetário de cada negociação em cada etapa do funil.",
    "items" => [
      "Etapas claras — Novo, Contato Iniciado, Qualificado, Convertido",
      "Valor monetário por etapa, pra enxergar o funil em receita, não só em quantidade",
    ],
    "mock" => "pipeline",
  ],
];
?>
<?php include __DIR__ . '/inc/head.php'; ?>
<?php include __DIR__ . '/inc/nav.php'; ?>

<header class="page-header">
  <div class="page-header-inner">
    <span class="pill-badge">Soluções</span>
    <h1>Tudo que sua operação<br>precisa, num sistema só.</h1>
    <p>Da etiqueta no equipamento até o contrato fechado — cada área da sua operação, organizada e conversando entre si.</p>
  </div>
</header>

<section class="zone-light" style="background:var(--bg);color:var(--fg)">

  <div class="wrap-wide">
    <?php // Trilha única cobrindo as 9 áreas -- pedido do usuário 2026-07-27:
          // antes só "Ativos e Frota" e "Manutenção" tinham mockup, o resto
          // caía numa coluna só de texto, sem o caminho pontilhado nem
          // alternância de lado. Agora toda área tem visual e faz parte da
          // mesma curva contínua da página. ?>
    <div class="journey">
      <?= journey_svg(count($areas)) ?>
      <?php foreach ($areas as $i => $area): ?>
      <div class="path-block<?= $i % 2 === 1 ? ' reverse' : '' ?> anim">
        <div class="path-node"></div>
        <div class="feat-text">
          <span class="pill-badge"><?= $area['tag'] ?></span>
          <h3><?= $area['title'] ?></h3>
          <p><?= $area['desc'] ?></p>
          <div class="module-list">
            <?php foreach ($area['items'] as $it): ?>
            <div class="module-item"><span class="module-dot"></span><?= $it ?></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="feat-visual">
          <div class="app-window">
            <div class="aw-top"><div class="aw-dot r"></div><div class="aw-dot y"></div><div class="aw-dot g"></div><span class="aw-title">Oravel — <?= $area['tag'] ?></span></div>
            <div class="aw-body">

              <?php if ($area['mock'] === 'qr'): ?>
                <div class="qr-grid">
                  <?php
                  $pattern = "1110101 1001011 1110100 0000101 1010111 0101001 1110010";
                  foreach (explode(" ", $pattern) as $row) {
                    foreach (str_split($row) as $bit) {
                      echo '<div class="qr-cell' . ($bit === '0' ? ' off' : '') . '"></div>';
                    }
                  }
                  ?>
                </div>
                <span class="pill-status">Disponível</span>
                <div class="info-row"><span class="info-label">Equipamento</span><span class="info-val">Compressor de Ar 185 PCM</span></div>
                <div class="info-row"><span class="info-label">Grupo</span><span class="info-val">Compressores</span></div>
                <div class="info-row"><span class="info-label">Horímetro</span><span class="info-val">640 h</span></div>

              <?php elseif ($area['mock'] === 'kanban'): ?>
                <div class="kb-cols">
                  <div>
                    <div class="kb-col-title">Aguardando</div>
                    <div class="kb-card"><div class="kb-bar"></div><div class="kb-bar s"></div></div>
                  </div>
                  <div>
                    <div class="kb-col-title">Em Manutenção</div>
                    <div class="kb-card urgent"><div class="kb-bar"></div><div class="kb-bar s"></div><span class="kb-tag">urgente</span></div>
                    <div class="kb-card"><div class="kb-bar"></div><div class="kb-bar s"></div></div>
                  </div>
                  <div>
                    <div class="kb-col-title">Concluído</div>
                    <div class="kb-card"><div class="kb-bar"></div><div class="kb-bar s"></div></div>
                  </div>
                </div>

              <?php elseif ($area['mock'] === 'route'): ?>
                <div class="mock-map">
                  <svg class="mock-route-line" viewBox="0 0 100 60" preserveAspectRatio="none"><path d="M14 48 Q 40 12 60 30 T 88 14" fill="none" stroke="#E8541A" stroke-width="1.5" stroke-dasharray="3 3"/></svg>
                  <div class="mock-map-pin live" style="top:78%;left:12%"></div>
                  <div class="mock-map-pin" style="top:48%;left:58%"></div>
                  <div class="mock-map-pin" style="top:22%;left:86%"></div>
                </div>
                <div class="info-row"><span class="info-label">Frete + pedágio da viagem</span><span class="info-val">R$ 186,40</span></div>

              <?php elseif ($area['mock'] === 'funnel'): ?>
                <div class="fm-flow">
                  <div class="fm-band" style="width:100%">Proposta Enviada</div>
                  <div class="fm-band" style="width:70%">Em Negociação</div>
                  <div class="fm-band good" style="width:42%">Contrato Fechado</div>
                </div>

              <?php elseif ($area['mock'] === 'stock'): ?>
                <div class="info-row"><span class="info-label">Filtro de óleo 15W40</span><span class="info-val warn">4 un. (mín. 10)</span></div>
                <div class="info-row"><span class="info-label">Correia dentada</span><span class="info-val">18 un. (mín. 6)</span></div>
                <div class="info-row"><span class="info-label">Óleo hidráulico 20L</span><span class="info-val">22 un. (mín. 8)</span></div>
                <span class="pill-status" style="margin-top:14px;margin-bottom:0">Fila de compra aberta</span>

              <?php elseif ($area['mock'] === 'roles'): ?>
                <div class="info-row"><span class="info-label">Técnico</span><span class="info-val">Ver Ativos, Criar OS</span></div>
                <div class="info-row"><span class="info-label">Comercial</span><span class="info-val">Ver Leads, Criar Contrato</span></div>
                <div class="info-row"><span class="info-label">Financeiro</span><span class="info-val">Ver Contas a Pagar/Receber</span></div>
                <div class="info-row"><span class="info-label">Almoxarifado</span><span class="info-val">Ver e Editar Estoque</span></div>

              <?php elseif ($area['mock'] === 'chat'): ?>
                <div class="info-row"><span class="info-label">Técnico João — OS #4821</span><span class="info-val">09:14</span></div>
                <div class="info-row"><span class="info-label">🎙️ Áudio — 0:18</span><span class="info-val">09:15</span></div>
                <div class="info-row"><span class="info-label">Oficina — peça chegou</span><span class="info-val">08:47</span></div>

              <?php elseif ($area['mock'] === 'agenda'): ?>
                <div class="info-row"><span class="info-label">09:00 — Preventiva Gerador Cummins</span><span class="info-val">Téc. João</span></div>
                <div class="info-row"><span class="info-label">11:30 — Follow-up Construtora Vega</span><span class="info-val warn">Atrasado</span></div>
                <div class="info-row"><span class="info-label">14:00 — Saída Compressor Atlas</span><span class="info-val">Logística</span></div>

              <?php elseif ($area['mock'] === 'log'): ?>
                <div class="info-row"><span class="info-label">Ativo #AT-0231 atualizado</span><span class="info-val">João, 14:32</span></div>
                <div class="info-row"><span class="info-label">Avaria #118 — cobrança decidida</span><span class="info-val">Marcos, 11:05</span></div>
                <div class="info-row"><span class="info-label">Lead "Construtora Vega" convertido</span><span class="info-val">Ana, 09:40</span></div>

              <?php elseif ($area['mock'] === 'portal'): ?>
                <span class="pill-status">Chamado #0342 — Em Atendimento</span>
                <div class="info-row"><span class="info-label">Contrato #1187 — Gerador 500 kVA</span><span class="info-val">Ativo</span></div>
                <div class="info-row"><span class="info-label">Laudo de Manutenção.pdf</span><span class="info-val">Assinado ✓</span></div>
                <div class="info-row"><span class="info-label">Mensagem — "Peça já chegou"</span><span class="info-val">09:15</span></div>

              <?php elseif ($area['mock'] === 'yard'): ?>
                <div class="gc-grid">
                  <?php
                  $gcStatus = "2222110222110222222211102222222211022221102222";
                  foreach (str_split($gcStatus) as $bit) {
                      $cls = $bit === '1' ? 'warn' : ($bit === '0' ? 'crit' : '');
                      echo '<div class="gc-cell ' . $cls . '"></div>';
                  }
                  ?>
                </div>
                <div class="info-row"><span class="info-label">Quadrante Q2-03</span><span class="info-val">Compressor Atlas Copco</span></div>

              <?php elseif ($area['mock'] === 'damage'): ?>
                <div class="info-row"><span class="info-label">Elétrico</span><span class="info-val warn">7 ocorrências</span></div>
                <div class="info-row"><span class="info-label">Hidráulico</span><span class="info-val">4 ocorrências</span></div>
                <div class="info-row"><span class="info-label">Pneus</span><span class="info-val">3 ocorrências</span></div>
                <span class="pill-status" style="margin-top:14px;margin-bottom:0">Janela de 90 dias</span>

              <?php elseif ($area['mock'] === 'ai'): ?>
                <div class="info-row"><span class="info-label">🔧 Diagnóstico de Avaria</span><span class="info-val">Analisar</span></div>
                <div class="info-row"><span class="info-label">📉 Risco de Perda Comercial</span><span class="info-val warn">2 contratos</span></div>
                <div class="info-row"><span class="info-label">📦 Prioridade de Compra</span><span class="info-val">Analisar</span></div>
                <div class="info-row"><span class="info-label">🛠️ Plano Preventivo Sugerido</span><span class="info-val">Analisar</span></div>

              <?php elseif ($area['mock'] === 'idle-stock'): ?>
                <div class="info-row"><span class="info-label">Bateria 12V</span><span class="info-val warn">Ruptura em 6 dias</span></div>
                <div class="info-row"><span class="info-label">Retentor hidráulico</span><span class="info-val">R$ 3.240 parado</span></div>
                <div class="info-row"><span class="info-label">Correia dentada</span><span class="info-val">R$ 1.180 parado</span></div>
                <span class="pill-status" style="margin-top:14px;margin-bottom:0">Resumo gerado por IA</span>

              <?php elseif ($area['mock'] === 'exec-dash'): ?>
                <div class="mock-bars">
                  <?php
                  $bars = [["Disponível","92%","92"], ["Preventiva em dia","87%","87"], ["OS concluídas","78%","78"]];
                  foreach ($bars as $b): ?>
                  <div class="mock-row">
                    <span class="mock-lbl"><?= $b[0] ?></span>
                    <div class="mock-track"><div class="mock-fill" style="width:<?= $b[2] ?>%"></div></div>
                    <span class="mock-val"><?= $b[1] ?></span>
                  </div>
                  <?php endforeach; ?>
                </div>
                <div class="mock-kpis">
                  <div class="kpi"><div class="kpi-num">R$ 18.4k</div><div class="kpi-lbl">Custo/mês</div></div>
                  <div class="kpi"><div class="kpi-num">92%</div><div class="kpi-lbl">Disponibilidade</div></div>
                </div>

              <?php elseif ($area['mock'] === 'inbox'): ?>
                <div class="info-row"><span class="info-label">📥 Construtora Vega — Proposta</span><span class="info-val">09:14</span></div>
                <div class="info-row"><span class="info-label">📤 Re: Contrato #1187</span><span class="info-val">08:52</span></div>
                <div class="info-row"><span class="info-label">📝 Rascunho — Orçamento gerador</span><span class="info-val">Ontem</span></div>

              <?php elseif ($area['mock'] === 'sales-dash'): ?>
                <div class="mock-kpis" style="grid-template-columns:1fr 1fr">
                  <div class="kpi"><div class="kpi-num">48</div><div class="kpi-lbl">Leads cadastrados</div></div>
                  <div class="kpi"><div class="kpi-num">32%</div><div class="kpi-lbl">Taxa de conversão</div></div>
                </div>
                <div class="info-row" style="margin-top:14px"><span class="info-label">Origem — Indicação</span><span class="info-val">41%</span></div>
                <div class="info-row"><span class="info-label">Origem — Prospecção Ativa</span><span class="info-val">35%</span></div>

              <?php elseif ($area['mock'] === 'pipeline'): ?>
                <div class="fm-flow">
                  <div class="fm-band" style="width:100%">Novo</div>
                  <div class="fm-band" style="width:78%">Contato Iniciado</div>
                  <div class="fm-band" style="width:52%">Qualificado</div>
                  <div class="fm-band good" style="width:30%">Convertido</div>
                </div>
                <div class="info-row" style="margin-top:14px"><span class="info-label">Valor em negociação</span><span class="info-val">R$ 142.000</span></div>

              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</section>

<div class="cta-section" id="cta">
  <div class="cta-inner">
    <span class="pill-badge">Vamos conversar</span>
    <h2 class="section-title">Qual dessas áreas dói<br>mais na sua operação?</h2>
    <p>Conta pra gente e a gente mostra, na prática, como o Oravel resolve.</p>
    <div class="cta-btns">
      <a href="/contato.php" class="btn-cta-dark">Agendar Demonstração →</a>
      <a href="<?= $app_url ?>" target="_blank" rel="noopener" class="btn-cta-out">Acessar o Sistema</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>
