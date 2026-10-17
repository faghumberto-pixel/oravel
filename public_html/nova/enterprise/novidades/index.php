<?php
header('Content-Type: text/html; charset=utf-8');
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
<meta charset="utf-8" />
<title>Novidades | Oravel Enterprise</title>
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

  /* destaque — orçamento */
  .feature { display: grid; grid-template-columns: 1fr 1fr; gap: 44px; align-items: center; margin-bottom: 64px; }
  @media (max-width: 860px) { .feature { grid-template-columns: 1fr; gap: 28px; } }
  .feature .tag { font-family: "SF Mono", ui-monospace, monospace; font-size: 11.5px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent); margin-bottom: 14px; }
  .feature h2 { font-size: clamp(22px, 2.8vw, 30px); margin: 0 0 14px; line-height: 1.2; }
  .feature p.desc { font-size: 15.5px; color: var(--fg-dim); line-height: 1.65; margin: 0 0 20px; }
  .feature .items { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
  .feature .items li { display: flex; gap: 10px; font-size: 14px; color: var(--fg-dim); line-height: 1.55; }
  .feature .items li::before { content: ""; width: 6px; height: 6px; background: var(--petrol-bright); margin-top: 7px; flex: none; border-radius: 1px; }

  .device-frame { border-radius: 6px; overflow: hidden; border: 1px solid var(--border); }

  .subhead { max-width: 640px; margin: 0 0 40px; }
  .subhead .eyebrow { color: var(--accent); margin-bottom: 12px; }
  .subhead h2 { font-size: clamp(22px, 2.8vw, 28px); margin: 0; line-height: 1.2; }

  .newsgrid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 60px; }
  @media (max-width: 760px) { .newsgrid { grid-template-columns: 1fr; } }
  .newscard { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 24px 24px 26px; }
  .newscard .tag { font-family: "SF Mono", ui-monospace, monospace; font-size: 11.5px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent); margin-bottom: 12px; }
  .newscard h3 { font-size: 16.5px; margin: 0 0 10px; line-height: 1.3; }
  .newscard p { font-size: 14px; color: var(--fg-dim); margin: 0; line-height: 1.6; }

  .secbanner { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; padding: 28px 30px; display: flex; align-items: center; gap: 24px; }
  @media (max-width: 600px) { .secbanner { flex-direction: column; align-items: flex-start; } }
  .secbanner .tag { font-family: "SF Mono", ui-monospace, monospace; font-size: 11.5px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--petrol-bright); margin-bottom: 10px; }
  .secbanner h3 { font-size: 16.5px; margin: 0 0 8px; }
  .secbanner p { font-size: 14px; color: var(--fg-dim); margin: 0; line-height: 1.55; }

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
  nav.inav[data-site="nav"] { position:sticky !important; top:0; z-index:50; display:block !important; background:rgba(255,255,255,.96) !important; backdrop-filter:blur(10px); border-bottom:1px solid #e4e7ec !important; padding:0 !important; margin:0 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; } nav.inav .hd-wrap { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; height:72px !important; display:flex !important; flex-direction:row !important; align-items:center !important; justify-content:space-between !important; gap:24px !important; box-sizing:border-box; } nav.inav .hd-logo { text-decoration:none !important; color:#0f172a !important; font-size:22px !important; font-weight:900 !important; font-family:Manrope,sans-serif !important; letter-spacing:-.01em; flex:none; } nav.inav .hd-logo i { color:#f97316 !important; font-style:normal !important; } nav.inav .hd-links { display:flex !important; align-items:center !important; gap:32px !important; } nav.inav .hd-links a { color:#5b6472 !important; font-size:14px !important; font-weight:600 !important; text-decoration:none !important; transition:color .2s; } nav.inav .hd-links a:hover, nav.inav .hd-links a[aria-current="page"] { color:#0f172a !important; } nav.inav .hd-dd { position:relative; padding-bottom:8px; } nav.inav .hd-dd::after { content:""; position:absolute; top:100%; left:0; right:0; height:8px; z-index:99; } nav.inav .hd-dd > a { display:inline-block !important; padding:8px 0; } nav.inav .hd-menu { position:absolute; top:calc(100% + 8px); left:0; background:#fff; border:1px solid #e4e7ec; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,.1); opacity:0; visibility:hidden; transition:opacity .2s ease, visibility .2s ease; min-width:220px; z-index:100; pointer-events:none; } nav.inav .hd-dd:hover .hd-menu, nav.inav .hd-dd:focus-within .hd-menu { opacity:1; visibility:visible; pointer-events:auto; } nav.inav .hd-menu a { display:block !important; padding:12px 16px !important; font-size:14px !important; border-bottom:1px solid #e4e7ec; } nav.inav .hd-menu a:last-child { border-bottom:none; } nav.inav .hd-menu a:hover { background:#f6f7f9; } nav.inav a.hd-cta { background:#f97316 !important; color:#fff !important; padding:10px 24px !important; border-radius:6px !important; font-weight:700 !important; font-size:14px !important; text-decoration:none !important; white-space:nowrap; flex:none; } nav.inav a.hd-cta:hover { background:#ea580c !important; } @media (max-width:768px) { nav.inav .hd-wrap { flex-direction:column !important; height:auto !important; gap:16px !important; padding:16px 28px !important; } nav.inav .hd-links { flex-wrap:wrap; justify-content:center; gap:16px !important; } nav.inav a.hd-cta, nav.inav .hd-sobre { display:none !important; } }
  .hero { position:relative; overflow:hidden; background:radial-gradient(900px 420px at 85% 0%, rgba(99,102,241,.38), transparent 60%), radial-gradient(700px 420px at 0% 100%, rgba(249,115,22,.26), transparent 60%), #070b14 !important; color:#fff; } .hero::before { content:""; position:absolute; inset:0; background-image:linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px); background-size:46px 46px; mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); -webkit-mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); pointer-events:none; } .hero > * { position:relative; } .hero h1, .hero h1 * { color:#fff !important; } .hero .desc, .hero p.desc { color:rgba(226,232,240,.82) !important; } .hero .eyebrow { color:#fdba74; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; } .hero .slogan { color:#fdba74 !important; } .hero .btn-primary { background:#f97316 !important; border-color:#f97316 !important; color:#fff !important; box-shadow:0 10px 30px -12px rgba(249,115,22,.8); } .hero .btn-ghost { background:transparent !important; color:#fff !important; border-color:rgba(255,255,255,.3) !important; } .hero .hero-visual { background:rgba(255,255,255,.06) !important; border-color:rgba(255,255,255,.14) !important; box-shadow:0 30px 70px -30px rgba(99,102,241,.7); } .hero .status-pill { background:rgba(34,197,94,.16) !important; color:#86efac !important; }
</style>
<div class="tb" data-site="topbar"><div class="tb-in"><span class="tb-msg"><b>Contrate online:</b> no cartão recorrente você paga menos que o valor cheio. Sem compromisso, no boleto ou Pix. <a href="/planos/">Veja os planos →</a></span><a class="tb-wa" href="https://wa.me/5519999332615" target="_blank" rel="noopener noreferrer">WhatsApp (19) 99933-2615</a></div></div><nav class="inav" data-site="nav" aria-label="Principal"><div class="hd-wrap"><a href="/" class="hd-logo">O<i>r</i>avel</a><div class="hd-links"><div class="hd-dd"><a href="/solucoes/" aria-haspopup="true">Soluções</a><div class="hd-menu"><a href="/erp-cmms/">ERP Locadoras</a><a href="/software-manutencao-industrial/">Manutenção</a><a href="/software-gestao-de-servicos/">Serviços</a><a href="/software-gestao-frota-logistica/">Logística</a><a href="/wms/">Estoque</a><a href="/crm/">Vendas</a><a href="/departamento-pessoal/">Departamento Pessoal</a></div></div><a href="/planos/">Planos</a><a href="/sobre/" class="hd-sobre">Sobre Nós</a><a href="/contato.php">Contato</a></div><a href="/contato.php" class="hd-cta">Falar com um Especialista</a></div></nav>

<header class="hero">
  <div class="wrap-narrow">
    <div class="hero-eyebrow eyebrow">Atualização da plataforma</div>
    <h1 class="deck">Orçamento que se aprova sozinho.</h1>
    <p class="lede">Orçamento, avaria e cobrança agora conversam entre si. O que o técnico registra no pátio vira orçamento; o que o cliente aprova vira conta a receber — sozinho.</p>
  </div>
</header>

<section class="section section-bone">
  <div class="wrap">

    <div class="feature">
      <div>
        <div class="tag">Novo módulo</div>
        <h2 class="deck">Orçamento que se aprova sozinho</h2>
        <p class="desc">Chega de planilha solta e PDF montado na mão. Monte o orçamento com peças do almoxarifado e serviços direto na Ordem de Serviço ou na avaria, gere o PDF na hora e envie por e-mail com um link de aprovação. O cliente aprova ou reprova pela internet — sem imprimir, sem assinar papel, sem esperar retornar ao pátio.</p>
        <ul class="items">
          <li>Orçamento a terceiro guarda o laudo técnico prévio junto, pronto para consulta</li>
          <li>A causa da avaria — desgaste natural, mau uso ou dano do cliente — já direciona se vira cobrança</li>
          <li>Aprovação do cliente vira conta a receber sozinha, com valor e vencimento prontos</li>
        </ul>
      </div>
      <div class="device-frame">
        <svg viewBox="0 0 460 260" role="img" aria-label="Tela do orçamento aprovado pelo cliente, com o fluxo desde a avaria até a aprovação">
          <rect width="460" height="260" fill="#121815"/>
          <rect x="16" y="16" width="180" height="24" rx="3" fill="#123028" stroke="#2f9a7f"/>
          <text x="26" y="32" fill="#6fd6b6" font-family="ui-monospace,monospace" font-size="9">Aprovado pelo cliente</text>
          <text x="16" y="66" fill="#8a8f87" font-family="ui-monospace,monospace" font-size="9">CLIENTE</text>
          <text x="16" y="80" fill="#f4f2ea" font-family="ui-monospace,monospace" font-size="11">Construtora Vega</text>
          <text x="240" y="66" fill="#8a8f87" font-family="ui-monospace,monospace" font-size="9">VALOR</text>
          <text x="240" y="80" fill="#f4f2ea" font-family="ui-monospace,monospace" font-size="11">R$ 3.240,00</text>
          <text x="16" y="104" fill="#8a8f87" font-family="ui-monospace,monospace" font-size="9">VENCIMENTO</text>
          <text x="16" y="118" fill="#f4f2ea" font-family="ui-monospace,monospace" font-size="11">10/08/2026</text>

          <rect x="16" y="140" width="428" height="100" rx="4" fill="#0e1512" stroke="#2a332c"/>
          <text x="26" y="158" fill="#8a8f87" font-family="ui-monospace,monospace" font-size="8">FLUXO</text>
          <rect x="26" y="168" width="408" height="16" rx="2" fill="#1c231f"/>
          <text x="32" y="179" fill="#d9d4c4" font-family="ui-monospace,monospace" font-size="8">Avaria constatada</text>
          <rect x="26" y="190" width="330" height="16" rx="2" fill="#1c231f"/>
          <text x="32" y="201" fill="#d9d4c4" font-family="ui-monospace,monospace" font-size="8">Causa classificada</text>
          <rect x="26" y="212" width="245" height="16" rx="2" fill="#2c9a7f"/>
          <text x="32" y="223" fill="#0a1512" font-family="ui-monospace,monospace" font-size="8" font-weight="bold">Cliente aprova</text>
        </svg>
      </div>
    </div>

    <div class="subhead">
      <div class="eyebrow">O que mais mudou</div>
      <h2 class="deck">Mais controle, menos retrabalho</h2>
    </div>

    <div class="newsgrid">
      <article class="newscard">
        <div class="tag">Logística</div>
        <h3>Todo equipamento, rastreado do início ao fim</h3>
        <p>Motoristas e movimentação de equipamento sob controle. A chegada no pátio já nasce documentada com laudo de recebimento — a prova de qualquer avaria fica registrada no exato momento em que ela acontece.</p>
      </article>
      <article class="newscard">
        <div class="tag">Suprimentos</div>
        <h3>Requisição vira compra sem planilha paralela</h3>
        <p>Requisição, cotação, ordem de compra e recebimento — tudo dentro do sistema, com aprovação em cada etapa. Ninguém mais controla por fora o que a oficina precisa comprar.</p>
      </article>
      <article class="newscard">
        <div class="tag">Ativos</div>
        <h3>Saiba onde cada ativo está, antes que ele quebre</h3>
        <p>Mapa de equipamentos, alertas automáticos de manutenção preventiva e gestão organizada por número de patrimônio. Exporte os dados quando precisar prestar contas.</p>
      </article>
      <article class="newscard">
        <div class="tag">Comercial</div>
        <h3>O funil de vendas mora dentro da Oravel</h3>
        <p>Gerencie prospects e acompanhe o funil comercial no mesmo lugar onde já roda a operação. O mapa comercial junta clientes ativos e leads por região.</p>
      </article>
    </div>

    <div class="secbanner">
      <div>
        <div class="tag">Segurança</div>
        <h3>Autenticação em duas etapas</h3>
        <p>Uma camada extra de proteção para as contas de administração da plataforma — segurança que não depende só de senha.</p>
      </div>
    </div>

  </div>
</section>

<section class="final-cta">
  <div class="wrap">
    <h2 class="deck">Quer ver o orçamento rodando na prática?</h2>
    <p>Agendamos uma avaliação com os dados da sua operação, sem compromisso.</p>
    <a href="../#agendar" class="btn btn-primary">Agendar avaliação</a>
  </div>
</section>

<footer class="site">
  <div class="wrap">
    <span>© 2026 Oravel — gestão de equipamentos e manutenção.</span>
    <span>oravel.com.br/nova/enterprise/novidades</span>
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
