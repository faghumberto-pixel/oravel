<?php
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="utf-8" />
<title>Soluções — Catálogo completo | Oravel Enterprise</title>
<link rel="icon" type="image/svg+xml" href="/nova/assets/favicon.svg" />
<link rel="icon" type="image/png" sizes="32x32" href="/nova/assets/favicon-32.png" />
<link rel="icon" type="image/x-icon" href="/nova/assets/favicon.ico" />
<link rel="apple-touch-icon" href="/nova/assets/apple-touch-icon.png" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  @font-face { font-family: "Oravel Deck"; src: local("Archivo Black"); font-weight: 900; }

  :root {
    --ink: #0b0f0d; --ink-2: #121815; --bone: #f4f2ea; --bone-dim: #e8e4d6;
    --steel: #d9d4c4; --olive: #8a8f87; --olive-dim: #565b52;
    --petrol: #1f6f5c; --petrol-bright: #2c9a7f; --orange: #ff6a1a; --orange-dim: #cc5514;
    --bg: var(--bone); --bg-raised: #ffffff; --fg: var(--ink); --fg-dim: var(--olive-dim);
    --border: #d3cfc0; --card-bg: #ffffff; --accent: var(--orange); --accent-ink: #ffffff;
    color-scheme: light;
  }
  @media (prefers-color-scheme: dark) {
    :root { --bg: var(--ink); --bg-raised: var(--ink-2); --fg: var(--bone); --fg-dim: #9aa39c; --border: #263029; --card-bg: var(--ink-2); --accent-ink: #1a0d05; color-scheme: dark; }
  }
  :root[data-theme="dark"] { --bg: var(--ink); --bg-raised: var(--ink-2); --fg: var(--bone); --fg-dim: #9aa39c; --border: #263029; --card-bg: var(--ink-2); --accent-ink: #1a0d05; color-scheme: dark; }
  :root[data-theme="light"] { --bg: var(--bone); --bg-raised: #ffffff; --fg: var(--ink); --fg-dim: var(--olive-dim); --border: #d3cfc0; --card-bg: #ffffff; --accent-ink: #ffffff; color-scheme: light; }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: var(--bg); color: var(--fg); overflow-x: hidden; }
  body { font-family: ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; -webkit-font-smoothing: antialiased; }
  .deck { font-family: "Archivo Black", "Arial Narrow", "Oswald", sans-serif; font-weight: 900; text-transform: uppercase; letter-spacing: -0.01em; text-wrap: balance; }
  .mono { font-family: "SF Mono", "JetBrains Mono", ui-monospace, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
  a { color: inherit; }
  img, svg { max-width: 100%; display: block; }
  .wrap { max-width: 1180px; margin: 0 auto; padding: 0 32px; }
  .wrap-narrow { max-width: 860px; margin: 0 auto; padding: 0 32px; }
  .eyebrow { font-family: "SF Mono", ui-monospace, monospace; text-transform: uppercase; letter-spacing: 0.14em; font-size: 12px; font-weight: 600; }
  section { position: relative; }
  a:focus-visible, button:focus-visible { outline: 2px solid var(--orange); outline-offset: 3px; }
  @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }

  .nav { position: sticky; top: 0; z-index: 50; background: color-mix(in srgb, var(--ink) 92%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(217, 212, 196, 0.14); }
  .nav .wrap { display: flex; align-items: center; justify-content: space-between; height: 68px; }
  .nav-brand { display: flex; align-items: center; gap: 10px; color: var(--bone); font-family: "Archivo Black", sans-serif; font-size: 18px; }
  .nav-brand .tag { font-family: "SF Mono", ui-monospace, monospace; font-size: 11px; letter-spacing: 0.08em; color: #6fd6b6; text-transform: none; font-weight: 400; margin-left: 4px; }
  .nav-mark { height: 32px; width: auto; flex: none; }
  .nav-back { color: #c9c5b6; font-size: 14px; text-decoration: none; display:flex; align-items:center; gap:6px; }
  .nav-back:hover { color: var(--bone); }

  .hero { background: var(--ink); color: var(--bone); padding: 70px 0 56px; border-bottom: 1px solid rgba(244,242,234,0.1); }
  .hero-eyebrow { color: var(--orange); display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
  .hero-eyebrow::before { content: ""; width: 26px; height: 2px; background: var(--orange); }
  .hero h1 { font-size: clamp(28px, 3.8vw, 42px); line-height: 1.1; margin: 0 0 18px; max-width: 20ch; }
  .hero p.lede { font-size: 16.5px; color: #d6d2c4; max-width: 62ch; margin: 0; line-height: 1.65; }

  .section { padding: 70px 0; }
  .section-bone { background: var(--bg); color: var(--fg); }

  .solgrid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
  @media (max-width: 760px) { .solgrid { grid-template-columns: 1fr; } }
  .solcard { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 26px 26px 28px; }
  .solcard .tag { font-family: "SF Mono", ui-monospace, monospace; font-size: 11.5px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent); margin-bottom: 12px; }
  .solcard h3 { font-size: 18px; margin: 0 0 10px; line-height: 1.3; }
  .solcard p.desc { font-size: 14px; color: var(--fg-dim); margin: 0 0 16px; line-height: 1.6; }
  .solcard .items { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; border-top: 1px solid var(--border); padding-top: 14px; }
  .solcard .items li { display: flex; gap: 9px; font-size: 13px; color: var(--fg-dim); line-height: 1.5; }
  .solcard .items li::before { content: ""; width: 5px; height: 5px; background: var(--petrol-bright); margin-top: 7px; flex: none; border-radius: 50%; }

  .final-cta { background: var(--ink); color: var(--bone); padding: 70px 0; text-align: center; }
  .final-cta h2 { font-size: clamp(24px, 3.4vw, 34px); max-width: 22ch; margin: 0 auto 16px; line-height: 1.15; }
  .final-cta p { color: #c9c5b6; font-size: 15.5px; margin: 0 auto 28px; max-width: 48ch; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 15px 26px; border-radius: 3px; font-weight: 700; font-size: 15.5px; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
  .btn-primary { background: var(--orange); color: #1a0d05; }
  .btn-primary:hover { background: #ff7d38; }

  footer.site { background: var(--ink); border-top: 1px solid rgba(244,242,234,0.12); padding: 32px 0; color: #8a8577; font-size: 13px; }
  footer.site .wrap { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
</style>
<style>
  /* Dificulta cópia casual (clique-direito, seleção, arrastar imagem) --
     NÃO impede print screen do sistema operacional, foto de outra câmera,
     nem alguém com DevTools/Leitor de página aberta. Ver aviso completo
     no bloco de script mais abaixo. Formulários continuam selecionáveis
     (usuário precisa digitar/revisar o que preencheu). */
  body {
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
  }
  input, textarea, select {
    -webkit-user-select: text;
    -moz-user-select: text;
    user-select: text;
  }
  img, svg {
    -webkit-user-drag: none;
    pointer-events: auto;
  }
</style>

<style data-site="nav-css">
  .inav-dd-menu a[aria-current="page"] { background: var(--surface, #f6f7f9); }
  .inav-links > a[aria-current="page"] { color: var(--ink, #0f172a); }
  .inav-m { display: none; position: relative; }
  .inav-m > summary { list-style: none; cursor: pointer; font-weight: 700; font-size: 14px; padding: 8px 12px; border: 1px solid var(--border, #e4e7ec); border-radius: 8px; }
  .inav-m > summary::-webkit-details-marker { display: none; }
  .inav-m-list { position: absolute; right: 0; top: 44px; min-width: 240px; background: #fff; border: 1px solid var(--border, #e4e7ec); border-radius: 10px; box-shadow: 0 16px 36px -18px rgba(15,23,42,.35); padding: 6px; display: grid; }
  .inav-m-list a { padding: 10px 12px; border-radius: 6px; text-decoration: none; color: var(--ink, #0f172a); font-size: 14px; }
  .inav-m-list a:hover { background: var(--surface, #f6f7f9); }
  .inav-m-list hr { border: 0; border-top: 1px solid var(--border, #e4e7ec); margin: 4px 0; width: 100%; }
  @media (max-width: 860px) { .inav-m { display: block; } .inav-cta { display: none; } }
  .ifoot-sol { max-width: 1180px; margin: 0 auto 22px; padding: 0 28px; display: flex; flex-wrap: wrap; gap: 8px 22px; font-size: 13.5px; }
  .ifoot-sol b { color: var(--ink, #0f172a); } .ifoot-sol a { color: var(--ink-soft, #5b6472); text-decoration: none; } .ifoot-sol a:hover { color: var(--ink, #0f172a); }
  .tb { background:#000 !important; color:#e5e7eb !important; font-size:12px !important; font-weight:600 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; line-height:1.4 !important; } .tb-in { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; min-height:36px; display:flex !important; align-items:center !important; justify-content:space-between !important; gap:24px; box-sizing:border-box; } .tb a { color:#e5e7eb !important; text-decoration:none !important; white-space:nowrap; } .tb a:hover { color:#f97316 !important; } .tb .tb-msg a { color:#f97316 !important; font-weight:700 !important; margin-left:6px; } .tb .tb-msg b { color:#fff !important; } @media (max-width:760px) { .tb-in { padding:6px 16px !important; } .tb-wa { display:none !important; } }
  nav.inav[data-site="nav"] { position:sticky !important; top:0; z-index:50; display:block !important; background:#fff !important; border-bottom:1px solid #e4e7ec !important; padding:0 !important; margin:0 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; } nav.inav .hd-wrap { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; height:72px !important; display:flex !important; flex-direction:row !important; align-items:center !important; justify-content:space-between !important; gap:24px !important; box-sizing:border-box; } nav.inav .hd-logo { text-decoration:none !important; color:#0f172a !important; font-size:22px !important; font-weight:900 !important; font-family:Manrope,sans-serif !important; letter-spacing:-.01em; flex:none; } nav.inav .hd-logo i { color:#f97316 !important; font-style:normal !important; } nav.inav .hd-links { display:flex !important; align-items:center !important; gap:32px !important; } nav.inav .hd-links a { color:#5b6472 !important; font-size:14px !important; font-weight:600 !important; text-decoration:none !important; transition:color .2s; } nav.inav .hd-links a:hover, nav.inav .hd-links a[aria-current="page"] { color:#0f172a !important; } nav.inav .hd-dd { position:relative; padding-bottom:8px; } nav.inav .hd-dd::after { content:""; position:absolute; top:100%; left:0; right:0; height:8px; z-index:99; } nav.inav .hd-dd > a { display:inline-block !important; padding:8px 0; } nav.inav .hd-menu { position:absolute; top:calc(100% + 8px); left:0; background:#fff; border:1px solid #e4e7ec; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,.1); opacity:0; visibility:hidden; transition:opacity .2s ease, visibility .2s ease; min-width:220px; z-index:100; pointer-events:none; } nav.inav .hd-dd:hover .hd-menu, nav.inav .hd-dd:focus-within .hd-menu { opacity:1; visibility:visible; pointer-events:auto; } nav.inav .hd-menu a { display:block !important; padding:12px 16px !important; font-size:14px !important; border-bottom:1px solid #e4e7ec; } nav.inav .hd-menu a:last-child { border-bottom:none; } nav.inav .hd-menu a:hover { background:#f6f7f9; } nav.inav a.hd-cta { background:#f97316 !important; color:#fff !important; padding:10px 24px !important; border-radius:6px !important; font-weight:700 !important; font-size:14px !important; text-decoration:none !important; white-space:nowrap; flex:none; } nav.inav a.hd-cta:hover { background:#ea580c !important; } @media (max-width:768px) { nav.inav .hd-wrap { flex-direction:column !important; height:auto !important; gap:16px !important; padding:16px 28px !important; } nav.inav .hd-links { flex-wrap:wrap; justify-content:center; gap:16px !important; } nav.inav a.hd-cta, nav.inav .hd-sobre { display:none !important; } }
  .hero { position:relative; overflow:hidden; background:radial-gradient(900px 420px at 85% 0%, rgba(99,102,241,.38), transparent 60%), radial-gradient(700px 420px at 0% 100%, rgba(249,115,22,.26), transparent 60%), #070b14 !important; color:#fff; } .hero::before { content:""; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px); background-size:46px 46px; mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); -webkit-mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); pointer-events:none; } .hero > * { position:relative; } .hero h1, .hero h1 * { color:#fff !important; } .hero .desc, .hero p.desc { color:rgba(226,232,240,.82) !important; } .hero .eyebrow { color:#fdba74; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; } .hero .slogan { color:#fdba74 !important; } .hero .btn-primary { background:#f97316 !important; border-color:#f97316 !important; color:#fff !important; box-shadow:0 10px 30px -12px rgba(249,115,22,.8); } .hero .btn-ghost { background:transparent !important; color:#fff !important; border-color:rgba(255,255,255,.3) !important; } .hero .hero-visual { background:rgba(255,255,255,.06) !important; border-color:rgba(255,255,255,.14) !important; box-shadow:0 30px 70px -30px rgba(99,102,241,.7); } .hero .status-pill { background:rgba(34,197,94,.16) !important; color:#86efac !important; } .chero { position:relative; overflow:hidden; background:radial-gradient(900px 420px at 85% 0%, rgba(99,102,241,.38), transparent 60%), radial-gradient(700px 420px at 0% 100%, rgba(249,115,22,.26), transparent 60%), #070b14 !important; color:#fff; } .chero::before { content:""; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px); background-size:46px 46px; mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); -webkit-mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); pointer-events:none; } .chero > * { position:relative; } .chero h1, .chero h1 * { color:#fff !important; } .chero .desc, .chero p.desc { color:rgba(226,232,240,.82) !important; } .chero .eyebrow { color:#fdba74; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; } .chero .slogan { color:#fdba74 !important; } .chero .btn-primary { background:#f97316 !important; border-color:#f97316 !important; color:#fff !important; box-shadow:0 10px 30px -12px rgba(249,115,22,.8); } .chero .btn-ghost { background:transparent !important; color:#fff !important; border-color:rgba(255,255,255,.3) !important; } .chero .chero-visual { background:rgba(255,255,255,.06) !important; border-color:rgba(255,255,255,.14) !important; box-shadow:0 30px 70px -30px rgba(99,102,241,.7); } .chero .status-pill { background:rgba(34,197,94,.16) !important; color:#86efac !important; } .chero p { color:rgba(226,232,240,.82) !important; }
</style>
<div class="tb" data-site="topbar"><div class="tb-in"><span class="tb-msg"><b>Contrate online:</b> no cartão recorrente você paga menos que o valor cheio. Sem compromisso, no boleto ou Pix. <a href="/planos/">Veja os planos →</a></span><a class="tb-wa" href="https://wa.me/5519999332615" target="_blank" rel="noopener noreferrer">WhatsApp (19) 99933-2615</a></div></div><nav class="inav" data-site="nav" aria-label="Principal"><div class="hd-wrap"><a href="/" class="hd-logo">O<i>r</i>avel</a><div class="hd-links"><div class="hd-dd"><a href="/solucoes/" aria-haspopup="true">Soluções</a><div class="hd-menu"><a href="/erp-cmms/">ERP Locadoras</a><a href="/software-manutencao-industrial/">Manutenção</a><a href="/software-gestao-de-servicos/">Serviços</a><a href="/software-gestao-frota-logistica/">Logística</a><a href="/wms/">Estoque</a><a href="/crm/">Vendas</a><a href="/departamento-pessoal/">Departamento Pessoal</a></div></div><a href="/planos/">Planos</a><a href="/sobre/" class="hd-sobre">Sobre Nós</a><a href="/contato.php">Contato</a></div><a href="/contato.php" class="hd-cta">Falar com um Especialista</a></div></nav>

<header class="hero">
  <div class="wrap-narrow">
    <div class="hero-eyebrow eyebrow">Catálogo completo</div>
    <h1 class="deck">Tudo que sua operação organiza num sistema só</h1>
    <p class="lede">Da etiqueta no equipamento até o contrato fechado — cada área da operação, organizada e conversando entre si.</p>
  </div>
</header>

<section class="section section-bone">
  <div class="wrap">
    <div class="solgrid">

      <article class="solcard">
        <div class="tag">Ativos e Equipamentos</div>
        <h3>Cada equipamento, com etiqueta e histórico próprio</h3>
        <p class="desc">O cadastro central de cada máquina da operação — geradores, plataformas, guindastes, compressores. Tudo mais no sistema gira em torno dele.</p>
        <ul class="items">
          <li>Cadastro completo — marca, patrimônio, número de série, status, horas trabalhadas</li>
          <li>QR Code próprio por equipamento, para consulta instantânea em campo</li>
          <li>Grupos de equipamento — define o checklist básico e o plano de preventiva de cada tipo</li>
          <li>Frota leve (veículos próprios) separada dos equipamentos de locação</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Manutenção</div>
        <h3>Do chamado ao equipamento de volta ao pátio</h3>
        <p class="desc">Toda intervenção — check-in, check-out, preventiva ou corretiva — vira uma Ordem de Serviço, acompanhada num quadro visual do início ao fim.</p>
        <ul class="items">
          <li>Ordens de Serviço com cronômetro, checklist, fotos e assinatura digital</li>
          <li>Kanban do pátio — arraste o atendimento entre as fases, veja tudo de relance</li>
          <li>Preventiva por horímetro — por equipamento ou por um grupo inteiro de uma vez</li>
          <li>Mobilização e desmobilização com checklist de saída e retorno, foto e geolocalização</li>
          <li>Avarias — do registro em campo até a decisão comercial sobre cobrança</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Logística</div>
        <h3>Do pátio até a obra, e de volta</h3>
        <p class="desc">Frota leve própria, motoristas e o custo de cada viagem — tudo ligado à saída e ao retorno do equipamento, sem planilha separada de frete.</p>
        <ul class="items">
          <li>Veículos da frota leve e motoristas, com CNH e disponibilidade</li>
          <li>Chegada no pátio — conferência de retorno antes do equipamento ficar disponível de novo</li>
          <li>Custo de frete e pedágio calculado por viagem, ligado à Ordem de Serviço</li>
          <li>Programação de saídas e chegadas com antecedência, numa agenda só de logística</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Comercial</div>
        <h3>Da proposta ao contrato fechado</h3>
        <p class="desc">Acompanhe a negociação, feche o contrato e deixe o sistema avisar a oficina — sem depender de ninguém lembrar de repassar a informação.</p>
        <ul class="items">
          <li>Contratos — ao fechar, o equipamento trava sozinho como locado para o cliente certo</li>
          <li>Clientes — carteira com histórico completo de locações</li>
          <li>Funil de propostas — reserve um equipamento específico enquanto negocia</li>
          <li>Aviso automático para a oficina assim que uma proposta vira contrato</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Suprimentos</div>
        <h3>Peça em falta não pega ninguém de surpresa</h3>
        <p class="desc">Estoque de materiais com fornecedor homologado, e uma fila de compras que se abre sozinha quando alguma peça fica abaixo do mínimo.</p>
        <ul class="items">
          <li>Catálogo de materiais e peças, com estoque mínimo e máximo por item</li>
          <li>Fornecedores homologados vinculados a cada peça</li>
          <li>Fila de compras gerada automaticamente a partir do consumo em campo</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Gestão e Equipe</div>
        <h3>Cada funcionário vendo só o que precisa</h3>
        <p class="desc">Organize sua equipe por departamento e defina, cargo por cargo, o que cada um pode ver, criar, editar ou apagar no sistema.</p>
        <ul class="items">
          <li>Departamentos — organize a equipe por área (oficina, comercial, logística...)</li>
          <li>Perfis de Acesso — permissão módulo por módulo, sem depender de TI</li>
          <li>Cadastro de funcionários com valor da hora para custo de mão de obra</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Comunicação</div>
        <h3>Tudo registrado, nada perdido em grupo de WhatsApp</h3>
        <p class="desc">Chat da equipe, fotos e áudio direto onde o trabalho acontece — dentro da Ordem de Serviço, não espalhado em conversas soltas.</p>
        <ul class="items">
          <li>Chat interno — conversa 1-a-1 e um chat próprio por Ordem de Serviço</li>
          <li>Foto em cada etapa — checklist, avaria, mobilização</li>
          <li>Mensagem de áudio direto no chat, prático para quem está em campo</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Programação</div>
        <h3>A agenda de cada área, num só lugar</h3>
        <p class="desc">Agenda técnica, agenda comercial e programação de logística — cada equipe vê os próprios compromissos sem precisar abrir planilha ou perguntar para o colega.</p>
        <ul class="items">
          <li>Agenda Técnica — atendimentos e preventivas programadas por técnico</li>
          <li>Agenda Comercial — follow-ups de leads, com atraso destacado automaticamente</li>
          <li>Programação de Logística — saídas e chegadas de equipamento planejadas com antecedência</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Relatórios &amp; Auditoria</div>
        <h3>Quem fez o quê, e quando</h3>
        <p class="desc">Um histórico completo por trás de cada tela — para consultar quando precisar entender uma mudança, ou para exportar e analisar fora do sistema.</p>
        <ul class="items">
          <li>Log de Atividades — cada tela aberta e cada ação de cada funcionário, com data e hora</li>
          <li>Log de Alterações — o valor de antes e de depois em cada mudança de Ativos, Avarias e Leads</li>
          <li>Central de Notificações — histórico de tudo que já foi avisado, sem nunca apagar</li>
          <li>Imprimir ou exportar para Excel/CSV em um clique, em quase toda listagem do sistema</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Portal do Cliente</div>
        <h3>Seu cliente acompanha tudo, sem precisar te ligar</h3>
        <p class="desc">Um acesso próprio para cada locatário — abre chamado, vê o histórico do contrato, troca mensagem com sua equipe e assina documento digital, tudo com comprovação de leitura.</p>
        <ul class="items">
          <li>Abertura de chamados (SAC) direto pelo cliente, sem depender de WhatsApp ou telefone</li>
          <li>Histórico completo do relacionamento — contratos, atendimentos, equipamentos locados</li>
          <li>Mensagens trocadas com sua equipe, registradas no mesmo lugar</li>
          <li>Assinatura digital de documentos com carimbo de tempo e comprovação de leitura</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Planta de Pátio</div>
        <h3>Ache qualquer equipamento no pátio em segundos</h3>
        <p class="desc">O pátio em quadrantes, visual e clicável — filtra por unidade, status ou patrimônio e localiza o equipamento sem andar fileira por fileira.</p>
        <ul class="items">
          <li>Mapa do pátio dividido em quadrantes (Q1-01 a Q4-04, por exemplo)</li>
          <li>Filtro por unidade, status e patrimônio para localização rápida</li>
          <li>Cor por status — disponível, em manutenção, reservado — de relance</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Análise de Avarias</div>
        <h3>Avaria que se repete tem causa — a Oravel mostra qual</h3>
        <p class="desc">Relatório dos últimos 90 dias agrupando avarias por tipo (elétrico, hidráulico, pneus...) para investigar reincidência de peça ou de equipamento antes que vire prejuízo recorrente.</p>
        <ul class="items">
          <li>Janela de 90 dias com avarias agrupadas por tipo de falha</li>
          <li>Mapeamento de reincidência — mesma peça, mesmo equipamento, mesmo técnico</li>
          <li>Dados de apoio para decidir troca preventiva antes da próxima quebra</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Central de IA</div>
        <h3>Inteligência artificial olhando para sua operação inteira</h3>
        <p class="desc">Um hub só de IA: diagnóstico de avaria, risco de perda comercial, prioridade de compra de estoque e sugestão de plano preventivo — tudo num painel, sem precisar caçar informação espalhada.</p>
        <ul class="items">
          <li>Diagnóstico de avarias — cruza o histórico técnico e aponta a causa provável</li>
          <li>Risco de perda comercial — identifica lead ou contrato esfriando antes de perder</li>
          <li>Resumo executivo automático do estoque, com prioridade de compra</li>
          <li>Sugestão de plano preventivo com base no uso real de cada equipamento</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Estoque Inteligente</div>
        <h3>Peça parada é capital parado — a IA aponta onde</h3>
        <p class="desc">Cruza consumo real com itens críticos abaixo do mínimo e mostra, em reais, quanto está imobilizado em material que não gira — antes que vire ruptura de um lado ou prejuízo do outro.</p>
        <ul class="items">
          <li>Resumo executivo automático — itens críticos e risco de ruptura</li>
          <li>Valor financeiro imobilizado por item parado (filtros, correias, retentores...)</li>
          <li>Prioridade de compra sugerida, para não faltar nem sobrar peça no almoxarifado</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Dashboards Executivos</div>
        <h3>Disponibilidade e custo de manutenção, num olhar</h3>
        <p class="desc">Status dos ativos, Ordens de Serviço abertas versus concluídas, custo de manutenção por mês e taxa de disponibilidade — a visão consolidada que orienta decisão de investimento.</p>
        <ul class="items">
          <li>Status de ativos e OS abertas vs. concluídas, atualizado em tempo real</li>
          <li>Custo de manutenção por mês, comparável entre equipamentos e grupos</li>
          <li>Taxa de disponibilidade — o número que mais pesa na negociação de contrato</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Comunicação</div>
        <h3>E-mail integrado, sem sair do sistema para negociar</h3>
        <p class="desc">Caixa de entrada, enviados e rascunhos direto na plataforma — atendimento e negociação com cliente centralizados, sem depender de outra ferramenta aberta em paralelo.</p>
        <ul class="items">
          <li>Recebidos, Enviados e Rascunhos integrados à gestão da operação</li>
          <li>Histórico de e-mail ligado ao cliente e ao contrato, não solto numa caixa externa</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Dashboard Comercial</div>
        <h3>Leads e conversão, sem esperar o fim do mês para saber</h3>
        <p class="desc">Cadastrados, em andamento, convertidos e perdidos — com taxa de conversão e origem de cada lead, para saber onde investir esforço de prospecção.</p>
        <ul class="items">
          <li>Leads cadastrados, em andamento, convertidos e perdidos, tudo num painel</li>
          <li>Taxa de conversão e origem do lead, para entender o que está funcionando</li>
        </ul>
      </article>

      <article class="solcard">
        <div class="tag">Funil de Vendas</div>
        <h3>Cada negociação, na etapa certa, com valor visível</h3>
        <p class="desc">Novo, Contato Iniciado, Qualificado, Convertido — o pipeline inteiro visual, com o valor monetário de cada negociação em cada etapa do funil.</p>
        <ul class="items">
          <li>Etapas claras — Novo, Contato Iniciado, Qualificado, Convertido</li>
          <li>Valor monetário por etapa, para enxergar o funil em receita, não só em quantidade</li>
        </ul>
      </article>

    </div>
  </div>
</section>

<section class="final-cta">
  <div class="wrap">
    <h2 class="deck">Qual dessas áreas dói mais na sua operação?</h2>
    <p>Conta pra gente e a gente mostra, na prática, como a Oravel resolve.</p>
    <a href="../#agendar" class="btn btn-primary">Agendar avaliação</a>
  </div>
</section>

<footer class="site">
  <div class="wrap">
    <span>© 2026 Oravel — gestão de equipamentos e manutenção.</span>
    <span>oravel.com.br/nova/enterprise/solucoes</span>
  </div>
</footer>

<script>
  /*
    Medidas de dificultar-cópia-casual da página. IMPORTANTE, leia antes de
    mexer ou de assumir que isso "protege" o conteúdo:

    Nada rodando no navegador consegue bloquear print screen do sistema
    operacional, uma foto tirada com outro celular, extensões de captura,
    gravação de tela, o modo Leitor do navegador, ou alguém copiando via
    DevTools/"Ver código-fonte" (Ctrl+U não é bloqueável por JS). Essas
    técnicas só aumentam o atrito de um "selecionar tudo + copiar" casual
    -- não são uma proteção de conteúdo de verdade. Se o objetivo é
    realmente restringir acesso a preços/condições, a solução correta é
    colocar esse conteúdo atrás de login, não bloqueio de clique no
    front-end público.
  */
  (function () {
    document.addEventListener('contextmenu', function (e) {
      e.preventDefault();
    });

    document.addEventListener('copy', function (e) {
      e.preventDefault();
    });

    document.addEventListener('cut', function (e) {
      e.preventDefault();
    });

    document.addEventListener('dragstart', function (e) {
      if (e.target.tagName === 'IMG') e.preventDefault();
    });

    // Ctrl/Cmd+C e Ctrl/Cmd+S (salvar página) -- não bloqueia dentro de
    // input/textarea, pra não impedir o usuário de copiar o que ELE
    // mesmo digitou num formulário.
    document.addEventListener('keydown', function (e) {
      var emCampoDeTexto = ['INPUT', 'TEXTAREA'].includes(e.target.tagName);
      var ctrlOuCmd = e.ctrlKey || e.metaKey;

      if (!emCampoDeTexto && ctrlOuCmd && ['c', 'x', 's', 'u'].includes(e.key.toLowerCase())) {
        e.preventDefault();
      }
    });
  })();
</script>
<script async src="https://app.oravel.com.br/t.js?v=2"></script>
