<?php
header('Content-Type: text/html; charset=utf-8');

// Planos nomeados (Operacional/Gestão/Enterprise) removidos 2026-09-23 --
// não existe mais pacote fixo, cada cliente tem um conjunto de módulos
// negociado individualmente. Esta página não recebe mais ?plano= nenhum.
?>
<meta charset="utf-8" />
<title>Solicitar Proposta — Oravel</title>
<link rel="canonical" href="https://oravel.com.br/proposta/">
<link rel="icon" type="image/png" sizes="32x32" href="/nova/assets/favicon-32.png?v=4" /><link rel="apple-touch-icon" href="/nova/assets/apple-touch-icon.png?v=4" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="description" content="Fale com a Oravel e receba uma proposta sob medida para a gestão de equipamentos e manutenção da sua operação." />
<style>
  @font-face {
    font-family: "Oravel Deck";
    src: local("Archivo Black");
    font-weight: 900;
  }

  :root {
    --ink: #0b0f0d;
    --ink-2: #121815;
    --bone: #f4f2ea;
    --bone-dim: #e8e4d6;
    --steel: #d9d4c4;
    --olive: #8a8f87;
    --olive-dim: #565b52;
    --petrol: #1f6f5c;
    --petrol-bright: #2c9a7f;
    --orange: #ff6a1a;
    --orange-dim: #cc5514;
    color-scheme: light;
  }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: var(--ink); color: var(--bone); overflow-x: hidden; }
  body {
    font-family: ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: 16px; line-height: 1.6; -webkit-font-smoothing: antialiased;
    min-height: 100vh; display: flex; flex-direction: column;
    background-image:
      linear-gradient(180deg, rgba(11,15,13,0.88) 0%, rgba(11,15,13,0.82) 45%, rgba(11,15,13,0.94) 100%),
      url('/proposta/bg.jpg');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
  }
  @media (max-width: 760px) {
    body { background-attachment: scroll; }
  }

  .deck {
    font-family: "Archivo Black", "Arial Narrow", "Oswald", sans-serif;
    font-weight: 900; text-transform: uppercase; letter-spacing: -0.01em; text-wrap: balance;
  }
  a { color: inherit; }
  img, svg { max-width: 100%; display: block; }
  .wrap { max-width: 1180px; margin: 0 auto; padding: 0 32px; }
  a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible, select:focus-visible {
    outline: 2px solid var(--orange); outline-offset: 3px;
  }
  @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }

  /* NAV */
  .nav {
    background: color-mix(in srgb, var(--ink) 92%, transparent);
    border-bottom: 1px solid rgba(217, 212, 196, 0.14);
  }
  .nav .wrap { display: flex; align-items: center; justify-content: space-between; height: 68px; }
  .nav-brand { display: flex; align-items: center; gap: 10px; color: var(--bone); font-family: "Archivo Black", sans-serif; font-size: 18px; }
  .nav-mark { height: 32px; width: auto; flex: none; }
  .nav-back { color: #c9c5b6; font-size: 14px; text-decoration: none; display: flex; align-items: center; gap: 6px; }
  .nav-back:hover { color: var(--bone); }

  /* MAIN */
  main { flex: 1; display: flex; align-items: center; padding: 56px 0; }
  .proposta-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center;
    width: 100%;
  }
  @media (max-width: 900px) { .proposta-grid { grid-template-columns: 1fr; gap: 40px; } }

  .eyebrow {
    font-family: "SF Mono", ui-monospace, monospace; text-transform: uppercase;
    letter-spacing: 0.14em; font-size: 12px; font-weight: 600; color: var(--orange);
    display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
  }
  .eyebrow::before { content: ""; width: 26px; height: 2px; background: var(--orange); }

  h1 { font-size: clamp(28px, 4vw, 42px); line-height: 1.1; margin: 0 0 18px; max-width: 14ch; }
  h1 em { font-style: normal; color: var(--orange); }
  .lede { font-size: 16.5px; color: #d6d2c4; max-width: 48ch; margin: 0 0 30px; line-height: 1.65; }

  .value-list { list-style: none; margin: 0 0 34px; padding: 0; display: flex; flex-direction: column; gap: 14px; }
  .value-list li { display: flex; gap: 12px; font-size: 15px; color: var(--bone); line-height: 1.5; }
  .value-list .check {
    flex: none; width: 20px; height: 20px; border-radius: 50%;
    background: rgba(44,154,127,0.18); color: #6fd6b6;
    display: flex; align-items: center; justify-content: center; margin-top: 1px;
  }

  .proof {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 18px; border-radius: 8px;
    background: rgba(244,242,234,0.06); border: 1px solid rgba(244,242,234,0.14);
  }
  .proof .num { font-family: "Archivo Black", sans-serif; font-size: 26px; color: var(--orange); line-height: 1; }
  .proof .txt { font-size: 13.5px; color: #b9b4a6; line-height: 1.4; }

  /* FORM CARD */
  .form-card {
    background: var(--ink-2); border: 1px solid rgba(244,242,234,0.14); border-radius: 12px;
    padding: 36px 34px;
  }
  .form-card h2 { font-size: 20px; margin: 0 0 6px; color: var(--bone); }
  .form-card .form-sub { font-size: 14px; color: #b9b4a6; margin: 0 0 26px; }

  form.contact { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 7px; }
  .field label { font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #9aa39c; font-family: "SF Mono", ui-monospace, monospace; }
  .field input, .field select, .field textarea {
    background: #10160f; border: 1px solid #2a332c; color: var(--bone);
    border-radius: 4px; padding: 12px 14px; font-size: 15px; font-family: inherit;
  }
  .field input::placeholder, .field textarea::placeholder { color: #5c665e; }
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 480px) { .field-row { grid-template-columns: 1fr; } }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 15px 26px; border-radius: 3px; font-weight: 700; font-size: 15.5px;
    text-decoration: none; border: 1px solid transparent; cursor: pointer;
    transition: transform 0.15s ease, background 0.15s ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn:disabled { opacity: 0.65; cursor: default; transform: none; }
  .btn-primary { background: var(--orange); color: #1a0d05; width: 100%; margin-top: 6px; }
  .btn-primary:hover { background: #ff7d38; }

  .form-status {
    display: none; border-radius: 6px; padding: 13px 15px; font-size: 13.5px;
    margin-bottom: 4px; line-height: 1.5;
  }

  .form-alt { margin: 16px 0 0; font-size: 13.5px; color: #9aa39c; text-align: center; }
  .form-alt a { color: var(--orange); text-decoration: underline; font-weight: 600; }

  footer.site {
    background: var(--ink); border-top: 1px solid rgba(244,242,234,0.12);
    padding: 28px 0; color: #8a8577; font-size: 13px;
  }
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
  nav.inav[data-site="nav"] { position:sticky !important; top:0; z-index:50; display:block !important; background:#fff !important; border-bottom:1px solid #e4e7ec !important; padding:0 !important; margin:0 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; } nav.inav .hd-wrap { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; height:72px !important; display:flex !important; flex-direction:row !important; align-items:center !important; justify-content:space-between !important; gap:24px !important; box-sizing:border-box; } nav.inav .hd-logo { text-decoration:none !important; color:#0f172a !important; font-size:22px !important; font-weight:900 !important; font-family:Manrope,sans-serif !important; letter-spacing:-.01em; flex:none; } nav.inav .hd-logo i { color:#f97316 !important; font-style:normal !important; } nav.inav .hd-links { display:flex !important; align-items:center !important; gap:32px !important; } nav.inav .hd-links a { color:#5b6472 !important; font-size:14px !important; font-weight:600 !important; text-decoration:none !important; transition:color .2s; } nav.inav .hd-links a:hover, nav.inav .hd-links a[aria-current="page"] { color:#0f172a !important; } nav.inav .hd-dd { position:relative; padding-bottom:8px; } nav.inav .hd-dd::after { content:""; position:absolute; top:100%; left:0; right:0; height:8px; z-index:99; } nav.inav .hd-dd > a { display:inline-block !important; padding:8px 0; } nav.inav .hd-menu { position:absolute; top:calc(100% + 8px); left:0; background:#fff; border:1px solid #e4e7ec; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,.1); opacity:0; visibility:hidden; transition:opacity .2s ease, visibility .2s ease; min-width:220px; z-index:100; pointer-events:none; } nav.inav .hd-dd:hover .hd-menu, nav.inav .hd-dd:focus-within .hd-menu { opacity:1; visibility:visible; pointer-events:auto; } nav.inav .hd-menu a { display:block !important; padding:12px 16px !important; font-size:14px !important; border-bottom:1px solid #e4e7ec; } nav.inav .hd-menu a:last-child { border-bottom:none; } nav.inav .hd-menu a:hover { background:#f6f7f9; } nav.inav a.hd-cta { background:#f97316 !important; color:#fff !important; padding:10px 24px !important; border-radius:6px !important; font-weight:700 !important; font-size:14px !important; text-decoration:none !important; white-space:nowrap; flex:none; } nav.inav a.hd-cta:hover { background:#ea580c !important; } @media (max-width:768px) { nav.inav .hd-wrap { flex-direction:column !important; height:auto !important; gap:16px !important; padding:16px 28px !important; } nav.inav .hd-links { flex-wrap:wrap; justify-content:center; gap:16px !important; } nav.inav a.hd-cta, nav.inav .hd-sobre { display:none !important; } }.hd-logo::before, footer.ifoot .ft-brand::before, header nav > a:first-child > span::before { content:""; display:inline-block; width:38px; height:10px; margin-right:10px; vertical-align:middle; background:radial-gradient(circle at 5px 5px,#ef4444 4.5px,transparent 5px) 0 0/10px 10px no-repeat,radial-gradient(circle at 5px 5px,#f59e0b 4.5px,transparent 5px) 14px 0/10px 10px no-repeat,radial-gradient(circle at 5px 5px,#22c55e 4.5px,transparent 5px) 28px 0/10px 10px no-repeat; } 
  footer.ifoot[data-site="footer"] { display:block !important; background:#fff !important; border-top:1px solid #e4e7ec !important; padding:48px 0 !important; margin:0 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; color:#0f172a; } footer.ifoot .ft-wrap { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; box-sizing:border-box; } footer.ifoot .ft-grid { display:grid !important; grid-template-columns:1.3fr 1.2fr 1fr 1fr; gap:32px; margin-bottom:32px; } footer.ifoot .ft-col h4 { font-size:14px !important; font-weight:700 !important; margin:0 0 16px !important; color:#0f172a !important; } footer.ifoot .ft-col a { display:block !important; font-size:13px !important; color:#5b6472 !important; line-height:1.8 !important; text-decoration:none !important; transition:color .2s; } footer.ifoot .ft-col a:hover { color:#0f172a !important; } footer.ifoot .ft-brand { font-weight:800; font-size:20px; margin-bottom:12px; font-family:Manrope,sans-serif; } footer.ifoot .ft-brand i { color:#f97316; font-style:normal; } footer.ifoot .ft-col p { font-size:13px !important; color:#5b6472 !important; line-height:1.6 !important; margin:0 !important; } footer.ifoot .ft-bottom { border-top:1px solid #e4e7ec; padding-top:24px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px 24px; font-size:12px; color:#8891a0; } footer.ifoot .ft-bottom p { margin:0 !important; font-size:12px !important; color:#8891a0 !important; } footer.ifoot .ft-legal { width:100%; margin-top:6px !important; font-size:11.5px !important; } @media (max-width:768px) { footer.ifoot .ft-grid { grid-template-columns:1fr; } }
  .hero { position:relative; overflow:hidden; background:radial-gradient(900px 420px at 85% 0%, rgba(99,102,241,.38), transparent 60%), radial-gradient(700px 420px at 0% 100%, rgba(249,115,22,.26), transparent 60%), #070b14 !important; color:#fff; } .hero::before { content:""; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px); background-size:46px 46px; mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); -webkit-mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); pointer-events:none; } .hero > * { position:relative; } .hero h1, .hero h1 * { color:#fff !important; } .hero .desc, .hero p.desc { color:rgba(226,232,240,.82) !important; } .hero .eyebrow { color:#fdba74; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; } .hero .slogan { color:#fdba74 !important; } .hero .btn-primary { background:#f97316 !important; border-color:#f97316 !important; color:#fff !important; box-shadow:0 10px 30px -12px rgba(249,115,22,.8); } .hero .btn-ghost { background:transparent !important; color:#fff !important; border-color:rgba(255,255,255,.3) !important; } .hero .hero-visual { background:rgba(255,255,255,.06) !important; border-color:rgba(255,255,255,.14) !important; box-shadow:0 30px 70px -30px rgba(99,102,241,.7); } .hero .status-pill { background:rgba(34,197,94,.16) !important; color:#86efac !important; } .chero { position:relative; overflow:hidden; background:radial-gradient(900px 420px at 85% 0%, rgba(99,102,241,.38), transparent 60%), radial-gradient(700px 420px at 0% 100%, rgba(249,115,22,.26), transparent 60%), #070b14 !important; color:#fff; } .chero::before { content:""; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px); background-size:46px 46px; mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); -webkit-mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); pointer-events:none; } .chero > * { position:relative; } .chero h1, .chero h1 * { color:#fff !important; } .chero .desc, .chero p.desc { color:rgba(226,232,240,.82) !important; } .chero .eyebrow { color:#fdba74; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; } .chero .slogan { color:#fdba74 !important; } .chero .btn-primary { background:#f97316 !important; border-color:#f97316 !important; color:#fff !important; box-shadow:0 10px 30px -12px rgba(249,115,22,.8); } .chero .btn-ghost { background:transparent !important; color:#fff !important; border-color:rgba(255,255,255,.3) !important; } .chero .chero-visual { background:rgba(255,255,255,.06) !important; border-color:rgba(255,255,255,.14) !important; box-shadow:0 30px 70px -30px rgba(99,102,241,.7); } .chero .status-pill { background:rgba(34,197,94,.16) !important; color:#86efac !important; } .chero p { color:rgba(226,232,240,.82) !important; }
</style>
<div class="tb" data-site="topbar"><div class="tb-in"><span class="tb-msg"><b>Fale com um especialista:</b> montamos a solução certa para a sua operação, sem compromisso. <a href="/contato.php">Falar com a equipe →</a></span><a class="tb-wa" href="https://wa.me/5519999332615" target="_blank" rel="noopener noreferrer">WhatsApp (19) 99933-2615</a></div></div><nav class="inav" data-site="nav" aria-label="Principal"><div class="hd-wrap"><a href="/" class="hd-logo">O<i>r</i>avel</a><div class="hd-links"><div class="hd-dd"><a href="/solucoes/" aria-haspopup="true">Soluções</a><div class="hd-menu"><a href="/erp-cmms/">ERP Locadoras</a><a href="/software-manutencao-industrial/">Manutenção</a><a href="/software-gestao-de-servicos/">Serviços</a><a href="/software-gestao-frota-logistica/">Logística</a><a href="/wms/">Estoque</a><a href="/crm/">Vendas</a><a href="/departamento-pessoal/">Departamento Pessoal</a></div></div><div class="hd-dd"><a href="/guindastes-e-plataformas/" aria-haspopup="true">Equipamentos</a><div class="hd-menu"><a href="/software-manutencao-guindastes/">Guindastes</a><a href="/software-manutencao-plataformas-elevatorias/">Plataformas elevatórias</a><a href="/nr-13-inspecao-equipamentos/">Inspeções NR-13</a><a href="/equipamento-devolvido-com-defeito/">Devolução com defeito</a></div></div><a href="/planos/">Planos</a><a href="/sobre/" class="hd-sobre">Sobre Nós</a><a href="/contato.php">Contato</a></div><a href="/contato.php" class="hd-cta">Falar com um Especialista</a></div></nav>

<main>
  <div class="wrap">
    <div class="proposta-grid">
      <div>
        <div class="eyebrow">Proposta sob medida</div>
        <h1 class="deck">Vamos entender <em>sua operação</em> e montar a proposta certa.</h1>
        <p class="lede">
          Preencha seus dados e nossa equipe entra em contato para uma conversa rápida sobre
          o seu parque de equipamentos, sua operação e os módulos mais adequados.
        </p>

        <ul class="value-list">
          <li>
            <span class="check">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            Sem custo e sem compromisso de assinatura
          </li>
          <li>
            <span class="check">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            Resposta da nossa equipe em até 1 dia útil
          </li>
          <li>
            <span class="check">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            Condição comercial ajustada ao tamanho real da sua operação
          </li>
          <li>
            <span class="check">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            Se o seu caso for mais simples que o esperado, dizemos isso também
          </li>
        </ul>

        <div class="proof">
          <div class="num">5+</div>
          <div class="txt">verticais de equipamento já em produção<br>(empilhadeiras, geradores, guindastes e mais)</div>
        </div>
      </div>

      <div class="form-card">
        <h2>Fale com a gente</h2>
        <p class="form-sub">Retornamos pelo e-mail ou telefone informado.</p>

        <div class="form-status" id="prop-form-status" role="status" aria-live="polite"></div>

        <form class="contact" id="prop-lead-form" action="/locadoras/enviar-lead.php" method="post" novalidate>
          <input type="text" name="site" class="form-hp" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true" />
          <div class="field-row">
            <div class="field">
              <label for="nome">Nome</label>
              <input id="nome" name="nome" type="text" placeholder="Seu nome" autocomplete="name" required />
            </div>
            <div class="field">
              <label for="empresa">Empresa</label>
              <input id="empresa" name="empresa" type="text" placeholder="Nome da empresa" autocomplete="organization" required />
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="email">E-mail</label>
              <input id="email" name="email" type="email" placeholder="voce@empresa.com.br" autocomplete="email" required />
            </div>
            <div class="field">
              <label for="telefone">Telefone</label>
              <input id="telefone" name="telefone" type="tel" placeholder="(00) 00000-0000" autocomplete="tel" required />
            </div>
          </div>
          <div class="field">
            <label for="frota">Tamanho do parque de equipamentos</label>
            <select id="frota" name="frota" required>
              <option value="" disabled selected>Selecione uma faixa</option>
              <option value="ate-15">Até 15 equipamentos</option>
              <option value="16-50">16 a 50 equipamentos</option>
              <option value="51-150">51 a 150 equipamentos</option>
              <option value="mais-150">Mais de 150 equipamentos</option>
            </select>
          </div>
          <input type="hidden" name="origem" value="proposta" />
          <button type="submit" class="btn btn-primary" id="prop-submit-btn">Solicitar proposta</button>
        </form>

        <p class="form-alt">
          Prefere e-mail? Escreva direto para
          <a href="mailto:contato@oravel.com.br">contato@oravel.com.br</a>
        </p>
      </div>
    </div>
  </div>
</main>

<style data-site="footer-css">footer.ifoot[data-site="footer"] { display:block !important; background:#fff !important; border-top:1px solid #e4e7ec !important; padding:48px 0 !important; margin:0 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; color:#0f172a; } footer.ifoot .ft-wrap { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; box-sizing:border-box; } footer.ifoot .ft-grid { display:grid !important; grid-template-columns:1.3fr 1.2fr 1fr 1fr; gap:32px; margin-bottom:32px; } footer.ifoot .ft-col h4 { font-size:14px !important; font-weight:700 !important; margin:0 0 16px !important; color:#0f172a !important; } footer.ifoot .ft-col a { display:block !important; font-size:13px !important; color:#5b6472 !important; line-height:1.8 !important; text-decoration:none !important; transition:color .2s; } footer.ifoot .ft-col a:hover { color:#0f172a !important; } footer.ifoot .ft-brand { font-weight:800; font-size:20px; margin-bottom:12px; font-family:Manrope,sans-serif; } footer.ifoot .ft-brand i { color:#f97316; font-style:normal; } footer.ifoot .ft-col p { font-size:13px !important; color:#5b6472 !important; line-height:1.6 !important; margin:0 !important; } footer.ifoot .ft-bottom { border-top:1px solid #e4e7ec; padding-top:24px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:6px 24px; font-size:12px; color:#8891a0; } footer.ifoot .ft-bottom p { margin:0 !important; font-size:12px !important; color:#8891a0 !important; } footer.ifoot .ft-legal { width:100%; margin-top:6px !important; font-size:11.5px !important; } @media (max-width:768px) { footer.ifoot .ft-grid { grid-template-columns:1fr; } }</style><footer class="ifoot" data-site="footer"><div class="ft-wrap"><div class="ft-grid"><div class="ft-col"><div class="ft-brand">O<i>r</i>avel</div><p>Plataforma de gestão com inteligência artificial para locação, manutenção, serviços, vendas (CRM), logística e estoque em todo o Brasil.</p></div><div class="ft-col"><h4>Soluções</h4><a href="/erp-cmms/">ERP Locadoras</a><a href="/software-manutencao-industrial/">Manutenção</a><a href="/software-gestao-de-servicos/">Serviços</a><a href="/software-gestao-frota-logistica/">Logística</a><a href="/wms/">Estoque</a><a href="/crm/">Vendas</a><a href="/departamento-pessoal/">Departamento Pessoal</a><a href="/software-manutencao-guindastes/">Guindastes</a><a href="/software-manutencao-plataformas-elevatorias/">Plataformas elevatórias</a><a href="/nr-13-inspecao-equipamentos/">Inspeções NR-13</a><a href="/equipamento-devolvido-com-defeito/">Devolução com defeito</a></div><div class="ft-col"><h4>Empresa</h4><a href="/planos/">Planos</a><a href="/inteligencia-artificial/">Inteligência artificial</a><a href="/academia/">Academia</a><a href="/sobre/">Sobre</a><a href="/contato.php">Contato</a></div><div class="ft-col"><h4>Legal</h4><a href="/politica-de-privacidade/">Privacidade</a><a href="/termos-de-uso/">Termos de Uso</a></div></div><div class="ft-bottom"><p>© 2026 Oravel Software. Todos os direitos reservados.</p><p class="ft-legal">Oravel Desenvolvimento de Software Ltda · CNPJ 65.707.953/0001-30 · Rua Pais Leme, 215, conj. 1713, Pinheiros, São Paulo/SP · CEP 05424-150</p></div></div></footer>

<script>
  (function () {
    var form = document.getElementById('prop-lead-form');
    if (!form) return;

    var statusBox = document.getElementById('prop-form-status');
    var submitBtn = document.getElementById('prop-submit-btn');

    function showStatus(kind, message) {
      statusBox.style.display = 'block';
      statusBox.style.background = kind === 'ok' ? 'rgba(44,154,127,0.12)' : 'rgba(255,106,26,0.1)';
      statusBox.style.border = '1px solid ' + (kind === 'ok' ? '#2c9a7f' : '#ff6a1a');
      statusBox.style.color = kind === 'ok' ? '#d6ece5' : '#ffd9bd';
      statusBox.textContent = message;
      statusBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (form.querySelector('[name="site"]').value.trim() !== '') {
        showStatus('ok', 'Obrigado! Em breve nossa equipe entra em contato.');
        form.reset();
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Enviando...';

      var formData = new FormData(form);

      fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) {
          if (!res.ok) throw new Error('http-' + res.status);
          return res.json();
        })
        .then(function (data) {
          if (data && data.ok) {
            showStatus('ok', 'Recebemos seus dados. Nossa equipe entra em contato pelo e-mail ou telefone informado para marcar a conversa.');
            form.reset();
          } else {
            throw new Error((data && data.error) || 'unknown');
          }
        })
        .catch(function () {
          showStatus('err', 'Não deu pra enviar agora. Tenta de novo ou escreve direto pra contato@oravel.com.br.');
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Solicitar proposta';
        });
    });
  })();
</script>

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
