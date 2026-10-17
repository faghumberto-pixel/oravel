<?php
header('Content-Type: text/html; charset=utf-8');
$status = $_GET['status'] ?? null; // 'success' | 'error' | null — setado por contato.php após o envio
?>
<meta charset="utf-8" />
<title>ERP CMMS Oravel Enterprise — avaliação para operações de grande porte</title>
<link rel="icon" type="image/png" sizes="32x32" href="/nova/assets/favicon-32.png?v=4" />
<link rel="apple-touch-icon" href="/nova/assets/apple-touch-icon.png?v=4" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  @font-face {
    font-family: "Oravel Deck";
    src: local("Archivo Black");
    font-weight: 900;
  }

  :root {
    --ink: #0a0e17;
    --ink-2: #121826;
    --bone: #f5f2ec;
    --bone-dim: #e8e4d6;
    --steel: #d9d4c4;
    --olive: #8a8f87;
    --olive-dim: #635c4d;
    --petrol: #0f172a;
    --petrol-bright: #223252;
    --orange: #f97316;
    --orange-dim: #9a3412;

    --bg: var(--bone);
    --bg-raised: #ffffff;
    --fg: var(--ink);
    --fg-dim: var(--olive-dim);
    --border: #d3cfc0;
    --card-bg: #ffffff;
    --accent: var(--orange);
    --accent-ink: #ffffff;
    color-scheme: light;
  }
  @media (prefers-color-scheme: dark) {
    :root {
      --bg: #0a0e17; --bg-raised: var(--ink-2); --fg: var(--bone);
      --fg-dim: #b3ab97; --border: #263029; --card-bg: var(--ink-2);
      --accent-ink: #1a0d05; color-scheme: dark;
    }
  }
  :root[data-theme="dark"] {
    --bg: #0a0e17; --bg-raised: var(--ink-2); --fg: var(--bone);
    --fg-dim: #b3ab97; --border: #263029; --card-bg: var(--ink-2);
    --accent-ink: #1a0d05; color-scheme: dark;
  }
  :root[data-theme="light"] {
    --bg: var(--bone); --bg-raised: #ffffff; --fg: var(--ink);
    --fg-dim: var(--olive-dim); --border: #d3cfc0; --card-bg: #ffffff;
    --accent-ink: #ffffff; color-scheme: light;
  }

  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: var(--bg); color: var(--fg); overflow-x: hidden; }
  body {
    font-family: ui-sans-serif, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: 16px; line-height: 1.6; -webkit-font-smoothing: antialiased;
  }

  /* Dificulta cópia casual (clique-direito, seleção, arrastar imagem) --
     NÃO impede print screen do sistema operacional, foto de outra câmera,
     nem alguém com DevTools/Leitor de página aberta. Ver aviso completo
     no bloco de script no fim do arquivo. Formulários continuam
     selecionáveis (usuário precisa digitar/revisar o que preencheu). */
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

  .deck {
    font-family: "Archivo Black", "Arial Narrow", "Oswald", sans-serif;
    font-weight: 900; text-transform: uppercase; letter-spacing: -0.01em; text-wrap: balance;
  }
  .mono { font-family: "SF Mono", "JetBrains Mono", ui-monospace, Menlo, Consolas, monospace; font-variant-numeric: tabular-nums; }
  a { color: inherit; }
  img, svg { max-width: 100%; display: block; }
  .wrap { max-width: 1180px; margin: 0 auto; padding: 0 32px; }
  .wrap-narrow { max-width: 860px; margin: 0 auto; padding: 0 32px; }
  .eyebrow {
    font-family: "SF Mono", ui-monospace, monospace; text-transform: uppercase;
    letter-spacing: 0.14em; font-size: 12px; font-weight: 600;
  }
  section { position: relative; }
  a:focus-visible, button:focus-visible, input:focus-visible, textarea:focus-visible {
    outline: 2px solid var(--orange); outline-offset: 3px;
  }
  @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }

  /* NAV */
  .nav {
    position: sticky; top: 0; z-index: 50;
    background: color-mix(in srgb, #0f172a 92%, transparent);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(217, 212, 196, 0.14);
  }
  .nav .wrap { display: flex; align-items: center; justify-content: space-between; height: 68px; }
  .nav-brand { display: flex; align-items: center; gap: 10px; color: var(--bone); font-family: "Archivo Black", sans-serif; font-size: 18px; }
  .nav-brand .tag { font-family: "SF Mono", ui-monospace, monospace; font-size: 11px; letter-spacing: 0.08em; color: #7dd3fc; text-transform: none; font-weight: 400; margin-left: 4px; }
  .nav-mark { height: 32px; width: auto; flex: none; }
  .nav-back { color: #c9c5b6; font-size: 14px; text-decoration: none; display:flex; align-items:center; gap:6px; }
  .nav-back:hover { color: var(--bone); }

  /* HERO */
  .hero {
    background: var(--ink); color: var(--bone);
    padding: 76px 0 68px;
    border-bottom: 1px solid rgba(244,242,234,0.1);
    position: relative;
    overflow: hidden;
  }
  .hero-plate-placeholder {
    position: absolute;
    inset: 0;
    z-index: 0;
    background:
      repeating-linear-gradient(
        45deg,
        #201a12,
        #201a12 22px,
        #2b2418 22px,
        #2b2418 44px
      );
  }
  .hero-vignette {
    position: absolute;
    inset: 0;
    z-index: 1;
    background:
      linear-gradient(180deg, rgba(11,15,13,0.35) 0%, rgba(11,15,13,0.55) 45%, var(--ink) 92%),
      linear-gradient(90deg, rgba(11,15,13,0.85) 0%, rgba(11,15,13,0.25) 46%, rgba(11,15,13,0.15) 62%, rgba(11,15,13,0.85) 100%);
  }
  .hero .wrap-narrow { position: relative; z-index: 2; text-align: left; }
  .hero-eyebrow { color: var(--orange); display: flex; align-items: center; gap: 10px; margin-bottom: 22px; }
  .hero-eyebrow::before { content: ""; width: 26px; height: 2px; background: var(--orange); }
  .hero h1 { font-size: clamp(30px, 4vw, 46px); line-height: 1.08; margin: 0 0 20px; max-width: 20ch; }
  .hero h1 em { font-style: normal; color: var(--orange); }
  .hero p.lede { font-size: 17.5px; color: #d6d2c4; max-width: 62ch; margin: 0 0 32px; line-height: 1.65; }
  .hero-facts {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px;
    background: rgba(244,242,234,0.14); border: 1px solid rgba(244,242,234,0.14);
    border-radius: 6px; overflow: hidden; margin-top: 8px;
  }
  @media (max-width: 700px) { .hero-facts { grid-template-columns: 1fr; } }
  .hero-fact { background: #10160f; padding: 20px 22px; }
  .hero-fact .k { font-size: 12.5px; color: #9aa39c; font-family: "SF Mono", ui-monospace, monospace; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
  .hero-fact .v { font-size: 15px; color: var(--bone); line-height: 1.45; }

  /* ESCOPO — ERP vs FSM/CMMS */
  .scope-intro { max-width: 760px; margin: 0 0 40px; }
  .scope-intro p { font-size: 16.5px; color: var(--fg-dim); line-height: 1.65; margin: 0; }
  .scope-intro strong { color: var(--fg); font-weight: 600; }
  .scopegrid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  @media (max-width: 760px) { .scopegrid { grid-template-columns: 1fr; } }
  .scopecard {
    background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px;
    padding: 30px 30px 32px; display: flex; flex-direction: column;
  }
  .scopecard .scope-icon { width: 34px; height: 34px; margin-bottom: 18px; color: var(--petrol-bright); }
  .scopecard.orange .scope-icon { color: var(--accent); }
  .scopecard .scope-kicker {
    font-family: "SF Mono", ui-monospace, monospace; font-size: 11.5px; letter-spacing: 0.08em;
    text-transform: uppercase; color: var(--fg-dim); margin-bottom: 8px;
  }
  .scopecard h3 { font-size: 19px; margin: 0 0 12px; line-height: 1.3; }
  .scopecard p.scope-desc { font-size: 14.5px; color: var(--fg-dim); margin: 0 0 18px; line-height: 1.6; }
  .scopecard .scope-items { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 9px; border-top: 1px solid var(--border); padding-top: 16px; }
  .scopecard .scope-items li { display: flex; gap: 9px; font-size: 13.5px; color: var(--fg-dim); line-height: 1.5; }
  .scopecard.petrol .scope-items li::before { content: ""; width: 5px; height: 5px; background: var(--petrol-bright); margin-top: 7px; flex: none; border-radius: 50%; }
  .scopecard.orange .scope-items li::before { content: ""; width: 5px; height: 5px; background: var(--accent); margin-top: 7px; flex: none; border-radius: 50%; }

  /* SECTIONS */
  .section { padding: 78px 0; }
  .section-bone { background: var(--bg); color: var(--fg); }
  .section-head { max-width: 640px; margin: 0 0 46px; }
  .section-head .eyebrow { color: var(--accent); margin-bottom: 14px; }
  .section-head h2 { font-size: clamp(24px, 3vw, 34px); line-height: 1.15; margin: 0 0 14px; }
  .section-head p { font-size: 16px; color: var(--fg-dim); margin: 0; line-height: 1.6; }

  /* module grid — full catalog, denser than the landing's 3 pillars */
  .modgrid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
  }
  @media (max-width: 900px) { .modgrid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 600px) { .modgrid { grid-template-columns: 1fr; } }
  .modcard {
    background: rgba(244,242,234,0.06); border: 1px solid rgba(244,242,234,0.14); border-radius: 6px;
    padding: 22px 22px 24px;
  }
  .modcard .icon { width: 30px; height: 30px; margin-bottom: 14px; color: #7dd3fc; }
  .modcard h3 { font-size: 15.5px; margin: 0 0 8px; color: var(--bone); }
  .modcard p { font-size: 13.5px; color: #d6d2c4; margin: 0; line-height: 1.55; }

  /* ---- verticais prontos (links diretos pros subdominios) ---- */
  .vertgrid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px;
  }
  @media (max-width: 900px) { .vertgrid { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 600px) { .vertgrid { grid-template-columns: 1fr; } }
  a.vertcard {
    display: block; background: var(--card-bg); border: 2px solid var(--accent);
    border-radius: 8px; padding: 26px 24px 24px; text-decoration: none; color: inherit;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
  }
  a.vertcard:hover { transform: translateY(-2px); box-shadow: 0 10px 30px -12px rgba(0,0,0,0.35); }
  a.vertcard:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
  .vertcard .vert-emoji { font-size: 26px; display: block; margin-bottom: 12px; }
  .vertcard h3 { font-size: 17px; margin: 0 0 8px; color: var(--fg); }
  .vertcard p { font-size: 13.5px; color: var(--fg-dim); margin: 0 0 16px; line-height: 1.5; }
  .vertcard .vert-domain {
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 12px; color: var(--accent);
    display: flex; align-items: center; gap: 6px; font-weight: 600;
  }

  /* diferenciais / petrol band */
  .section-petrol { background: #0f172a; color: var(--bone); }
  :root[data-theme="dark"] .section-petrol { background: #0c1424; }
  @media (prefers-color-scheme: dark) { .section-petrol { background: #0c1424; } }
  :root[data-theme="light"] .section-petrol { background: #0f172a; }
  .section-petrol .section-head .eyebrow { color: #7dd3fc; }
  .section-petrol .section-head p { color: #b0c4de; }

  .diflist { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px 40px; }
  @media (max-width: 760px) { .diflist { grid-template-columns: 1fr; } }
  .difitem { display: flex; gap: 16px; }
  .difitem .num { font-family: "SF Mono", ui-monospace, monospace; font-size: 13px; color: var(--orange); flex: none; padding-top: 3px; }
  .difitem h3 { font-size: 16.5px; margin: 0 0 6px; color: var(--bone); }
  .difitem p { font-size: 14px; color: #b0c4de; margin: 0; line-height: 1.6; }

  /* process / how the evaluation works */
  .steps { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; }
  @media (max-width: 900px) { .steps { grid-template-columns: repeat(2, 1fr); } }
  @media (max-width: 560px) { .steps { grid-template-columns: 1fr; } }
  .step {
    border: 1px solid var(--border); border-radius: 6px; padding: 20px 18px;
    background: var(--card-bg);
  }
  .step .idx { font-family: "SF Mono", ui-monospace, monospace; font-size: 12px; color: var(--accent); margin-bottom: 10px; }
  .step h3 { font-size: 14.5px; margin: 0 0 6px; }
  .step p { font-size: 13px; color: var(--fg-dim); margin: 0; line-height: 1.5; }

  /* FORM */
  .form-section { background: #0f172a; color: var(--bone); padding: 84px 0; }
  .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: start; }
  @media (max-width: 860px) { .form-grid { grid-template-columns: 1fr; gap: 40px; } }
  .form-grid h2 { font-size: clamp(26px, 3.4vw, 36px); line-height: 1.15; margin: 0 0 18px; max-width: 16ch; }
  .form-grid > div:first-child p { color: #c9c5b6; font-size: 15.5px; line-height: 1.65; margin: 0 0 28px; max-width: 46ch; }
  .form-note-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
  .form-note-list li { display: flex; gap: 10px; font-size: 14px; color: #d6d2c4; }
  .form-note-list li::before { content: ""; width: 6px; height: 6px; background: var(--orange); margin-top: 7px; flex: none; border-radius: 1px; }

  form.contact { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 7px; }
  .field label { font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #9aa39c; font-family: "SF Mono", ui-monospace, monospace; }
  .field input, .field select, .field textarea {
    background: #10160f; border: 1px solid #2a332c; color: var(--bone);
    border-radius: 4px; padding: 12px 14px; font-size: 15px; font-family: inherit;
  }
  .field input::placeholder, .field textarea::placeholder { color: #5c665e; }
  .field textarea { resize: vertical; min-height: 90px; }
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  @media (max-width: 480px) { .field-row { grid-template-columns: 1fr; } }

  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    padding: 15px 26px; border-radius: 3px; font-weight: 700; font-size: 15.5px;
    text-decoration: none; border: 1px solid transparent; cursor: pointer;
    transition: transform 0.15s ease, background 0.15s ease;
  }
  .btn:hover { transform: translateY(-1px); }
  .btn-primary { background: var(--orange); color: #1a0d05; }
  .btn-primary:hover { background: #ff7d38; }
  form.contact .btn { margin-top: 6px; }
  .form-confirm {
    display: none; border-radius: 6px;
    padding: 18px 20px; font-size: 14.5px; margin-bottom: 20px;
  }
  .form-confirm.visible { display: block; }
  .form-status-success { background: #123028; border: 1px solid #2f9a7f; color: #d6ece5; }
  .form-status-error { background: #2a1a10; border: 1px solid #ff6a1a; color: #ffd9bd; }

  footer.site {
    background: var(--ink); border-top: 1px solid rgba(244,242,234,0.12);
    padding: 32px 0; color: #8a8577; font-size: 13px;
  }
  footer.site .wrap { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
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

<!-- ============================================================ HERO -->
<header class="hero">
  <div class="hero-plate-placeholder" role="presentation" aria-hidden="true"></div>
  <div class="hero-vignette" aria-hidden="true"></div>
  <div class="wrap-narrow">
    <div class="hero-eyebrow eyebrow">ERP CMMS Oravel — Avaliação sob medida</div>
    <h1 class="deck">Controle centralizado para grandes operações e operações <em>multifilial.</em></h1>
    <p class="lede">
      Se a sua empresa opera em larga escala, precisa de integrações personalizadas ou possui múltiplos
      pátios, nosso time de engenharia cria uma arquitetura exclusiva do <strong>ERP CMMS Oravel</strong> para o seu negócio.
    </p>

    <div class="hero-facts">
      <div class="hero-fact">
        <div class="k">Para quem é</div>
        <div class="v">Operações de grande porte, múltiplos pátios ou grupos com mais de uma empresa operando no mesmo sistema.</div>
      </div>
      <div class="hero-fact">
        <div class="k">O que muda</div>
        <div class="v">Limites abertos, condições comerciais próprias e prioridade de atendimento — definidos caso a caso.</div>
      </div>
      <div class="hero-fact">
        <div class="k">Como começa</div>
        <div class="v">Uma avaliação guiada da sua operação atual, sem custo, para desenhar a configuração certa.</div>
      </div>
    </div>
  </div>
</header>

<!-- ============================================================ ESCOPO — ERP + FSM/CMMS -->
<section class="section section-bone">
  <div class="wrap">
    <div class="scope-intro">
      <p>O <strong>ERP CMMS Oravel</strong>, desenvolvido pela Oravel, abrange tanto a parte de <strong>Gestão (ERP)</strong> — cuidando do financeiro, faturamento e contratos —
      quanto a <strong>FSM (Field Service Management)</strong> — focada na gestão de equipes em campo, controle de
      horímetros, manutenções preventivas e Ordens de Serviço (CMMS) para técnicos via celular.</p>
    </div>

    <div class="scopegrid">
      <article class="scopecard petrol">
        <svg class="scope-icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="6" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 10h18" stroke="currentColor" stroke-width="1.6"/><path d="M8 4v4M16 4v4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <div class="scope-kicker">Gestão · ERP</div>
        <h3>O escritório: financeiro e contratos sob controle</h3>
        <p class="scope-desc">A camada administrativa que sustenta o negócio — o que entra, o que sai e o que está contratado.</p>
        <ul class="scope-items">
          <li>Contas a pagar e a receber ligadas à operação real</li>
          <li>Faturamento e franquia de horas por contrato</li>
          <li>Contratos de locação, renovação e condições comerciais</li>
          <li>Relatórios financeiros por ativo, cliente ou período</li>
        </ul>
      </article>

      <article class="scopecard orange">
        <svg class="scope-icon" viewBox="0 0 24 24" fill="none"><rect x="6" y="2" width="12" height="20" rx="2" stroke="currentColor" stroke-width="1.6"/><line x1="10" y1="19" x2="14" y2="19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <div class="scope-kicker">Campo · FSM / CMMS</div>
        <h3>O campo: equipe, horímetro e manutenção em tempo real</h3>
        <p class="scope-desc">A camada operacional que acompanha o equipamento e o técnico onde o trabalho realmente acontece.</p>
        <ul class="scope-items">
          <li>Gestão de equipes em campo, por técnico e por chamado</li>
          <li>Controle de horímetro com alertas de manutenção preventiva</li>
          <li>Ordens de Serviço (CMMS) abertas e concluídas pelo celular</li>
          <li>Checklist, foto e assinatura digital direto no pátio</li>
        </ul>
      </article>
    </div>
  </div>
</section>

<!-- ============================================================ CATALOGO COMPLETO -->
<section class="section section-petrol">
  <div class="wrap">
    <div class="section-head">
      <div class="eyebrow">O que o ERP CMMS Oravel oferece</div>
      <h2 class="deck">Todos os módulos, não só os três principais</h2>
      <p>Os planos de entrada já cobrem Ordens de Serviço, horímetro e app do técnico. Na configuração Enterprise do ERP CMMS Oravel você também tem acesso a:</p>
    </div>

    <p style="margin: -30px 0 46px; font-size: 14.5px; color: #b0c4de;">
      Essa é uma seleção. <a href="solucoes/" style="color: var(--orange); text-decoration: underline;">Veja o catálogo completo de módulos →</a>
    </p>

    <div class="modgrid">
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="4" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 9h18" stroke="currentColor" stroke-width="1.6"/><path d="M8 14h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <h3>Ordens de Serviço</h3>
        <p>Chamado, diagnóstico, peças e conclusão em uma única tela, com histórico completo por ativo.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <h3>Prevenção por horímetro</h3>
        <p>Alertas automáticos de revisão por hora rodada, não por data — evita quebra em campo.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><rect x="6" y="2" width="12" height="20" rx="2" stroke="currentColor" stroke-width="1.6"/><line x1="10" y1="19" x2="14" y2="19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <h3>App do técnico</h3>
        <p>Checklist, foto obrigatória e horímetro lançados em campo, mesmo com sinal fraco no pátio.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 20V10l8-6 8 6v10" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 20v-6h6v6" stroke="currentColor" stroke-width="1.6"/></svg>
        <h3>Contratos e franquia de horas</h3>
        <p>Locação com controle de excedente, renovação e faturamento vinculado ao contrato certo.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><rect x="3" y="7" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 11h18" stroke="currentColor" stroke-width="1.6"/><circle cx="8" cy="15" r="1.4" fill="currentColor"/></svg>
        <h3>Contas a pagar e a receber</h3>
        <p>Financeiro ligado à operação — custo real de manutenção por ativo, cliente ou contrato.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 4v16h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M8 15l3-4 3 3 4-6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h3>Relatórios gerenciais</h3>
        <p>Custo, disponibilidade e histórico prontos para decisão, sem exportar planilha.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M3 12h5l2-7 4 14 2-7h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h3>Mobilização e desmobilização</h3>
        <p>Entrada e saída de equipamento do pátio rastreada por QR code, com laudo de recebimento.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4L12 2z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
        <h3>Oravel Academy</h3>
        <p>Trilha de treinamento para gestores e técnicos aprenderem o sistema no próprio ritmo da equipe.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 4h16v12H8l-4 4V4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        <h3>Área do Cliente</h3>
        <p>Portal próprio para abertura e acompanhamento de chamados, fora do fluxo interno da equipe.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4v5c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V7l8-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h3>Central de Inteligência Artificial</h3>
        <p>Análises automáticas que apoiam a gestão de manutenções, equipe e clientes com recomendações práticas.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M4 7h9l-3-3M20 17h-9l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 7v6h16V7" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        <h3>Módulo de Trocas</h3>
        <p>Rastreamento completo das trocas de equipamento, com motivo registrado e providências vinculadas.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><rect x="2" y="9" width="13" height="8" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M15 12h3l3 3v2h-6" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="6.5" cy="19" r="1.6" stroke="currentColor" stroke-width="1.6"/><circle cx="16.5" cy="19" r="1.6" stroke="currentColor" stroke-width="1.6"/></svg>
        <h3>Módulo Logística</h3>
        <p>Gestão completa de veículos, clientes e custos de transporte em um só lugar.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><path d="M3 8l9-5 9 5-9 5-9-5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3 8v8l9 5 9-5V8" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
        <h3>Módulo Suprimentos</h3>
        <p>Estoque, inventário e compras integrados ao consumo real da operação.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><circle cx="9" cy="7" r="3.2" stroke="currentColor" stroke-width="1.6"/><path d="M3.5 20c0-3.6 2.5-6 5.5-6s5.5 2.4 5.5 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M16 5.5a3 3 0 010 6M18.5 20c0-2.8-1.6-5-4-5.7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <h3>Departamento Pessoal</h3>
        <p>Do ponto eletrônico via celular ao controle de horas extras e folha de pagamento.</p>
      </article>
      <article class="modcard">
        <svg class="icon" viewBox="0 0 24 24" fill="none"><rect x="4" y="3" width="16" height="18" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        <h3>Módulo Contabilidade</h3>
        <p>Plano de contas, relatórios contábeis e análise de indicadores financeiros.</p>
      </article>
    </div>
  </div>
</section>

<!-- ============================================================ VERTICAIS PRONTOS -->
<section class="section section-bone">
  <div class="wrap">
    <div class="section-head">
      <div class="eyebrow">Já prontos pra sua operação</div>
      <h2 class="deck">Cinco tipos de equipamento, já demonstrados na prática</h2>
      <p>Cada vertical abaixo tem um site próprio com cenários reais do sistema — cadastro técnico, contrato, checklist e almoxarifado, com dados coerentes com o que está em produção.</p>
    </div>

    <div class="vertgrid">
      <a class="vertcard" href="https://empilhadeira.oravel.com.br/">
        <span class="vert-emoji">🏗️</span>
        <h3>Empilhadeiras</h3>
        <p>O módulo mais completo — Classe II e III, franquia de horas, mobilização e IA de diagnóstico.</p>
        <span class="vert-domain">empilhadeira.oravel.com.br →</span>
      </a>

      <a class="vertcard" href="https://gerador.oravel.com.br/">
        <span class="vert-emoji">⚡</span>
        <h3>Geradores</h3>
        <p>Especificação elétrica, banco de carga estruturado e combo de acessórios até o contrato.</p>
        <span class="vert-domain">gerador.oravel.com.br →</span>
      </a>

      <a class="vertcard" href="https://compressor.oravel.com.br/">
        <span class="vert-emoji">💨</span>
        <h3>Compressores de Ar</h3>
        <p>Vaso de pressão sob NR-13 — checklist de despacho, plano preventivo e comparativo de frota.</p>
        <span class="vert-domain">compressor.oravel.com.br →</span>
      </a>

      <a class="vertcard" href="https://plataforma.oravel.com.br/">
        <span class="vert-emoji">🔺</span>
        <h3>Plataformas Elevatórias</h3>
        <p>Especificações técnicas reais e checklist NR-18/NR-35 com resultado item a item.</p>
        <span class="vert-domain">plataforma.oravel.com.br →</span>
      </a>

      <a class="vertcard" href="https://guindastes.oravel.com.br/">
        <span class="vert-emoji">🏗</span>
        <h3>Guindastes e Munck</h3>
        <p>O setor mais regulado — checklist de içamento, plano preventivo e frota comparativa.</p>
        <span class="vert-domain">guindastes.oravel.com.br →</span>
      </a>

      <a class="vertcard" href="https://pcm.oravel.com.br/">
        <span class="vert-emoji">📊</span>
        <h3>PCM — Planejamento e Controle</h3>
        <p>Pra quem entende de manutenção — MTBF, criticidade auditável, matriz ABC e retrabalho com fórmula exposta.</p>
        <span class="vert-domain">pcm.oravel.com.br →</span>
      </a>
    </div>
  </div>
</section>

<!-- ============================================================ DIFERENCIAIS ENTERPRISE -->
<section class="section section-petrol">
  <div class="wrap">
    <div class="section-head">
      <div class="eyebrow">Configuração Enterprise</div>
      <h2 class="deck">O que muda quando a operação passa da faixa de entrada</h2>
      <p>Nada disso vem "pronto" num plano de prateleira — é desenhado depois de entender sua operação.</p>
    </div>

    <div class="diflist">
      <div class="difitem">
        <span class="num mono">01</span>
        <div>
          <h3>Sem limite fixo de ativos ou pátios</h3>
          <p>O dimensionamento acompanha o tamanho real do seu parque de equipamentos e das unidades, não uma faixa pré-definida de plano.</p>
        </div>
      </div>
      <div class="difitem">
        <span class="num mono">02</span>
        <div>
          <h3>Migração assistida de dados</h3>
          <p>Se você já opera com planilha ou outro sistema, nossa equipe conduz a migração junto com você durante a implantação.</p>
        </div>
      </div>
      <div class="difitem">
        <span class="num mono">03</span>
        <div>
          <h3>Integrações dedicadas</h3>
          <p>Conexão com sistemas que sua operação já usa — financeiro, telemetria de equipamentos, ou outro software interno.</p>
        </div>
      </div>
      <div class="difitem">
        <span class="num mono">04</span>
        <div>
          <h3>Prioridade na Área do Cliente</h3>
          <p>Chamados abertos pelo portal com fila prioritária, sem depender de disponibilidade integral de um canal direto.</p>
        </div>
      </div>
      <div class="difitem">
        <span class="num mono">05</span>
        <div>
          <h3>Trilha Academy personalizada</h3>
          <p>Treinamento adaptado aos módulos e ao fluxo específico da sua operação, para gestores e técnicos.</p>
        </div>
      </div>
      <div class="difitem">
        <span class="num mono">06</span>
        <div>
          <h3>Condição comercial própria</h3>
          <p>Faturamento e forma de cobrança adequados ao porte da operação — definidos na avaliação, não num preço de tabela.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================ COMO FUNCIONA A AVALIACAO -->
<section class="section section-bone">
  <div class="wrap">
    <div class="section-head">
      <div class="eyebrow">Como funciona</div>
      <h2 class="deck">Quatro etapas até a configuração ficar pronta</h2>
      <p>Sem proposta genérica — a avaliação parte do que sua operação já faz hoje.</p>
    </div>

    <div class="steps">
      <div class="step">
        <div class="idx">01</div>
        <h3>Você agenda</h3>
        <p>Preenche o formulário abaixo com um retrato rápido da sua operação.</p>
      </div>
      <div class="step">
        <div class="idx">02</div>
        <h3>Conversa guiada</h3>
        <p>Entendemos seu parque de equipamentos, pátios, sistemas atuais e onde estão os gargalos.</p>
      </div>
      <div class="step">
        <div class="idx">03</div>
        <h3>Configuração proposta</h3>
        <p>Você recebe um desenho de módulos, limites e condição comercial sob medida.</p>
      </div>
      <div class="step">
        <div class="idx">04</div>
        <h3>Implantação assistida</h3>
        <p>Migração de dados e treinamento Academy acompanhados até a operação estar de pé.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================ FORM -->
<section class="form-section" id="agendar">
  <div class="wrap">
    <div class="form-grid">
      <div>
        <h2 class="deck">Agende sua avaliação</h2>
        <p>Preencha os dados abaixo. Nossa equipe entra em contato para marcar a conversa — normalmente em até 1 dia útil.</p>
        <ul class="form-note-list">
          <li>Sem custo e sem compromisso de assinatura</li>
          <li>Conversa focada na sua operação, não uma demonstração genérica</li>
          <li>Se o seu caso couber num plano de entrada, dizemos isso também</li>
        </ul>
      </div>

      <div>
        <div class="form-status" id="ent-form-status" role="status" aria-live="polite" style="display:none; padding:13px 15px; border-radius:6px; font-size:13.5px; margin-bottom:16px; line-height:1.5;"></div>

        <form class="contact" id="ent-lead-form" action="/locadoras/enviar-lead.php" method="post" novalidate>
          <input type="text" name="site" class="form-hp" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true" />
          <div class="field-row">
            <div class="field">
              <label for="nome">Nome</label>
              <input id="nome" name="nome" type="text" placeholder="Seu nome" autocomplete="name" required />
            </div>
            <div class="field">
              <label for="empresa">Empresa</label>
              <input id="empresa" name="empresa" type="text" placeholder="Nome da locadora/oficina" autocomplete="organization" required />
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
          <input type="hidden" name="origem" value="enterprise" />
          <button type="submit" class="btn btn-primary" id="ent-submit-btn">Agendar avaliação</button>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
  (function () {
    var form = document.getElementById('ent-lead-form');
    if (!form) return;

    var statusBox = document.getElementById('ent-form-status');
    var submitBtn = document.getElementById('ent-submit-btn');

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
          submitBtn.textContent = 'Agendar avaliação';
        });
    });
  })();
</script>

<footer class="site">
  <div class="wrap">
    <span>© 2026 Oravel — gestão de equipamentos e manutenção.</span>
    <span style="display:flex; align-items:center; gap:20px;">
      <a href="solucoes/" style="text-decoration:underline;">Catálogo completo</a>
      <a href="novidades/" style="text-decoration:underline;">Novidades</a>
      <a href="https://www.linkedin.com/in/oravel-tecnologia/" target="_blank" rel="noopener" aria-label="Oravel no LinkedIn" style="display:inline-flex; color:inherit;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.446-2.136 2.94v5.666H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 11.001-4.125 2.062 2.062 0 01-.001 4.125zM7.114 20.452H3.56V9h3.554v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
      </a>
    </span>
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
