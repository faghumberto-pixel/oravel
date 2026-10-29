"""
Tema do site novo da Oravel: CSS, cabeçalho, rodapé e componentes (mockups em HTML/CSS).

Estrutura inspirada em sites de software de manutenção e frota (menu por produto, seções alternadas,
mockups de produto, passos e FAQ). Os textos são originais da Oravel; as cores e fontes são as
que o site já usa (azul-escuro, laranja, Manrope e Inter). Dados dos mockups sempre fictícios.
"""
import html
import json
from pathlib import Path

WIDGET = (Path(__file__).resolve().parent / 'widget_whatsapp.html').read_text(encoding='utf-8')
RODAPE_JS = '<script async src="https://app.oravel.com.br/t.js?v=2"></script>\n<script src="/protecao.js?v=3" defer></script>\n'
WHATS = 'https://wa.me/5519999332615'
WHATS_TESTE = WHATS + '?text=Ol%C3%A1!%20Quero%20testar%20a%20Oravel%20por%2030%20dias%20na%20minha%20locadora.'
EMAIL = 'contato@oravel.com.br'
FONE = '(19) 99933-2615'

RECURSOS = [
    ('/recursos/', 'Todos os recursos', 'Visão completa da plataforma'),
    ('/processo-manutencao/', 'Preventiva e ordens de serviço', 'Revisão no prazo, ordem por equipamento'),
    ('/processo-gestao-de-equipes/', 'Gestão de equipes', 'Agenda, carga e desempenho dos técnicos'),
    ('/processo-gestao-de-estoques/', 'Gestão de estoques', 'Peça certa, na hora certa'),
    ('/processo-cobranca-automatica/', 'Cobrança automática', 'Da medição ao pagamento'),
    ('/nr-13-inspecao-equipamentos/', 'Inspeções NR-13', 'Datas, laudos e alertas'),
    ('/equipamento-devolvido-com-defeito/', 'Devolução com defeito', 'Do bloqueio à liberação'),
]
EQUIPAMENTOS = [
    ('/software-manutencao-guindastes/', 'Guindastes'),
    ('/software-manutencao-munks/', 'Munks'),
    ('/software-manutencao-plataformas-elevatorias/', 'Plataformas elevatórias'),
    ('/software-manutencao-empilhadeiras/', 'Empilhadeiras'),
    ('/software-manutencao-geradores/', 'Geradores'),
    ('/software-manutencao-compressores/', 'Compressores'),
    ('/software-manutencao-outros-equipamentos/', 'Outros equipamentos'),
]

CSS = """
:root{--paper:#fff;--surface:#f6f7f9;--ink:#0f172a;--ink-soft:#5b6472;--ink-faint:#8891a0;--line:#e4e7ec;
--accent:#f97316;--accent-ink:#c2410c;--accent-soft:#fff1e4;--navy:#0f172a;--navy-2:#08142c;--ok:#16a34a}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:"Inter","Segoe UI",system-ui,sans-serif;color:var(--ink);background:var(--paper);-webkit-font-smoothing:antialiased;line-height:1.5}
h1,h2,h3,h4{font-family:"Manrope","Inter",sans-serif;font-weight:800;letter-spacing:-.02em;margin:0;text-wrap:balance}
p,ul{margin:0}a{color:inherit}img,svg,video{max-width:100%}
:focus-visible{outline:3px solid var(--accent);outline-offset:2px}
.skip{position:absolute;left:-9999px}.skip:focus{left:8px;top:8px;background:var(--navy);color:#fff;padding:10px 14px;border-radius:8px;z-index:100}
.wrap{max-width:1180px;margin:0 auto;padding:0 24px}
.eyebrow{font-family:"Manrope",sans-serif;font-size:12.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--accent-ink)}
.on-dark .eyebrow,.hero .eyebrow{color:#fdba74}
/* botões */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 22px;border-radius:10px;font-weight:700;font-size:15px;text-decoration:none;border:1.5px solid transparent;cursor:pointer;min-height:48px;font-family:inherit;transition:.15s}
.btn-p{background:var(--accent);color:#0f172a}.btn-p:hover{background:#ea580c}
.btn-o{background:transparent;color:#fff;border-color:rgba(255,255,255,.4)}.btn-o:hover{border-color:#fff}
.btn-d{background:var(--navy);color:#fff}.btn-d:hover{background:#1a2540}
.btn-l{background:#fff;color:var(--ink);border-color:var(--line)}.btn-l:hover{border-color:var(--ink-faint)}
/* cabeçalho */
.top{position:sticky;top:0;z-index:60;background:rgba(255,255,255,.96);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.top-in{max-width:1180px;margin:0 auto;padding:0 24px;height:72px;display:flex;align-items:center;gap:28px}
.logo{font-family:"Manrope",sans-serif;font-weight:900;font-size:22px;text-decoration:none;letter-spacing:-.01em;display:flex;align-items:center;flex:none}
.logo i{color:var(--accent);font-style:normal}
.logo::before{content:"";display:inline-block;width:44px;height:26px;margin-right:10px;background:url(/img/logo-or.png) no-repeat center/contain}
.nav{display:none;align-items:center;gap:6px;flex:1}
.nav>a,.nav .dd>button{font:600 14.5px "Inter",sans-serif;color:var(--ink-soft);text-decoration:none;background:none;border:0;padding:10px 12px;border-radius:8px;cursor:pointer}
.nav>a:hover,.nav .dd>button:hover,.nav a[aria-current]{color:var(--ink);background:var(--surface)}
.dd{position:relative}.dd>button::after{content:"▾";margin-left:6px;font-size:11px}
.dd-menu{position:absolute;top:100%;left:0;min-width:300px;background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 24px 50px -24px rgba(15,23,42,.35);padding:8px;opacity:0;visibility:hidden;transform:translateY(6px);transition:.15s}
.dd:hover .dd-menu,.dd:focus-within .dd-menu{opacity:1;visibility:visible;transform:none}
.dd-menu a{display:block;padding:10px 12px;border-radius:9px;text-decoration:none}
.dd-menu a:hover{background:var(--surface)}.dd-menu strong{display:block;font-size:14px}.dd-menu span{display:block;font-size:12.5px;color:var(--ink-soft)}
.top-cta{display:none;gap:10px;margin-left:auto}
.top-cta .btn{padding:10px 16px;min-height:42px;font-size:14px}
.burger{margin-left:auto;background:none;border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-weight:700;cursor:pointer}
.mnav{display:none;border-top:1px solid var(--line);padding:12px 24px 20px;background:#fff}.mnav.open{display:block}
.mnav a{display:block;padding:11px 4px;text-decoration:none;font-weight:600;border-bottom:1px solid var(--line)}
.mnav h4{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-faint);margin:14px 0 4px}
@media(min-width:1000px){.nav,.top-cta{display:flex}.burger,.mnav{display:none!important}}
/* hero */
.hero{background:radial-gradient(900px 420px at 85% 0%,rgba(99,102,241,.3),transparent 60%),radial-gradient(700px 420px at 0% 100%,rgba(249,115,22,.22),transparent 60%),var(--navy-2);color:#fff;padding:56px 0 64px;position:relative;overflow:hidden}
.hero::before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.04) 1px,transparent 1px);background-size:46px 46px;-webkit-mask-image:radial-gradient(ellipse at 50% 20%,#000 25%,transparent 75%);mask-image:radial-gradient(ellipse at 50% 20%,#000 25%,transparent 75%)}
.hero .wrap{position:relative;display:grid;gap:40px;align-items:center}
.hero h1{font-size:clamp(32px,5.2vw,56px);line-height:1.06;margin:14px 0 18px}
.hero .sub{font-size:18px;color:rgba(226,232,240,.88);max-width:54ch}
.hero-act{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}
.trust{display:flex;gap:10px 24px;flex-wrap:wrap;margin-top:26px;list-style:none;padding:0;font-size:14px;color:rgba(226,232,240,.85)}
.trust li::before{content:"✓";color:var(--accent);font-weight:800;margin-right:8px}
@media(min-width:1000px){.hero .wrap{grid-template-columns:1fr 1.05fr}.hero{padding:84px 0 92px}}
/* seções */
.sec{padding:72px 0}.sec.alt{background:var(--surface)}.sec.dark{background:var(--navy-2);color:#fff}
.sec-h{max-width:720px;margin-bottom:38px}.sec-h h2{font-size:clamp(26px,4vw,40px);line-height:1.12;margin-top:10px}
.sec-h p{font-size:17px;color:var(--ink-soft);margin-top:14px}.dark .sec-h p{color:rgba(226,232,240,.8)}
.center{text-align:center;margin-left:auto;margin-right:auto}
.strip{padding:22px 0;border-bottom:1px solid var(--line);background:#fff}
.strip .wrap{display:flex;gap:10px 12px;flex-wrap:wrap;align-items:center}
.strip span{font-size:13px;font-weight:700;color:var(--ink-faint);text-transform:uppercase;letter-spacing:.08em;margin-right:6px}
.chip-l{padding:8px 14px;border:1px solid var(--line);border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;color:var(--ink-soft);background:#fff}
.chip-l:hover{border-color:var(--accent);color:var(--ink)}
.grid3{display:grid;gap:18px}.grid4{display:grid;gap:18px}
@media(min-width:700px){.grid3{grid-template-columns:repeat(2,1fr)}.grid4{grid-template-columns:repeat(2,1fr)}}
@media(min-width:1000px){.grid3{grid-template-columns:repeat(3,1fr)}.grid4{grid-template-columns:repeat(4,1fr)}}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;text-decoration:none;display:block;transition:.15s}
a.card:hover{border-color:var(--accent);transform:translateY(-2px);box-shadow:0 18px 36px -24px rgba(15,23,42,.35)}
.card .ic{width:42px;height:42px;border-radius:11px;background:var(--accent-soft);color:var(--accent-ink);display:flex;align-items:center;justify-content:center;margin-bottom:16px}
.card h3{font-size:18px}.card p{font-size:14.5px;color:var(--ink-soft);margin-top:8px}
.dark .card{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12)}.dark .card p{color:rgba(226,232,240,.75)}
.more{display:inline-block;margin-top:26px;font-weight:700;color:var(--accent-ink);text-decoration:none}.more::after{content:" →"}
.split{display:grid;gap:40px;align-items:center}
@media(min-width:1000px){.split{grid-template-columns:1fr 1.15fr;gap:64px}.split.rev .split-t{order:2}}
.split h2{font-size:clamp(26px,3.6vw,38px);line-height:1.12;margin:10px 0 16px}.split p{color:var(--ink-soft);font-size:17px}
.ticks{list-style:none;padding:0;margin:22px 0 0;display:grid;gap:12px}
.ticks li{position:relative;padding-left:30px;font-size:16px}
.ticks li::before{content:"✓";position:absolute;left:0;top:0;width:21px;height:21px;border-radius:50%;background:var(--accent-soft);color:var(--accent-ink);font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center}
.steps{display:grid;gap:18px;counter-reset:s}
@media(min-width:900px){.steps{grid-template-columns:repeat(3,1fr)}}
.step{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px;position:relative}
.step::before{counter-increment:s;content:counter(s);width:38px;height:38px;border-radius:50%;background:var(--navy);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;margin-bottom:16px;font-family:"Manrope",sans-serif}
.step h3{font-size:18px}.step p{font-size:14.5px;color:var(--ink-soft);margin-top:8px}
.faq{max-width:820px;display:grid;gap:10px}
.faq details{background:#fff;border:1px solid var(--line);border-radius:14px;padding:0 22px}
.faq summary{cursor:pointer;font-family:"Manrope",sans-serif;font-weight:700;font-size:16.5px;padding:18px 0;list-style:none}
.faq summary::-webkit-details-marker{display:none}.faq summary::after{content:"+";float:right;color:var(--accent);font-size:22px;line-height:1}
.faq details[open] summary::after{content:"–"}.faq p{font-size:15px;color:var(--ink-soft);padding-bottom:18px}
.cta{background:var(--navy-2);color:#fff;border-radius:26px;padding:48px 28px;text-align:center}
.cta h2{font-size:clamp(26px,4vw,40px)}.cta p{color:rgba(226,232,240,.82);margin:14px auto 0;max-width:56ch;font-size:17px}
.cta .hero-act{justify-content:center}.cta ul{list-style:none;padding:0;margin:22px 0 0;display:flex;gap:8px 24px;justify-content:center;flex-wrap:wrap;font-size:14px;color:rgba(226,232,240,.85)}
.cta li::before{content:"✓";color:var(--accent);margin-right:6px;font-weight:800}
/* rodapé */
.foot{background:var(--navy-2);color:#cbd5e1;padding:56px 0 28px}
.foot-g{display:grid;gap:34px}@media(min-width:900px){.foot-g{grid-template-columns:1.4fr 1fr 1fr 1fr 1fr}}
.foot h4{color:#fff;font-size:14px;margin-bottom:14px}.foot a{display:block;color:#cbd5e1;text-decoration:none;font-size:14px;line-height:2}.foot a:hover{color:var(--accent)}
.foot p{font-size:14px;color:#94a3b8}.foot .logo{color:#fff;margin-bottom:12px;font-size:22px;line-height:1.2}
.foot-b{border-top:1px solid rgba(255,255,255,.14);margin-top:34px;padding-top:20px;font-size:12.5px;color:#94a3b8}
/* mockups */
.mk{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 40px 70px -40px rgba(15,23,42,.55);overflow:hidden;font-size:13px;color:var(--ink)}
.mk-bar{display:flex;align-items:center;gap:6px;padding:11px 14px;background:#f1f4f8;border-bottom:1px solid var(--line)}
.mk-bar i{width:10px;height:10px;border-radius:50%;background:#cbd5e1}.mk-bar i:nth-child(1){background:#f87171}.mk-bar i:nth-child(2){background:#fbbf24}.mk-bar i:nth-child(3){background:#4ade80}
.mk-bar b{margin-left:12px;font-weight:600;color:var(--ink-faint);font-size:12px;background:#fff;border:1px solid var(--line);border-radius:7px;padding:3px 12px}
.mk-body{display:grid}
.mk-2{grid-template-columns:1fr}@media(min-width:620px){.mk-2{grid-template-columns:.9fr 1.1fr}}
.mk-l{border-right:1px solid var(--line);padding:14px;background:#fafbfc}.mk-r{padding:18px}
.mk h5{font-family:"Manrope",sans-serif;font-size:13px;margin:0 0 10px;color:var(--ink)}
.oi{background:#fff;border:1px solid var(--line);border-radius:11px;padding:10px 12px;margin-bottom:8px;display:flex;justify-content:space-between;gap:10px;align-items:center}
.oi.on{border-color:var(--accent);box-shadow:0 0 0 3px rgba(249,115,22,.14)}
.oi b{display:block;font-size:12.5px}.oi small{color:var(--ink-faint);font-size:11.5px}
.tg{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;white-space:nowrap}
.tg.o{background:var(--accent-soft);color:var(--accent-ink)}.tg.g{background:#dcfce7;color:#166534}.tg.y{background:#fef3c7;color:#92400e}.tg.r{background:#fee2e2;color:#991b1b}
.tl{list-style:none;padding:0;margin:12px 0;display:grid;gap:10px}
.tl li{display:flex;gap:10px;align-items:flex-start;font-size:12.5px}.tl li i{flex:none;width:9px;height:9px;border-radius:50%;background:var(--accent);margin-top:5px}
.tl li.d i{background:var(--ok)}.tl li.n i{background:#cbd5e1}
.cst{border:1px solid var(--line);border-radius:11px;overflow:hidden}.cst div{display:flex;justify-content:space-between;padding:8px 12px;border-bottom:1px solid var(--line);font-size:12.5px}.cst div:last-child{border:0;background:var(--accent-soft);font-weight:800}
.bar{height:7px;border-radius:4px;background:#e6ebf1;overflow:hidden;margin-top:6px}.bar i{display:block;height:100%;background:var(--accent);border-radius:4px}
.stats{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:16px 16px 4px}
@media(min-width:620px){.stats{grid-template-columns:repeat(4,1fr)}}
.st{border:1px solid var(--line);border-radius:12px;padding:12px}.st small{color:var(--ink-faint);font-size:11px;display:block}.st b{font-family:"Manrope",sans-serif;font-size:22px}
.st.o{background:var(--accent-soft);border-color:#fed7aa}
.mk-t{padding:8px 16px 16px}.mk-t .oi{margin:0 0 6px}
.phone{width:min(300px,100%);margin:0 auto;border-radius:38px;border:9px solid #0f172a;background:#fff;box-shadow:0 40px 70px -40px rgba(15,23,42,.6);overflow:hidden;position:relative}
.phone::before{content:"";position:absolute;top:7px;left:50%;transform:translateX(-50%);width:84px;height:20px;background:#0f172a;border-radius:12px;z-index:2}
.ph-h{background:var(--navy-2);color:#fff;padding:36px 16px 14px;font-family:"Manrope",sans-serif;font-weight:800;font-size:15px}
.ph-b{padding:14px;background:#f6f7f9;display:grid;gap:9px;font-size:12.5px}
.ph-c{background:#fff;border:1px solid var(--line);border-radius:12px;padding:11px 12px}.ph-c b{display:block}
.ph-k{display:flex;align-items:center;gap:9px;background:#fff;border:1px solid var(--line);border-radius:10px;padding:9px 11px}
.ph-k i{width:17px;height:17px;border-radius:50%;background:var(--ok);color:#fff;font-size:10px;font-style:normal;display:flex;align-items:center;justify-content:center}
.ph-s{background:#fff;border:1px dashed #cbd5e1;border-radius:10px;padding:10px;color:var(--ink-faint);font-size:11.5px}
.ph-s svg{display:block;margin-top:4px}.ph-btn{background:var(--accent);color:#0f172a;text-align:center;font-weight:800;border-radius:11px;padding:11px}
.vs{display:grid;gap:16px}@media(min-width:700px){.vs{grid-template-columns:1fr 1fr}}
.vs>div{border-radius:18px;padding:24px;border:1px solid var(--line);background:#fff}
.vs .antes{background:#fff7f7;border-color:#fecaca}.vs .depois{background:#f0fdf4;border-color:#bbf7d0}
.vs h3{font-size:17px;margin-bottom:12px}.vs ul{list-style:none;padding:0;display:grid;gap:10px;font-size:14.5px}
.vs .antes li::before{content:"✕";color:#dc2626;font-weight:800;margin-right:10px}.vs .depois li::before{content:"✓";color:#16a34a;font-weight:800;margin-right:10px}
.nota{font-size:12.5px;color:var(--ink-faint);margin-top:12px}

/* compatibilidade com o corpo das páginas geradas */
:root{--border:var(--line)}
.section{padding:64px 0}.section-surface{background:var(--surface)}
.section-head{max-width:720px;margin:0 0 34px}.section-head h2{font-size:clamp(26px,4vw,38px);line-height:1.12;margin-top:10px}
.section-head p{font-size:17px;color:var(--ink-soft);margin-top:12px}
.section .eyebrow{color:var(--accent-ink)}
.solve{display:grid;gap:28px;align-items:center;padding:36px 0;border-top:1px solid var(--line)}.solve:first-of-type{border-top:0;padding-top:0}
@media(min-width:1000px){.solve{grid-template-columns:1fr 1.1fr;gap:56px}.solve.rev .mock{order:-1}}
.solve h3{font-size:clamp(22px,3.2vw,30px);line-height:1.15;margin-top:8px}.solve p{font-size:16.5px;color:var(--ink-soft);margin-top:12px}
.solve .result{color:var(--ink);font-weight:600}
.mock{margin:0;background:#fff;border:1px solid var(--line);border-radius:18px;padding:12px}
.mock svg{display:block;width:100%;height:auto;border-radius:12px}.mock figcaption{font-size:12.5px;color:var(--ink-faint);margin-top:10px;text-align:center}
.who-grid{display:grid;gap:14px}@media(min-width:700px){.who-grid{grid-template-columns:repeat(2,1fr)}}@media(min-width:1000px){.who-grid{grid-template-columns:repeat(4,1fr)}}
.who{background:#fff;border:1px solid var(--line);border-left:4px solid var(--accent);border-radius:12px;padding:20px 22px;display:block;text-decoration:none}
a.who:hover{border-color:var(--accent)}.who h3{font-size:16px}.who p{font-size:14px;color:var(--ink-soft);margin-top:6px}
.porte{margin-top:18px;background:var(--accent-soft);border-radius:12px;padding:16px 20px;font-size:15px}
.texto{max-width:760px;font-size:16.5px;color:var(--ink-soft)}.texto p+p{margin-top:14px}
.vt{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:14px;margin-top:26px;color:var(--ink-soft)}.vt a{color:var(--accent-ink);font-weight:600}
.wide{margin:0}.wide img{width:100%;height:auto;display:block;border-radius:18px;border:1px solid var(--line);box-shadow:0 30px 60px -36px rgba(15,23,42,.45)}
.wide figcaption{font-size:12.5px;color:var(--ink-faint);margin-top:10px;text-align:center}
.hero-art svg,.hero-art img,.hero-art video{width:100%;height:auto;display:block;border-radius:22px;box-shadow:0 30px 60px -24px rgba(0,0,0,.55)}
html{-webkit-user-select:none;user-select:none}input,textarea{-webkit-user-select:text;user-select:text}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important}}
"""

JS = """
(function(){var b=document.querySelector('.burger'),m=document.getElementById('mnav');
if(b&&m){b.addEventListener('click',function(){var o=m.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false')})}
})();
"""

FAVICON = ('<link rel="icon" href="/favicon.ico" sizes="any">\n<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">\n'
           '<link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">\n<link rel="apple-touch-icon" href="/apple-touch-icon.png">\n')

FONTES = ('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
          '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">')

ICONES = {
    'pm': '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="8"/>',
    'os': '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    'ck': '<path d="M5 12l4 4 10-10"/><rect x="3" y="3" width="18" height="18" rx="3"/>',
    'av': '<path d="M12 3l9 16H3z"/><path d="M12 10v4M12 17h.01"/>',
    'hs': '<path d="M4 19V5M4 19h16"/><path d="M8 15l4-5 3 3 4-6"/>',
    'al': '<path d="M6 17V11a6 6 0 1112 0v6l2 2H4z"/><path d="M10 21h4"/>',
    'eq': '<circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.5 3-6 6-6s6 2.5 6 6M15 20c0-2.5 2-4.5 5-4.5"/>',
    'es': '<path d="M3 8l9-5 9 5v9l-9 5-9-5z"/><path d="M3 8l9 5 9-5M12 13v9"/>',
    'co': '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 15h4"/>',
    'lo': '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
}


def ic(k):
    return ('<span class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
            f'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{ICONES[k]}</svg></span>')


def cabecalho(atual=''):
    rec = ''.join(f'<a href="{u}"><strong>{t}</strong><span>{d}</span></a>' for u, t, d in RECURSOS)
    eqp = ''.join(f'<a href="{u}"><strong>{t}</strong></a>' for u, t in EQUIPAMENTOS)
    m_rec = ''.join(f'<a href="{u}">{t}</a>' for u, t, _ in RECURSOS)
    m_eq = ''.join(f'<a href="{u}">{t}</a>' for u, t in EQUIPAMENTOS)
    return (
        '<a class="skip" href="#conteudo">Ir para o conteúdo</a>'
        '<header class="top"><div class="top-in"><a class="logo" href="/" aria-label="Oravel">O<i>r</i>avel</a>'
        '<nav class="nav" aria-label="Principal">'
        f'<div class="dd"><button type="button" aria-haspopup="true">Recursos</button><div class="dd-menu">{rec}</div></div>'
        '<a href="/software-manutencao-industrial/">Manutenção</a><a href="/processo-logistica/">Logística</a><a href="/erp-cmms/">Locação</a>'
        f'<div class="dd"><button type="button" aria-haspopup="true">Soluções</button><div class="dd-menu">{eqp}</div></div>'
        '<a href="/contato.php">Contato</a></nav>'
        f'<div class="top-cta"><a class="btn btn-l" href="/contato.php">Agendar demonstração</a><a class="btn btn-p" href="{WHATS_TESTE}" target="_blank" rel="noopener">Testar 30 dias</a></div>'
        '<button class="burger" type="button" aria-expanded="false" aria-controls="mnav">Menu</button></div>'
        f'<div class="mnav" id="mnav"><h4>Recursos</h4>{m_rec}<h4>Soluções</h4>{m_eq}<h4>Mais</h4>'
        '<a href="/software-manutencao-industrial/">Manutenção</a><a href="/processo-logistica/">Logística</a><a href="/erp-cmms/">Locação</a><a href="/contato.php">Contato</a>'
        f'<div style="display:grid;gap:10px;margin-top:16px"><a class="btn btn-l" href="/contato.php">Agendar demonstração</a><a class="btn btn-p" href="{WHATS_TESTE}">Testar 30 dias</a></div></div></header>')


def rodape():
    rec = ''.join(f'<a href="{u}">{t}</a>' for u, t, _ in RECURSOS)
    eqp = ''.join(f'<a href="{u}">{t}</a>' for u, t in EQUIPAMENTOS)
    return (
        '<footer class="foot"><div class="wrap"><div class="foot-g"><div><a class="logo" href="/">O<i>r</i>avel</a>'
        '<p>Desenvolvimento e tecnologia</p><p style="margin-top:10px">Gestão de manutenção, estoque, logística e cobrança para locadoras de equipamentos.</p></div>'
        f'<div><h4>Recursos</h4>{rec}</div><div><h4>Soluções</h4>{eqp}</div>'
        '<div><h4>Conteúdo</h4><a href="/central-de-conhecimento/">Central de conhecimento</a><a href="/academia/">Academia Oravel</a><a href="/faq.php">Perguntas frequentes</a><a href="/novidades.php">Novidades</a></div>'
        '<div><h4>Empresa</h4><a href="/sobre/">Sobre a Oravel</a><a href="/contato.php">Contato</a><a href="/politica-de-privacidade/">Privacidade</a><a href="/termos-de-uso/">Termos de uso</a></div></div>'
        f'<div class="foot-b">© 2026 Oravel Desenvolvimento de Software Ltda · CNPJ 65.707.953/0001-30 · {FONE} · {EMAIL}</div></div></footer>')


def pagina(titulo, desc, canonico, corpo, ld=None, atual=''):
    ldj = f'<script type="application/ld+json">{json.dumps(ld, ensure_ascii=False)}</script>' if ld else ''
    return ('<!DOCTYPE html>\n<html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">\n'
            f'<title>{html.escape(titulo)}</title>\n<meta name="description" content="{html.escape(desc)}">\n<link rel="canonical" href="{canonico}">\n'
            f'<meta property="og:type" content="website"><meta property="og:locale" content="pt_BR"><meta property="og:site_name" content="Oravel">'
            f'<meta property="og:title" content="{html.escape(titulo)}"><meta property="og:description" content="{html.escape(desc)}"><meta property="og:url" content="{canonico}">\n'
            f'{FAVICON}{FONTES}\n{ldj}\n<style>{CSS}</style></head><body>\n{cabecalho(atual)}\n<main id="conteudo">{corpo}</main>\n{rodape()}\n<script>{JS}</script>\n{WIDGET}\n{RODAPE_JS}</body></html>')


# ------------------------------------------------------------------ componentes
def secao(eyebrow, h2, intro, corpo, alt=False, dark=False, centro=False):
    cls = 'sec' + (' alt' if alt else '') + (' dark on-dark' if dark else '')
    c = ' center' if centro else ''
    p = f'<p>{intro}</p>' if intro else ''
    return f'<section class="{cls}"><div class="wrap"><div class="sec-h{c}"><div class="eyebrow">{eyebrow}</div><h2>{h2}</h2>{p}</div>{corpo}</div></section>'


def cards(itens, link=True):
    out = ''
    for icone, t, d, *u in itens:
        tag, href = ('a', f' href="{u[0]}"') if u else ('div', '')
        out += f'<{tag} class="card"{href}>{ic(icone)}<h3>{t}</h3><p>{d}</p></{tag}>'
    return out


def faq(itens):
    return '<div class="faq">' + ''.join(f'<details><summary>{q}</summary><p>{a}</p></details>' for q, a in itens) + '</div>'


def cta_final(titulo='Veja a Oravel na rotina da sua locadora', texto='Teste por 30 dias, com acompanhamento, treinamento e implementação inclusos. A Oravel complementa o sistema que você já usa.'):
    return (f'<section class="sec"><div class="wrap"><div class="cta"><h2>{titulo}</h2><p>{texto}</p>'
            f'<div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar demonstração</a><a class="btn btn-o" href="{WHATS_TESTE}" target="_blank" rel="noopener">Testar 30 dias</a></div>'
            f'<ul><li>30 dias grátis</li><li>Treinamento incluso</li><li>Implementação inclusa</li><li>WhatsApp {FONE}</li></ul></div></div></section>')


# mockups
def mk_painel():
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>app.oravel.com.br · Painel</b></div>'
            '<div class="stats"><div class="st"><small>Disponíveis</small><b>62</b></div><div class="st o"><small>Em manutenção</small><b>9</b></div>'
            '<div class="st"><small>OS abertas</small><b>14</b></div><div class="st"><small>Preventivas a vencer</small><b>5</b></div></div>'
            '<div class="mk-t"><h5 style="margin:12px 0 8px">Ordens de serviço</h5>'
            '<div class="oi on"><div><b>Revisão da lança · Guindaste 01</b><small>Técnico A · hoje</small></div><span class="tg o">Em curso</span></div>'
            '<div class="oi"><div><b>Troca de filtros · Gerador 03</b><small>Técnico B · amanhã</small></div><span class="tg y">Aberta</span></div>'
            '<div class="oi"><div><b>Inspeção geral · Empilhadeira 07</b><small>Técnico C · ontem</small></div><span class="tg g">Concluída</span></div></div></div>')


def mk_os():
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Ordens de serviço</b></div><div class="mk-body mk-2">'
            '<div class="mk-l"><h5>Abertas</h5>'
            '<div class="oi on"><div><b>OS · Guindaste 01</b><small>Revisão da lança</small></div><span class="tg o">Em curso</span></div>'
            '<div class="oi"><div><b>OS · Gerador 03</b><small>Troca de filtros</small></div><span class="tg y">Aberta</span></div>'
            '<div class="oi"><div><b>OS · Compressor 02</b><small>Revisão geral</small></div><span class="tg y">Aberta</span></div>'
            '<div class="oi"><div><b>OS · Munk 05</b><small>Inspeção</small></div><span class="tg g">Concluída</span></div></div>'
            '<div class="mk-r"><h5>Guindaste 01 · Revisão da lança</h5><div class="tg o" style="display:inline-block">Em curso</div>'
            '<ul class="tl"><li class="d"><i></i>Ordem aberta</li><li class="d"><i></i>Peças separadas</li><li><i></i>Serviço em execução</li><li class="n"><i></i>Checklist e assinatura</li></ul>'
            '<div class="cst"><div><span>Peças</span><span>R$ ••••</span></div><div><span>Mão de obra</span><span>R$ ••••</span></div><div><span>Custo total</span><span>R$ ••••</span></div></div></div></div></div>')


def mk_celular():
    return ('<div class="phone"><div class="ph-h">Minhas ordens do dia</div><div class="ph-b">'
            '<div class="ph-c"><b>Revisão da lança</b><span style="color:#5b6472">Guindaste 01 · em curso</span><div class="bar"><i style="width:60%"></i></div></div>'
            '<div class="ph-k"><i>✓</i>Estrutura e cabos</div><div class="ph-k"><i>✓</i>Patolas e estabilizadores</div><div class="ph-k"><i>✓</i>Itens de segurança</div>'
            '<div class="ph-s">Assinatura do cliente<svg width="150" height="30" viewBox="0 0 150 30"><path d="M4 22c10-22 18 8 28-6s16 12 26-2 16-10 24 6" fill="none" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/></svg></div>'
            '<div class="ph-btn">Enviar ordem</div></div></div>')


def vs(antes, depois, t1='Antes', t2='Com a Oravel'):
    li = lambda xs: ''.join(f'<li>{x}</li>' for x in xs)
    return f'<div class="vs"><div class="antes"><h3>{t1}</h3><ul>{li(antes)}</ul></div><div class="depois"><h3>{t2}</h3><ul>{li(depois)}</ul></div></div><p class="nota">Exemplo ilustrativo.</p>'


def mk_estoque():
    linhas = [('Filtro de óleo', 'Em dia', 'g', 72), ('Correia dentada', 'Abaixo do mínimo', 'r', 18), ('Óleo lubrificante', 'Em dia', 'g', 55), ('Mangueira hidráulica', 'Atenção', 'y', 34)]
    r = ''.join(f'<div class="oi"><div style="flex:1"><b>{n}</b><div class="bar"><i style="width:{p}%"></i></div></div><span class="tg {k}">{s}</span></div>' for n, s, k, p in linhas)
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Estoque · Almoxarifado central</b></div><div class="stats">'
            '<div class="st"><small>Itens</small><b>248</b></div><div class="st o"><small>Abaixo do mínimo</small><b>6</b></div><div class="st"><small>Em reposição</small><b>9</b></div><div class="st"><small>Recebimentos hoje</small><b>3</b></div></div>'
            f'<div class="mk-t"><h5 style="margin:12px 0 8px">Níveis de estoque</h5>{r}</div></div>')


def mk_funil():
    col = lambda t_, xs: '<div style="background:#f6f7f9;border:1px solid #e4e7ec;border-radius:12px;padding:10px"><h5 style="margin:0 0 8px">' + t_ + '</h5>' + ''.join(f'<div class="oi" style="display:block"><b>{a}</b><small>{b}</small></div>' for a, b in xs) + '</div>'
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Funil comercial</b></div><div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;padding:14px">'
            + col('Prospectado', [('Locadora A', 'Primeiro contato'), ('Locadora B', 'Aguardando retorno')]) + col('Contato iniciado', [('Locadora C', 'Reunião marcada')])
            + col('Proposta enviada', [('Locadora D', 'Follow-up amanhã')]) + col('Fechado', [('Locadora E', 'Contrato assinado')]) + '</div></div>')


def mk_ponto():
    return ('<div class="phone"><div class="ph-h">Ponto eletrônico</div><div class="ph-b">'
            '<div class="ph-c"><b>Hoje</b><span style="color:#5b6472">Entrada registrada às 07:58</span><div class="bar"><i style="width:70%"></i></div></div>'
            '<div class="ph-k"><i>✓</i>Entrada</div><div class="ph-k"><i>✓</i>Intervalo</div><div class="ph-k"><i style="background:#cbd5e1">·</i>Saída</div>'
            '<div class="ph-c"><b>Comunicar falta</b><span style="color:#5b6472">Enviar foto do atestado</span></div><div class="ph-btn">Registrar saída</div></div></div>')


# ---------------------------------------------------------------- página de recursos (abas + blocos)
CSS_REC = """
.subnav{position:sticky;top:72px;z-index:50;background:rgba(255,255,255,.96);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.subnav .wrap{display:flex;gap:8px;overflow-x:auto;padding-top:12px;padding-bottom:12px;scrollbar-width:none}.subnav .wrap::-webkit-scrollbar{display:none}
.subnav a{flex:none;padding:8px 16px;border-radius:999px;font-size:14px;font-weight:600;text-decoration:none;color:var(--ink-soft)}
.subnav a:hover{background:var(--surface);color:var(--ink)}.subnav a.on{background:var(--navy);color:#fff}
.feat{padding:72px 0 24px;scroll-margin-top:140px}.feat .row{display:grid;gap:44px;align-items:center}
@media(min-width:1000px){.feat .row{grid-template-columns:1.05fr 1fr;gap:72px}.feat.rev .row>.fx{order:2}}
.feat h2{font-size:clamp(28px,4vw,44px);line-height:1.1;margin:12px 0 16px}.feat .lede{font-size:17.5px;color:var(--ink-soft);max-width:56ch}
.kpis{display:grid;gap:18px;margin-top:30px}@media(min-width:620px){.kpis{grid-template-columns:repeat(3,1fr)}}
.kpi{border-left:3px solid var(--accent);padding-left:16px}.kpi b{display:block;font-family:"Manrope",sans-serif;font-size:21px;line-height:1.2}.kpi span{font-size:13.5px;color:var(--ink-soft)}
.fcards{display:grid;gap:18px;margin-top:40px}@media(min-width:820px){.fcards{grid-template-columns:repeat(3,1fr)}}
.fc{background:#fff;border:1px solid var(--line);border-radius:16px;padding:24px;position:relative}
.fc .ic2{position:absolute;right:18px;top:18px;width:36px;height:36px;border-radius:9px;background:var(--accent-soft);color:var(--accent-ink);display:flex;align-items:center;justify-content:center}
.fc h3{font-size:18px;padding-right:44px}.fc>p{font-size:14.5px;color:var(--ink-soft);margin-top:8px}
.fc ul{list-style:none;padding:14px 0 0;margin:16px 0 0;border-top:1px solid var(--line);display:grid;gap:9px}
.fc li{position:relative;padding-left:22px;font-size:14px}.fc li::before{content:"✓";position:absolute;left:0;color:var(--ok);font-weight:800}
.sep{border:0;border-top:1px solid var(--line);margin:56px 0 0}
.prg{display:grid;gap:14px;padding:18px}.prg h6{margin:0;font-size:13px}.prg small{color:var(--ink-faint);font-size:12px;display:block;margin-top:2px}
.prg .bar{height:8px}.prg .rd i{background:#dc2626}
.kp3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:16px}.kp3 .st b{font-size:20px}
.ml{display:grid;gap:10px;padding:16px}.ml .row2{display:flex;align-items:center;gap:10px;font-size:12.5px}.ml .row2 span:first-child{width:74px;color:var(--ink-soft)}.ml .row2 .bar{flex:1;margin:0}
.ass{padding:16px;display:grid;gap:10px}.ass .ln{display:flex;gap:9px;align-items:center;font-size:12.5px;padding:9px 11px;border:1px solid var(--line);border-radius:10px}.ass .ln i{width:8px;height:8px;border-radius:50%;background:var(--accent)}.ass .ln em{margin-left:auto;color:var(--ink-faint);font-style:normal}
"""


def pagina_rec(titulo, desc, canonico, corpo, ld=None):
    return pagina(titulo, desc, canonico, corpo, ld).replace('</style>', CSS_REC + '</style>', 1)


def mk_pm():
    r = lambda n, d, p, red=False: f'<div class="prg"><div><h6>{n}</h6><small>{d}</small><div class="bar {"rd" if red else ""}"><i style="width:{p}%"></i></div></div></div>'
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Planos de preventiva</b></div>'
            + r('Revisão da lança · Guindaste 01', 'Próxima revisão em 70 h', 84, True) + r('Troca de filtros · Gerador 03', 'Faltam 120 h', 58) + r('Inspeção anual · Munk 05', 'Prevista para daqui a 38 dias', 30)
            + '<div style="padding:0 18px 18px"><span class="tg o">Ordem de serviço aberta automaticamente</span></div></div>')


def mk_checklist():
    k = lambda x, ok=True: f'<div class="ph-k" style="margin:0 0 8px"><i style="background:{"#16a34a" if ok else "#cbd5e1"}">{"✓" if ok else "·"}</i>{x}</div>'
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Checklist de entrega · Plataforma 01</b></div><div style="padding:16px">'
            + k('Cesto e guarda-corpo') + k('Sistema de elevação') + k('Comandos e segurança') + k('Fotos anexadas (4)')
            + '<div class="ph-s" style="margin-top:6px">Assinatura do cliente<svg width="150" height="30" viewBox="0 0 150 30"><path d="M4 22c10-22 18 8 28-6s16 12 26-2 16-10 24 6" fill="none" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/></svg></div></div></div>')


def mk_ativo():
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Equipamento · Guindaste 01</b></div><div class="ass">'
            '<div class="ln"><i></i>Ficha técnica e documentos<em>completo</em></div><div class="ln"><i></i>3 fotos do equipamento<em>anexadas</em></div>'
            '<div class="ln"><i></i>Horímetro atual<em>1.180 h</em></div><div class="ln"><i></i>QR code para etiqueta<em>pronto</em></div>'
            '<div class="ln"><i></i>Histórico individual<em>12 registros</em></div></div></div>')


def mk_pecas():
    li = lambda a, b, k, s: f'<div class="oi"><div><b>{a}</b><small>{b}</small></div><span class="tg {k}">{s}</span></div>'
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Peças e compras</b></div><div class="mk-t" style="padding-top:14px">'
            + li('Filtro hidráulico', 'Estoque mínimo: 4', 'r', '2 disponíveis') + li('Filtro de ar', 'Estoque mínimo: 3', 'g', '9 disponíveis') + li('Correia dentada', 'Estoque mínimo: 5', 'r', '0 em estoque')
            + '<div class="oi"><div><b>Pedido de compra</b><small>Recebimento parcial</small></div><span class="tg o">2 de 6 recebidos</span></div></div></div>')


def mk_relatorios():
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Relatórios</b></div><div class="kp3"><div class="st"><small>Disponibilidade</small><b>91%</b></div>'
            '<div class="st"><small>Preventivas em dia</small><b>94%</b></div><div class="st o"><small>Retrabalho</small><b>6%</b></div></div>'
            '<div class="ml"><div class="row2"><span>Hidráulica</span><div class="bar"><i style="width:82%"></i></div></div><div class="row2"><span>Desgaste</span><div class="bar"><i style="width:64%"></i></div></div>'
            '<div class="row2"><span>Elétrica</span><div class="bar"><i style="width:35%"></i></div></div><div class="row2"><span>Operação</span><div class="bar"><i style="width:22%"></i></div></div></div></div>')


def mk_equipe():
    p = lambda n, h, pc, red=False: f'<div class="row2" style="padding:6px 0"><span style="width:90px;color:#0f172a;font-weight:600">{n}</span><div class="bar {"rd" if red else ""}" style="flex:1"><i style="width:{pc}%"></i></div><span style="width:60px;text-align:right;color:#5b6472">{h}</span></div>'
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Carga de trabalho desta semana</b></div><div class="ml" style="gap:2px">'
            + p('Técnico A', '34/40 h', 85) + p('Técnico B', '41/40 h', 100, True) + p('Técnico C', '12/40 h', 30)
            + '<div class="ass" style="padding:12px 0 0"><div class="ln"><i style="background:#f59e0b"></i>Técnico B está acima da capacidade<em>redistribuir</em></div></div></div></div>')


def mk_logistica():
    li = lambda a, b, k, s: f'<div class="oi"><div><b>{a}</b><small>{b}</small></div><span class="tg {k}">{s}</span></div>'
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Retiradas e entregas</b></div><div class="mk-t" style="padding-top:14px">'
            + li('Entrega na obra · Gerador 03', 'Saída com checklist e fotos', 'o', 'Em trânsito') + li('Retirada no cliente · Munk 05', 'Solicitada pelo comercial', 'y', 'Agendada')
            + li('Chegada no pátio · Empilhadeira 07', 'Conferência concluída', 'g', 'Liberada') + li('Chegada no pátio · Compressor 02', 'Avaria encontrada', 'r', 'Quarentena') + '</div></div>')


def mk_seguranca():
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Atividade</b></div><div class="ass">'
            '<div class="ln"><i></i>Técnico A concluiu a OS · Guindaste 01<em>2 min</em></div><div class="ln"><i></i>Supervisor revisou a avaria · Munk 05<em>1 h</em></div>'
            '<div class="ln"><i></i>Gestor alterou o plano de preventiva<em>3 h</em></div><div class="ln"><i></i>Comercial liberou o equipamento · Gerador 03<em>5 h</em></div>'
            '<div class="ln" style="background:var(--surface);color:var(--ink-soft)">Dados isolados por empresa · links de arquivos com validade</div></div></div>')


def bloco_feat(ident, icone, rotulo, h2, lede, kpis, cartoes, mock, rev=False):
    k = ''.join(f'<div class="kpi"><b>{a}</b><span>{b}</span></div>' for a, b in kpis)
    c = ''.join(f'<div class="fc"><span class="ic2"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{ICONES[i]}</svg></span>'
                f'<h3>{ti}</h3><p>{de}</p><ul>{"".join(f"<li>{x}</li>" for x in li)}</ul></div>' for i, ti, de, li in cartoes)
    return (f'<section class="feat{" rev" if rev else ""}" id="{ident}"><div class="wrap"><div class="row"><div class="fx"><div class="eyebrow">{rotulo}</div><h2>{h2}</h2><p class="lede">{lede}</p><div class="kpis">{k}</div></div>'
            f'<div>{mock}<p class="nota" style="text-align:center">Tela ilustrativa, com dados fictícios.</p></div></div><div class="fcards">{c}</div><hr class="sep"></div></section>')


CSS_REF = """
.tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden;font-size:14.5px}
.tbl th,.tbl td{text-align:left;padding:13px 16px;border-bottom:1px solid var(--line);vertical-align:top}.tbl th{background:var(--surface);font-family:"Manrope",sans-serif;font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft)}
.tbl td:first-child{font-weight:700;width:30%}.tbl tr:last-child td{border-bottom:0}.tbl .ok{color:var(--ok);font-weight:800}
.tbl-w{overflow-x:auto;border-radius:14px}
.tline{list-style:none;padding:0;margin:0;display:grid;gap:0;border-left:3px solid var(--line);margin-left:12px}
.tline li{position:relative;padding:0 0 28px 28px}.tline li::before{content:"";position:absolute;left:-10px;top:3px;width:17px;height:17px;border-radius:50%;background:#fff;border:4px solid var(--accent)}
.tline h3{font-size:18px}.tline p{font-size:15px;color:var(--ink-soft);margin-top:6px}
.qs{list-style:none;padding:0;counter-reset:q;display:grid;gap:12px;max-width:820px}
.qs li{counter-increment:q;background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px 20px 16px 58px;position:relative;font-size:15.5px}
.qs li::before{content:counter(q);position:absolute;left:18px;top:14px;width:28px;height:28px;border-radius:50%;background:var(--navy);color:#fff;font-weight:800;display:flex;align-items:center;justify-content:center;font-size:13px}
.tags{display:flex;flex-wrap:wrap;gap:10px}.tags span{padding:9px 16px;border-radius:999px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);font-size:14px;font-weight:600;color:#e2e8f0}
.ba{display:grid;gap:12px;grid-template-columns:1fr 1fr}.ba>div{background:#fff;border-radius:14px;padding:16px;border:1px solid var(--line)}
.ba h6{margin:0 0 10px;font-size:12.5px;display:flex;justify-content:space-between}.ba .antes{background:#fff7f7;border-color:#fecaca}.ba .depois{background:#f0fdf4;border-color:#bbf7d0}
.ba p{font-size:12.5px;margin:7px 0;color:var(--ink-soft)}
.opt{display:grid;gap:18px}@media(min-width:760px){.opt{grid-template-columns:repeat(2,1fr)}}
.form{background:#fff;border:1px solid var(--line);border-radius:18px;padding:26px;display:grid;gap:14px;box-shadow:0 30px 60px -40px rgba(15,23,42,.4)}
.form label{display:block;font-size:13px;font-weight:700;margin-bottom:6px}
.form input,.form select{width:100%;font:inherit;font-size:16px;padding:12px;border:1px solid #c3c9d3;border-radius:10px;background:#fff;color:var(--ink);min-height:48px}
.form .r2{display:grid;gap:14px}@media(min-width:620px){.form .r2{grid-template-columns:1fr 1fr}}
.form .hp{position:absolute;left:-9999px}.form .fine{font-size:12px;color:var(--ink-soft)}
.fmsg{border-radius:10px;padding:12px 14px;font-size:14px;font-weight:600}.fmsg[hidden]{display:none}.fmsg.ok{background:#dcfce7;color:#14532d}.fmsg.err{background:#fee2e2;color:#7f1d1d}
"""


def pagina_ref(titulo, desc, canonico, corpo, ld=None, extra_js=''):
    p = pagina(titulo, desc, canonico, corpo, ld).replace('</style>', CSS_REF + '</style>', 1)
    return p.replace('</body>', extra_js + '</body>', 1)


def hero_pg(eyebrow, h1, sub, mock='', nota=True, ctas=True):
    m = f'<div>{mock}<p class="nota" style="color:#94a3b8">Tela ilustrativa, com dados fictícios.</p></div>' if mock else ''
    cols = '' if mock else ' style="grid-template-columns:1fr"'
    a = (f'<div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar demonstração</a><a class="btn btn-o" href="{WHATS_TESTE}" target="_blank" rel="noopener">Testar 30 dias</a></div>') if ctas else ''
    return f'<section class="hero"><div class="wrap"{cols}><div><div class="eyebrow">{eyebrow}</div><h1>{h1}</h1><p class="sub">{sub}</p>{a}</div>{m}</div></section>'


def tabela(cab, linhas):
    th = ''.join(f'<th>{c}</th>' for c in cab)
    tr = ''.join('<tr>' + ''.join(f'<td>{c}</td>' for c in l) + '</tr>' for l in linhas)
    return f'<div class="tbl-w"><table class="tbl"><thead><tr>{th}</tr></thead><tbody>{tr}</tbody></table></div>'


def linha_tempo(itens):
    return '<ol class="tline">' + ''.join(f'<li><h3>{a}</h3><p>{b}</p></li>' for a, b in itens) + '</ol>'


def lista_num(itens):
    return '<ol class="qs">' + ''.join(f'<li>{x}</li>' for x in itens) + '</ol>'


def passos(itens):
    return '<div class="steps" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">' + ''.join(f'<div class="step"><h3>{a}</h3><p>{b}</p></div>' for a, b in itens) + '</div>'


def cartoes_lista(itens):
    return '<div class="fcards">' + ''.join(
        f'<div class="fc"><span class="ic2"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{ICONES[i]}</svg></span>'
        f'<h3>{ti}</h3><ul>{"".join(f"<li>{x}</li>" for x in li)}</ul></div>' for i, ti, li in itens) + '</div>'


def faq_ld(itens, nome):
    return {'@context': 'https://schema.org', '@graph': [{'@type': 'WebPage', 'name': nome},
            {'@type': 'FAQPage', 'mainEntity': [{'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': a}} for q, a in itens]}]}


def mk_programacao():
    return ('<div class="mk"><div class="mk-bar"><i></i><i></i><i></i><b>Programação do dia</b></div><div style="padding:16px"><div class="ba">'
            '<div class="antes"><h6>Antes <span class="tg r">pedidos soltos</span></h6><p>Retirada pedida por mensagem</p><p>Saída sem checklist</p><p>Chegada sem conferência</p></div>'
            '<div class="depois"><h6>Com a Oravel <span class="tg g">organizado</span></h6><p>Pedido com status</p><p>Saída com fotos e assinatura</p><p>Chegada conferida no pátio</p></div></div>'
            '<div style="margin-top:12px"><span class="tg o">Exemplo ilustrativo</span></div></div></div>')
