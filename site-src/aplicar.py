#!/usr/bin/env python3
"""Aplica a estrutura de Soluções (6 pacotes) ao site em public_html/. Idempotente: pode rodar quantas vezes quiser.

Uso: python3 site-src/aplicar.py
"""
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import dados  # noqa: E402
import mockups  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent / 'public_html'
APP = 'https://app.oravel.com.br'
PACOTES = dados.PACOTES

# ---------------------------------------------------------------- utilidades


def ler(rel):
    return (RAIZ / rel).read_text(encoding='utf-8')


def gravar(rel, texto):
    p = RAIZ / rel
    p.parent.mkdir(parents=True, exist_ok=True)
    if not p.exists() or p.read_text(encoding='utf-8') != texto:
        p.write_text(texto, encoding='utf-8')
        print('  atualizado', rel)


def trocar(texto, padrao, novo, rotulo, flags=re.S, obrigatorio=True):
    resultado, n = re.subn(padrao, lambda m: novo, texto, count=1, flags=flags)
    if n == 0 and obrigatorio:
        raise SystemExit(f'padrão não encontrado: {rotulo}')
    return resultado


def assinar(codigo):
    return f'{APP}/assinar/{codigo}'


def cta(codigo):
    """(href, rótulo) do botão principal: Assinar, ou Falar com um especialista quando não há venda online."""
    p = dados.POR_CODIGO[codigo]
    if dados.venda_online(codigo):
        return assinar(codigo), f'Assinar {p["nome"]}'
    return f'/contato.php?pacote={codigo}', 'Falar com um especialista'


# ---------------------------------------------------------------- menu das páginas "inav"

CSS_NAV = '''<style data-site="nav-css">
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
</style>'''


TOPBAR_CSS = (
    '.tb { background:#000 !important; color:#e5e7eb !important; font-size:12px !important; font-weight:600 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; line-height:1.4 !important; } '
    '.tb-in { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; min-height:36px; display:flex !important; align-items:center !important; justify-content:space-between !important; gap:24px; box-sizing:border-box; } '
    '.tb a { color:#e5e7eb !important; text-decoration:none !important; white-space:nowrap; } .tb a:hover { color:#f97316 !important; } '
    '.tb .tb-msg a { color:#f97316 !important; font-weight:700 !important; margin-left:6px; } .tb .tb-msg b { color:#fff !important; } '
    '@media (max-width:760px) { .tb-in { padding:6px 16px !important; } .tb-wa { display:none !important; } }'
)
MSG_PLANOS = ('<span class="tb-msg"><b>Contrate online:</b> no cartão recorrente você paga menos que o valor cheio. Sem compromisso, no boleto ou Pix. '
              '<a href="/planos/">Veja os planos →</a></span>')
WA_TOPO = '<a class="tb-wa" href="https://wa.me/5519999332615" target="_blank" rel="noopener noreferrer">WhatsApp (19) 99933-2615</a>'
CSS_NAV = CSS_NAV.replace('</style>', '  ' + TOPBAR_CSS + '\n</style>')
TOPBAR = f'<div class="tb" data-site="topbar"><div class="tb-in">{MSG_PLANOS}{WA_TOPO}</div></div>'


CSS_HDR = (
    # Cabeçalho idêntico ao da home. Seletores específicos + !important nos pontos de layout: páginas antigas têm CSS próprio
    # para `nav`, `a` e `.wrap` que desmontavam o menu (logo colado nos links, fundo cinza).
    'nav.inav[data-site="nav"] { position:sticky !important; top:0; z-index:50; display:block !important; background:#fff !important; border-bottom:1px solid #e4e7ec !important; padding:0 !important; margin:0 !important; font-family:Inter,"Segoe UI",system-ui,sans-serif !important; } '
    'nav.inav .hd-wrap { max-width:1180px !important; margin:0 auto !important; padding:0 28px !important; height:72px !important; display:flex !important; flex-direction:row !important; align-items:center !important; justify-content:space-between !important; gap:24px !important; box-sizing:border-box; } '
    'nav.inav .hd-logo { text-decoration:none !important; color:#0f172a !important; font-size:22px !important; font-weight:900 !important; font-family:Manrope,sans-serif !important; letter-spacing:-.01em; flex:none; } nav.inav .hd-logo i { color:#f97316 !important; font-style:normal !important; } '
    'nav.inav .hd-links { display:flex !important; align-items:center !important; gap:32px !important; } nav.inav .hd-links a { color:#5b6472 !important; font-size:14px !important; font-weight:600 !important; text-decoration:none !important; transition:color .2s; } '
    'nav.inav .hd-links a:hover, nav.inav .hd-links a[aria-current="page"] { color:#0f172a !important; } '
    'nav.inav .hd-dd { position:relative; padding-bottom:8px; } nav.inav .hd-dd::after { content:""; position:absolute; top:100%; left:0; right:0; height:8px; z-index:99; } nav.inav .hd-dd > a { display:inline-block !important; padding:8px 0; } '
    'nav.inav .hd-menu { position:absolute; top:calc(100% + 8px); left:0; background:#fff; border:1px solid #e4e7ec; border-radius:8px; box-shadow:0 10px 25px rgba(0,0,0,.1); opacity:0; visibility:hidden; transition:opacity .2s ease, visibility .2s ease; min-width:220px; z-index:100; pointer-events:none; } '
    'nav.inav .hd-dd:hover .hd-menu, nav.inav .hd-dd:focus-within .hd-menu { opacity:1; visibility:visible; pointer-events:auto; } '
    'nav.inav .hd-menu a { display:block !important; padding:12px 16px !important; font-size:14px !important; border-bottom:1px solid #e4e7ec; } nav.inav .hd-menu a:last-child { border-bottom:none; } nav.inav .hd-menu a:hover { background:#f6f7f9; } '
    'nav.inav a.hd-cta { background:#f97316 !important; color:#fff !important; padding:10px 24px !important; border-radius:6px !important; font-weight:700 !important; font-size:14px !important; text-decoration:none !important; white-space:nowrap; flex:none; } nav.inav a.hd-cta:hover { background:#ea580c !important; } '
    '@media (max-width:768px) { nav.inav .hd-wrap { flex-direction:column !important; height:auto !important; gap:16px !important; padding:16px 28px !important; } nav.inav .hd-links { flex-wrap:wrap; justify-content:center; gap:16px !important; } nav.inav a.hd-cta, nav.inav .hd-sobre { display:none !important; } }'
)
CSS_NAV = CSS_NAV.replace('</style>', '  ' + CSS_HDR + '\n</style>')


def nav_inav(atual=None):
    """Cabeçalho único do site: faixa preta + menu idêntico ao da home, em todas as páginas. `atual` marca o item da página."""
    itens_dd = ''.join(
        f'<a href="{p["pagina"]}"{" aria-current=\"page\"" if atual == p["pagina"] or dados.PAGINA_PACOTE.get(atual) == p["codigo"] else ""}>{p["nome"]}</a>'
        for p in PACOTES)
    cur = lambda href: ' aria-current="page"' if atual == href else ''  # noqa: E731
    return (
        TOPBAR + '<nav class="inav" data-site="nav" aria-label="Principal"><div class="hd-wrap">'
        '<a href="/" class="hd-logo">O<i>r</i>avel</a>'
        f'<div class="hd-links"><div class="hd-dd"><a href="/solucoes/" aria-haspopup="true">Soluções</a><div class="hd-menu">{itens_dd}</div></div>'
        f'<a href="/planos/"{cur("/planos/")}>Planos</a><a href="/sobre/" class="hd-sobre"{cur("/sobre/")}>Sobre Nós</a><a href="/contato.php"{cur("/contato.php")}>Contato</a></div>'
        '<a href="/contato.php" class="hd-cta">Falar com um Especialista</a>'
        '</div></nav>'
    )


def footer_inav():
    sol = ''.join(f'<a href="{p["pagina"]}">{p["nome"]}</a>' for p in PACOTES)
    return (
        '<footer class="ifoot" data-site="footer">'
        f'<div class="ifoot-sol"><b>Soluções</b>{sol}</div>'
        '<div class="ifoot-wrap">'
        '<div class="ifoot-copy">© 2026 Oravel Desenvolvimento de Software'
        '<div class="ifoot-legal">CNPJ 65.707.953/0001-30 · Rua Pais Leme, 215, conj. 1713, Pinheiros, São Paulo/SP · CEP 05424-150</div></div>'
        '<div class="ifoot-links"><a href="/">Home</a><a href="/solucoes/">Soluções</a><a href="/planos/">Planos</a><a href="/inteligencia-artificial/">IA</a><a href="/academia/">Academia</a><a href="/contato.php">Contato</a><a href="/faq.php">FAQ</a><a href="/central-de-conhecimento/">Ajuda</a>'
        '<a href="/politica-de-privacidade/">Privacidade</a><a href="/termos-de-uso/">Termos</a></div>'
        '</div></footer>'
    )


JS_PRECOS = r'''(function () {
  var API = 'https://app.oravel.com.br/api/public/planos';
  var CICLOS = { monthly: 'mês', mensal: 'mês', yearly: 'ano', anual: 'ano' };
  function brl(v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; }

  function preco(box, p) {
    var un = CICLOS[p.ciclo] || 'mês';
    var rec = el('div', 'pv-rec');
    var cheio = el('s', 'pv-cheio', brl(p.valor_cheio));
    rec.appendChild(cheio);
    rec.appendChild(el('b', 'pv-valor', ' ' + brl(p.valor_recorrente)));
    rec.appendChild(el('span', 'pv-un', '/' + un));
    box.appendChild(rec);
    box.appendChild(el('div', 'pv-linha', 'No cartão recorrente (' + p.desconto_percentual + '% de desconto sobre o valor cheio).'));
    box.appendChild(el('div', 'pv-linha', 'Ou ' + brl(p.valor_cheio) + '/' + un + ' no boleto ou Pix, sem compromisso: 30 dias de acesso a cada pagamento.'));
    if (p.implantacao) {
      var i = p.implantacao;
      var t = (i.opcional ? 'Implantação opcional: ' : 'Implantação: ') + brl(i.valor) + (i.parcelas_max > 1 ? ' (à vista ou em até ' + i.parcelas_max + 'x)' : '');
      box.appendChild(el('div', 'pv-linha', t));
    }
    box.hidden = false;
  }

  function taxas(box, lista) {
    if (!lista.length) return;
    box.appendChild(el('h3', 'pv-tit', 'Taxas de configuração (opcionais, pagamento único)'));
    var ul = el('ul', 'pv-taxas');
    lista.forEach(function (t) {
      var li = el('li');
      li.appendChild(el('b', null, t.nome + ' · ' + brl(t.valor)));
      if (t.descricao) li.appendChild(el('span', null, ' ' + t.descricao));
      ul.appendChild(li);
    });
    box.appendChild(ul);
    box.appendChild(el('p', 'pv-linha', 'Prefere configurar sozinho? Veja o treinamento na Academia Oravel.'));
    box.hidden = false;
  }

  function iniciar() {
    var caixas = document.querySelectorAll('[data-preco], [data-taxas]');
    if (!caixas.length || !window.fetch) return;
    fetch(API, { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
      if (!j) return;
      var porCodigo = {};
      (j.data || []).forEach(function (p) { porCodigo[p.codigo] = p; });
      caixas.forEach(function (box) {
        if (box.hasAttribute('data-taxas')) { taxas(box, j.taxas || []); return; }
        var p = porCodigo[box.getAttribute('data-preco')];
        if (p) preco(box, p);
      });
    }).catch(function () { /* sem a API o bloco continua oculto e os botões seguem funcionando */ });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
'''

CSS_PRECO = '''<style data-site="preco-css">
  .pv { margin:14px 0 4px; padding:14px 16px; border:1px solid var(--border, #e4e7ec); border-radius:12px; background:var(--surface, #f6f7f9); }
  .pv[hidden] { display:none; } .pv-cheio { color:var(--ink-faint, #8891a0); font-size:15px; } .pv-valor { font-family:Manrope, sans-serif; font-size:26px; letter-spacing:-.02em; }
  .pv-un { color:var(--ink-soft, #5b6472); } .pv-linha { font-size:13.5px; line-height:1.5; color:var(--ink-soft, #5b6472); margin-top:6px; }
  .pv-tit { font-size:16px; margin-bottom:8px; } .pv-taxas { margin:0; padding-left:18px; font-size:14px; line-height:1.6; }
  .sp-app { display:grid; grid-template-columns:230px 1fr; gap:36px; align-items:center; margin-top:28px; } .sp-app svg { width:100%; height:auto; display:block; }
  .sp-app ol { padding-left:20px; line-height:1.7; } @media (max-width:760px) { .sp-app { grid-template-columns:1fr; max-width:260px; } }
</style>'''


def caixa_preco(codigo):
    """Marcador preenchido por /inc/precos.js com os valores lidos da Central (nenhum valor fica no HTML)."""
    return f'<div class="pv" data-preco="{codigo}" hidden></div>' if dados.venda_online(codigo) else ''


SCRIPT_PRECOS = '<script src="/inc/precos.js" defer></script>'


def bloco_app(codigo):
    nome, cor, tela, itens = dados.APPS[codigo]
    return (
        f'<section class="sp-sec alt" id="app"><div class="sp-wrap"><div class="sp-eyebrow">App incluído</div><h2>App {nome} no seu celular</h2>'
        f'<p class="sp-lead">Cada solução tem o seu próprio app, que abre direto em <strong>{tela}</strong>. Sem baixar em loja: instale pelo navegador.</p>'
        f'<div class="sp-app">{mockups.app_celular(nome, cor, tela, itens)}<div><h3>Como instalar</h3><ol>'
        '<li>Abra o sistema no navegador do celular e entre com o seu usuário.</li><li>Toque em <strong>Adicionar à tela inicial</strong> (menu do navegador).</li>'
        '<li>Pronto: o ícone fica ao lado dos seus outros apps.</li></ol>'
        '<p class="sp-lead">Gestor, técnico em campo e colaborador usam o mesmo app, cada um com as telas do seu perfil de acesso.</p></div></div></div></section>'
    )


# ---------------------------------------------------------------- bloco do pacote (incluído, telas, contratar)

CSS_PACOTE = '''<style data-site="pacote-css">
  .sp-sec { padding: 64px 0; } .sp-sec.alt { background: var(--surface, #f6f7f9); }
  .sp-wrap { max-width: 1180px; margin: 0 auto; padding: 0 28px; }
  .sp-eyebrow { font-family: Manrope, sans-serif; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--accent, #f97316); }
  .sp-sec h2 { font-family: Manrope, sans-serif; font-weight: 800; font-size: clamp(24px, 3.2vw, 32px); letter-spacing: -.02em; margin: 8px 0 0; }
  .sp-lead { color: var(--ink-soft, #5b6472); font-size: 16px; line-height: 1.6; margin-top: 10px; max-width: 64ch; }
  .sp-inclui { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-top: 28px; }
  .sp-card { background: #fff; border: 1px solid var(--border, #e4e7ec); border-radius: 12px; padding: 18px 20px; }
  .sp-card h3 { font-family: Manrope, sans-serif; font-size: 16px; margin: 0 0 6px; } .sp-card p { margin: 0; font-size: 14px; line-height: 1.55; color: var(--ink-soft, #5b6472); }
  .sp-base { margin-top: 16px; font-size: 13.5px; color: var(--ink-soft, #5b6472); }
  .sp-galeria { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 22px; margin-top: 30px; }
  .sp-galeria figure { margin: 0; } .sp-galeria svg { width: 100%; height: auto; border-radius: 10px; box-shadow: 0 18px 40px -26px rgba(15,23,42,.45); display: block; }
  .sp-galeria figcaption { margin-top: 10px; font-size: 13.5px; font-weight: 600; color: var(--ink, #0f172a); }
  .sp-cta { background: var(--navy, #0f172a); color: #fff; border-radius: 22px; padding: 44px; text-align: center; }
  .sp-cta h2 { color: #fff; } .sp-cta p { color: rgba(255,255,255,.78); margin: 10px auto 0; max-width: 58ch; line-height: 1.6; }
  .sp-cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; margin-top: 24px; }
  .sp-btn { display: inline-flex; padding: 13px 24px; border-radius: 8px; font-weight: 700; font-size: 15px; text-decoration: none; border: 1px solid rgba(255,255,255,.35); color: #fff; }
  .sp-btn.p { background: var(--accent, #f97316); border-color: var(--accent, #f97316); }
  .sp-crumb { max-width: 1180px; margin: 0 auto; padding: 14px 28px 0; font-size: 13px; color: var(--ink-soft, #5b6472); } .sp-crumb a { color: inherit; }
</style>'''


def bloco_pacote(codigo, extra_vertical=None):
    p = dados.POR_CODIGO[codigo]
    inclui = ''.join(f'<div class="sp-card"><h3>{t}</h3><p>{d}</p></div>' for t, d in p['inclui'])
    figs = ''.join(f'<figure>{mockups.gerar(m)}<figcaption>{m["legenda"]}</figcaption></figure>' for m in p['mockups'])
    vert = f' Esta página apresenta a vertical <strong>{extra_vertical}</strong>, atendida por esta solução.' if extra_vertical else ''
    href, rotulo = cta(codigo)
    if dados.venda_online(codigo):
        texto_cta = 'Contrate online, sem burocracia, ou converse com a nossa equipe para entender se é o melhor ponto de partida para a sua operação.'
        extra_btn = f'<a class="sp-btn" href="/contato.php?pacote={codigo}">Falar com vendas</a>'
    else:
        texto_cta = 'Cada locadora tem uma operação diferente. Fale com um especialista para montarmos a proposta sob medida para a sua.'
        extra_btn = ''
    partes = [
        '<!-- site:pacote -->', CSS_PACOTE, CSS_PRECO,
        f'<section class="sp-sec" id="incluido"><div class="sp-wrap"><div class="sp-eyebrow">Solução {p["nome"]}</div>',
        f'<h2>O que está incluído</h2><p class="sp-lead">{p["descricao"]}{vert}</p>',
        f'<div class="sp-inclui">{inclui}</div><p class="sp-base">Em todas as soluções: {dados.BASE_COMUM}.</p></div></section>',
        '<section class="sp-sec alt" id="telas"><div class="sp-wrap"><div class="sp-eyebrow">O sistema por dentro</div>',
        f'<h2>Veja as telas da solução {p["nome"]}</h2><p class="sp-lead">Exemplos de telas, com dados fictícios.</p>',
        f'<div class="sp-galeria">{figs}</div></div></section>', bloco_app(codigo),
        f'<section class="sp-sec" id="contratar"><div class="sp-wrap"><div class="sp-cta"><h2>Comece com a solução {p["nome"]}</h2><p>{texto_cta}</p>{caixa_preco(codigo)}{"<div class=\"pv\" data-taxas hidden></div>" if dados.venda_online(codigo) else ""}',
        f'<div class="sp-cta-btns"><a class="sp-btn p" href="{href}">{rotulo}</a><a class="sp-btn" href="/planos/#{codigo}">Comparar planos</a>{extra_btn}</div>',
        '</div></div></section>', SCRIPT_PRECOS, '<!-- /site:pacote -->',
    ]
    return ''.join(partes)


def nav_e_rodape(texto, atual, rel):
    """Troca o menu .inav e o rodapé .ifoot pelo padrão e (re)aplica o CSS do menu."""
    texto = trocar(texto, r'(?:<div class="tb" data-site="topbar">.*?</div></div>\s*)?<nav class="inav"[^>]*>.*?</nav>', nav_inav(atual), f'nav inav em {rel}')
    texto = trocar(texto, r'<footer class="ifoot"[^>]*>.*?</footer>', footer_inav(), f'footer ifoot em {rel}')
    texto = re.sub(r'<style data-site="nav-css">.*?</style>\s*', '', texto, flags=re.S)
    return texto.replace('</head>', CSS_NAV + '\n</head>', 1)


def aplicar_pagina_inav(rel):
    texto = ler(rel)
    atual = '/' + rel[:-len('index.html')] if rel.endswith('index.html') else '/' + rel
    texto = nav_e_rodape(texto, atual, rel)

    codigo = dados.PAGINA_PACOTE.get(atual)
    texto = re.sub(r'<!-- site:pacote -->.*?<!-- /site:pacote -->', '', texto, flags=re.S)
    texto = re.sub(r'<div class="sp-crumb" data-site="crumb">.*?</div>', '', texto, flags=re.S)
    if codigo:
        vertical = 'Montagem Industrial' if atual == '/software-montagem-industrial/' else None
        texto = texto.replace('<footer class="ifoot" data-site="footer">', bloco_pacote(codigo, vertical) + '<footer class="ifoot" data-site="footer">', 1)
        nome = dados.POR_CODIGO[codigo]['nome']
        if vertical:
            migalha = f'<div class="sp-crumb" data-site="crumb"><a href="/solucoes/">Soluções</a> › <a href="/software-gestao-de-servicos/">Serviços</a> › {vertical}</div>'
            texto = texto.replace('</nav>', '</nav>' + migalha, 1)
        # Botões de "Teste Grátis" (removido do produto) viram a contratação do pacote.
        texto = re.sub(r'<a href="/lp\.html\?type=[a-z]+" class="btn btn-primary">Teste Grátis Agora</a>',
                       f'<a href="{cta(codigo)[0]}" class="btn btn-primary">{cta(codigo)[1]}</a>', texto)
    gravar(rel, texto)


# ---------------------------------------------------------------- hub /solucoes/ e /planos/

CSS_HUB = '''<style>
  :root { --paper:#fff; --surface:#f6f7f9; --ink:#0f172a; --ink-soft:#5b6472; --ink-faint:#8891a0; --border:#e4e7ec; --accent:#f97316; --navy:#0f172a; --navy-2:#1a2540; }
  * { box-sizing: border-box; } body { margin:0; font-family:"Inter","Segoe UI",system-ui,sans-serif; color:var(--ink); background:var(--paper); -webkit-font-smoothing:antialiased; }
  h1,h2,h3 { font-family:"Manrope","Inter",sans-serif; font-weight:800; letter-spacing:-.02em; margin:0; text-wrap:balance; } p { margin:0; } a { color:inherit; }
  .wrap { max-width:1180px; margin:0 auto; padding:0 28px; }
  .eyebrow { font-family:"Manrope",sans-serif; font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--accent); }
  .inav { position:sticky; top:0; z-index:50; background:rgba(255,255,255,.92); backdrop-filter:blur(10px); border-bottom:1px solid var(--border); }
  .inav-wrap { max-width:1180px; margin:0 auto; padding:0 28px; height:72px; display:flex; align-items:center; justify-content:space-between; }
  .inav-logo { display:flex; align-items:center; gap:9px; font-family:"Manrope",sans-serif; font-weight:800; font-size:19px; text-decoration:none; color:var(--ink); }
  .inav-logo-mark { width:30px; height:30px; border-radius:8px; background:var(--navy); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:15px; flex:none; }
  .inav-links { display:flex; align-items:center; gap:34px; } .inav-links a { text-decoration:none; color:var(--ink-soft); font-size:14.5px; font-weight:600; } .inav-links a:hover { color:var(--ink); }
  .inav-cta { background:var(--navy); color:#fff; padding:10px 20px; border-radius:7px; text-decoration:none; font-weight:700; font-size:14px; } .inav-cta:hover { background:var(--navy-2); }
  .inav-dd { position:relative; padding:24px 0; }
  .inav-dd-menu { position:absolute; top:100%; left:-16px; min-width:260px; background:#fff; border:1px solid var(--border); border-radius:10px; box-shadow:0 16px 36px -18px rgba(15,23,42,.35); padding:6px; opacity:0; visibility:hidden; transform:translateY(-4px); transition:all .15s; }
  .inav-dd:hover .inav-dd-menu, .inav-dd:focus-within .inav-dd-menu { opacity:1; visibility:visible; transform:none; }
  .inav-dd-menu a { display:block; padding:10px 12px; border-radius:6px; font-size:14px; } .inav-dd-menu a:hover { background:var(--surface); color:var(--ink); }
  @media (max-width:860px) { .inav-links { display:none; } }
  .hero { padding:72px 0 40px; background:linear-gradient(180deg,#eef1f5 0%,#fff 100%); }
  .hero h1 { font-size:clamp(30px,4vw,46px); line-height:1.12; margin-top:14px; } .hero .desc { font-size:17px; color:var(--ink-soft); line-height:1.65; margin-top:16px; max-width:62ch; font-weight:500; }
  .plans { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; padding:24px 0 40px; }
  .plan { background:#fff; border:1px solid var(--border); border-radius:16px; padding:0; display:flex; flex-direction:column; overflow:hidden; }
  .plan-shot { background:var(--surface); border-bottom:1px solid var(--border); padding:14px; } .plan-shot svg { width:100%; height:auto; display:block; border-radius:8px; }
  .plan-body { padding:22px 24px 26px; display:flex; flex-direction:column; flex:1; }
  .plan h3 { font-size:24px; } .plan-desc { color:var(--ink-soft); font-size:15px; line-height:1.55; margin-top:8px; }
  .plan ul { list-style:none; padding:0; margin:18px 0 22px; display:grid; gap:9px; flex:1; } .plan li { font-size:14.5px; line-height:1.45; padding-left:24px; position:relative; }
  .plan li::before { content:"✓"; position:absolute; left:0; color:var(--accent); font-weight:800; }
  .plan-actions { display:grid; gap:10px; }
  @media (min-width:981px) { .plans > .plan:last-child:nth-child(3n+1) { grid-column:2; } }
  .btn { display:inline-flex; justify-content:center; padding:13px 22px; border-radius:8px; font-weight:700; font-size:15px; text-decoration:none; border:1px solid transparent; }
  .btn-primary { background:var(--navy); color:#fff; } .btn-primary:hover { background:var(--navy-2); } .btn-ghost { border-color:var(--border); color:var(--ink); } .btn-ghost:hover { background:var(--surface); }
  .comp { overflow-x:auto; margin:8px 0 56px; } .comp table { width:100%; border-collapse:collapse; min-width:760px; font-size:14px; }
  .comp th, .comp td { padding:12px 10px; border-bottom:1px solid var(--border); text-align:center; } .comp th:first-child, .comp td:first-child { text-align:left; }
  .comp thead th { font-family:"Manrope",sans-serif; font-size:13px; background:var(--surface); } .comp td.s { color:var(--accent); font-weight:800; } .comp td.n { color:var(--ink-faint); }
  .note { background:var(--surface); border-radius:14px; padding:22px 26px; color:var(--ink-soft); font-size:15px; line-height:1.6; margin-bottom:64px; }
  .como { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin:28px 0 56px; } .como div { border:1px solid var(--border); border-radius:14px; padding:22px; }
  .como b { display:inline-flex; width:30px; height:30px; border-radius:50%; background:var(--navy); color:#fff; align-items:center; justify-content:center; margin-bottom:10px; }
  footer.ifoot { border-top:1px solid var(--border); padding:44px 0; } .ifoot-wrap { max-width:1180px; margin:0 auto; padding:0 28px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px; }
  .ifoot-links { display:flex; flex-wrap:wrap; gap:24px; font-size:13.5px; color:var(--ink-soft); } .ifoot-links a { text-decoration:none; } .ifoot-copy { font-size:13px; color:var(--ink-faint); } .ifoot-legal { font-size:11.5px; margin-top:4px; }
  @media (max-width:980px) { .plans { grid-template-columns:repeat(2,1fr); } .como { grid-template-columns:1fr; } } @media (max-width:640px) { .plans { grid-template-columns:1fr; } }
</style>'''


def pagina_base(titulo, descricao, corpo, atual):
    return (
        '<!DOCTYPE html>\n<html lang="pt-BR">\n<head>\n<meta charset="UTF-8">\n<meta name="viewport" content="width=device-width, initial-scale=1.0">\n'
        f'<title>{titulo}</title>\n<meta name="description" content="{descricao}">\n'
        '<link rel="icon" type="image/svg+xml" href="/nova/assets/favicon.svg?v=6" />\n'
        '<link rel="preconnect" href="https://fonts.googleapis.com">\n<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>\n'
        '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">\n'
        f'{CSS_HUB}\n{CSS_PRECO}\n{CSS_NAV}\n</head>\n<body>\n{nav_inav(atual)}\n{corpo}\n{footer_inav()}\n{SCRIPT_PRECOS}\n</body>\n</html>\n'
    )


def cards_pacotes(com_shot=True):
    out = []
    for p in PACOTES:
        shot = f'<div class="plan-shot">{mockups.gerar(p["mockups"][0])}</div>' if com_shot else ''
        lis = ''.join(f'<li>{t}</li>' for t, _ in p['inclui'][:5])
        out.append(
            f'<article class="plan" id="{p["codigo"]}">{shot}<div class="plan-body"><h3>{p["nome"]}</h3><p class="plan-desc">{p["frase"]} {p["descricao"]}</p>'
            f'<ul>{lis}</ul>{caixa_preco(p["codigo"])}<div class="plan-actions"><a class="btn btn-primary" href="{cta(p["codigo"])[0]}" data-pacote="{p["codigo"]}">{cta(p["codigo"])[1]}</a>'
            f'<a class="btn btn-ghost" href="{p["pagina"]}">Conhecer a solução</a></div></div></article>')
    return ''.join(out)


def pagina_solucoes():
    corpo = (
        '<section class="hero"><div class="wrap"><span class="eyebrow">Soluções Oravel</span><h1>Escolha a solução certa para a sua operação</h1>'
        '<p class="desc">Sete soluções, cada uma completa no que se propõe. Comece pela que resolve o seu dia a dia e contrate online ou com a nossa equipe.</p></div></section>'
        f'<main class="wrap"><div class="plans">{cards_pacotes()}</div>'
        '<div class="como"><div><b>1</b><h3>Escolha a solução</h3><p>Veja as telas e o que está incluído em cada uma.</p></div>'
        '<div><b>2</b><h3>Contrate</h3><p>Online, em poucos minutos, ou com apoio da nossa equipe comercial.</p></div>'
        '<div><b>3</b><h3>Comece a usar</h3><p>Acesso liberado e treinamento na Academia Oravel.</p></div></div>'
        '<p class="note">Precisa de uma combinação diferente? Fale com a nossa equipe em <a href="/contato.php">contato</a> e montamos o contrato sob medida.</p></main>'
    )
    return pagina_base('Soluções — ERP Locadoras, Manutenção, Serviços, Logística, Estoque e Vendas | Oravel',
                       'Conheça as soluções Oravel: ERP Locadoras, Manutenção, Serviços, Logística, Estoque e Vendas. Contrate só o que a sua operação precisa.', corpo, '/solucoes/')


def pagina_pacote_nova(codigo):
    """Página de uma solução que não tinha página própria (Departamento Pessoal): hero + bloco padrão do pacote."""
    p = dados.POR_CODIGO[codigo]
    href, rotulo = cta(codigo)
    corpo = (
        f'<section class="hero"><div class="wrap"><span class="eyebrow">Solução {p["nome"]}</span><h1>{p["frase"]}</h1>'
        f'<p class="desc">{p["descricao"]}</p><div style="margin-top:26px;display:flex;gap:12px;flex-wrap:wrap"><a class="btn btn-primary" href="{href}">{rotulo}</a>'
        '<a class="btn btn-ghost" href="/planos/">Comparar planos</a></div></div></section>' + bloco_pacote(codigo))
    return pagina_base(f'Software de {p["nome"]} | Oravel', p['descricao'], corpo, p['pagina'])


def pagina_planos():
    cab = ''.join(f'<th>{p["nome"]}</th>' for p in PACOTES)
    linhas = ''.join(
        f'<tr><td>{rot}</td>' + ''.join(f'<td class="s">✓</td>' if p['codigo'] in quem else '<td class="n">—</td>' for p in PACOTES) + '</tr>'
        for rot, quem in dados.MATRIZ)
    corpo = (
        '<section class="hero"><div class="wrap"><span class="eyebrow">Planos</span><h1>Contrate a solução que a sua operação precisa</h1>'
        '<p class="desc">O valor de cada solução aparece na tela de contratação. Compare abaixo o que cada uma inclui.</p></div></section>'
        f'<main class="wrap"><div class="plans">{cards_pacotes(com_shot=False)}</div>'
        f'<h2 style="margin:8px 0 14px">Compare o que está incluído</h2><div class="comp"><table><thead><tr><th>Recurso</th>{cab}</tr></thead><tbody>{linhas}</tbody></table></div>'
        '<p class="note">Dúvida sobre qual solução escolher, ou precisa de uma combinação diferente? Fale com a nossa equipe em <a href="/contato.php">contato</a> e montamos o contrato sob medida.</p></main>'
    )
    return pagina_base('Planos | Oravel', 'Compare as soluções Oravel e contrate online: ERP Locadoras, Manutenção, Serviços, Logística, Estoque e Vendas.', corpo, '/planos/')



# ---------------------------------------------------------------- Academia Oravel + apps por solução (home e /academia/)

CSS_ACADEMIA = """<style data-site="academia-css">
  .ac-sec { padding: 80px 0; } .ac-dark { background: linear-gradient(160deg, #0f172a 0%, #1a2540 100%); color: #fff; }
  .ac-wrap { max-width: 1180px; margin: 0 auto; padding: 0 28px; }
  .ac-eyebrow { font-family: Manrope, sans-serif; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #f97316; }
  .ac-sec h2 { font-family: Manrope, sans-serif; font-weight: 800; font-size: clamp(28px, 4vw, 44px); letter-spacing: -.02em; line-height: 1.1; margin: 10px 0 0; }
  .ac-sub { font-family: Manrope, sans-serif; font-size: clamp(20px, 2.4vw, 26px); font-weight: 700; color: #fdba74; margin-top: 12px; }\n  .ac-lead { font-size: 17px; line-height: 1.65; margin-top: 14px; max-width: 58ch; color: rgba(255,255,255,.78); }
  .ac-hero { display: grid; grid-template-columns: 1fr 1.1fr; gap: 48px; align-items: center; }
  .ac-hero svg, .ac-shots svg { width: 100%; height: auto; display: block; border-radius: 12px; box-shadow: 0 30px 60px -28px rgba(0,0,0,.7); }
  .ac-chips { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 26px; }
  .ac-chip { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12); border-radius: 12px; padding: 14px 16px; }
  .ac-chip b { display: block; font-family: Manrope, sans-serif; font-size: 15px; } .ac-chip span { font-size: 13px; color: rgba(255,255,255,.7); line-height: 1.5; }
  .ac-btns { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 28px; }
  .ac-btn { display: inline-flex; padding: 13px 24px; border-radius: 8px; font-weight: 700; font-size: 15px; text-decoration: none; border: 1px solid rgba(255,255,255,.35); color: #fff; }
  .ac-btn.p { background: #f97316; border-color: #f97316; } .ac-btn.d { color: #0f172a; border-color: #e4e7ec; }
  .ac-shots { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-top: 56px; }
  .ac-shots figure { margin: 0; } .ac-shots figcaption { margin-top: 12px; font-size: 14px; color: rgba(255,255,255,.85); line-height: 1.5; } .ac-shots figcaption b { display: block; color: #fff; font-family: Manrope, sans-serif; }
  .ac-light { background: var(--surface, #f6f7f9); color: var(--ink, #0f172a); } .ac-light .ac-lead { color: var(--ink-soft, #5b6472); }
  .ac-phones { display: grid; grid-template-columns: repeat(7, 1fr); gap: 18px; margin-top: 44px; }
  .ac-phone { text-align: center; text-decoration: none; color: inherit; } .ac-phone svg { width: 100%; max-width: 190px; height: auto; display: block; margin: 0 auto; filter: drop-shadow(0 22px 24px rgba(15,23,42,.28)); transition: transform .2s; }
  .ac-phone:hover svg { transform: translateY(-6px); } .ac-phone b { display: block; margin-top: 12px; font-family: Manrope, sans-serif; font-size: 15px; } .ac-phone span { font-size: 12.5px; color: var(--ink-soft, #5b6472); }
  @media (max-width: 980px) { .ac-hero { grid-template-columns: 1fr; } .ac-phones { grid-template-columns: repeat(3, 1fr); } .ac-shots { grid-template-columns: 1fr; } }
  @media (max-width: 560px) { .ac-phones { grid-template-columns: repeat(2, 1fr); } .ac-chips { grid-template-columns: 1fr; } .ac-sec { padding: 56px 0; } }
</style>"""


def bloco_academia():
    chips = [('Cursos do seu plano', 'Cada solução abre só os cursos que você contratou: nada de conteúdo que não serve para a sua operação.'),
             ('Aulas passo a passo', 'Telas reais do sistema, caminhos de menu e dicas de quem usa no dia a dia.'),
             ('Provas e certificado', 'Prova a cada aula e certificado de conclusão para a sua equipe.'),
             ('Configure sozinho', 'Treinamentos de WhatsApp, e-mail, cobrança automática e notas fiscais, sem depender de ninguém.')]
    chips_html = ''.join(f'<div class="ac-chip"><b>{t}</b><span>{d}</span></div>' for t, d in chips)
    return (
        '<!-- site:academia -->'
        f'<section class="ac-sec ac-dark" id="academia" data-site="academia"><div class="ac-wrap"><div class="ac-hero"><div>'
        '<div class="ac-eyebrow">Academia Oravel</div><h2>Aprendizado que transforma a sua operação</h2><p class="ac-sub">Aprendizado que se transforma em resultados.</p>'
        '<p class="ac-lead">Sua equipe aprende a usar o sistema dentro do próprio sistema: aulas curtas, provas e certificado. Mais de 60 aulas, organizadas por solução, para quem está começando e para quem já domina.</p>'
        f'<div class="ac-chips">{chips_html}</div>'
        '<div class="ac-btns"><a class="ac-btn p" href="/solucoes/">Escolher a minha solução</a><a class="ac-btn" href="/contato.php">Falar com um especialista</a></div></div>'
        f'<div>{mockups.academia_catalogo()}</div></div>'
        '<div class="ac-shots">'
        f'<figure>{mockups.academia_aula()}<figcaption><b>Aula passo a passo</b>Do caminho do menu até o resultado, com dicas e alertas.</figcaption></figure>'
        f'<figure>{mockups.academia_prova()}<figcaption><b>Prova a cada aula</b>Perguntas de múltipla escolha para fixar o que foi aprendido.</figcaption></figure>'
        f'<figure>{mockups.academia_certificado()}<figcaption><b>Certificado de conclusão</b>Com código de verificação, para cada pessoa que conclui o curso.</figcaption></figure>'
        '</div></div></section><!-- /site:academia -->'
    )


def bloco_apps():
    cards = ''.join(
        f'<a class="ac-phone" href="{p["pagina"]}#app">{mockups.app_celular(*dados.APPS[p["codigo"]])}<b>{dados.APPS[p["codigo"]][0]}</b><span>Abre em {dados.APPS[p["codigo"]][2]}</span></a>'
        for p in PACOTES)
    return (
        '<!-- site:apps -->'
        '<section class="ac-sec ac-light" id="apps" data-site="apps"><div class="ac-wrap"><div class="ac-eyebrow">App incluído em todas as soluções</div>'
        '<h2>Cada solução tem o seu próprio app no celular</h2>'
        '<p class="ac-lead">Instale pelo navegador, sem loja de aplicativos. O app leva o nome da sua solução e abre direto na tela que você mais usa. Gestor, técnico e colaborador entram no mesmo app e veem só o que o perfil de cada um permite.</p>'
        f'<div class="ac-phones">{cards}</div></div></section><!-- /site:apps -->'
    )


def blocos_academia_e_apps():
    return CSS_ACADEMIA + bloco_academia() + bloco_apps()


def pagina_academia():
    corpo = blocos_academia_e_apps() + (
        '<section class="ac-sec"><div class="ac-wrap" style="text-align:center"><div class="ac-eyebrow">Pronto para começar?</div>'
        '<h2 style="color:var(--ink)">A Academia vem com a sua solução</h2>'
        '<div class="ac-btns" style="justify-content:center"><a class="ac-btn p" href="/planos/">Ver os planos</a><a class="ac-btn d" href="/contato.php">Falar com um especialista</a></div></div></section>')
    return pagina_base('Academia Oravel — aprendizado que transforma | Oravel',
                       'A Academia Oravel ensina a sua equipe a usar o sistema: aulas passo a passo, provas e certificado, organizadas por solução. Cada solução tem ainda o seu app no celular.', corpo, '/academia/')



# ---------------------------------------------------------------- IA em destaque (home e /inteligencia-artificial/)

CSS_IA = """<style data-site="ia-css">
  .ia-sec { position: relative; overflow: hidden; padding: 96px 0; color: #fff; background:
    radial-gradient(900px 420px at 85% 0%, rgba(99,102,241,.35), transparent 60%), radial-gradient(700px 400px at 5% 100%, rgba(249,115,22,.28), transparent 60%), #070b14; }
  .ia-sec::before { content: ""; position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px); background-size: 46px 46px; mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 75%); -webkit-mask-image: radial-gradient(ellipse at 50% 30%, #000 30%, transparent 75%); pointer-events: none; }
  .ia-wrap { position: relative; max-width: 1180px; margin: 0 auto; padding: 0 28px; }
  .ia-tag { display: inline-flex; align-items: center; gap: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; letter-spacing: .14em; text-transform: uppercase; color: #c7d2fe; border: 1px solid rgba(165,180,252,.35); background: rgba(99,102,241,.12); padding: 7px 14px; border-radius: 999px; }
  .ia-tag i { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 10px #22c55e; }
  .ia-sec h2 { font-family: Manrope, sans-serif; font-weight: 800; font-size: clamp(44px, 9vw, 104px); line-height: .98; letter-spacing: -.04em; margin: 22px 0 0; text-transform: uppercase;
    background: linear-gradient(95deg, #ffffff 0%, #fdba74 40%, #f97316 62%, #a5b4fc 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .ia-sub { font-family: Manrope, sans-serif; font-size: clamp(20px, 2.6vw, 30px); font-weight: 700; margin-top: 18px; color: #e2e8f0; max-width: 28ch; line-height: 1.25; }
  .ia-lead { font-size: 17px; line-height: 1.7; color: rgba(226,232,240,.78); max-width: 62ch; margin-top: 16px; }
  .ia-trust { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
  .ia-trust span { font-size: 13px; font-weight: 600; color: #e2e8f0; border: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.05); padding: 8px 14px; border-radius: 10px; }
  .ia-shots { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-top: 54px; }
  .ia-shots figure { margin: 0; } .ia-shots svg { width: 100%; height: auto; display: block; border-radius: 12px; box-shadow: 0 0 0 1px rgba(255,255,255,.1), 0 30px 70px -30px rgba(99,102,241,.7); }
  .ia-shots figcaption { margin-top: 12px; font-size: 14px; color: rgba(226,232,240,.82); line-height: 1.5; } .ia-shots figcaption b { display: block; color: #fff; font-family: Manrope, sans-serif; font-size: 16px; }
  .ia-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-top: 40px; }
  .ia-card { border: 1px solid rgba(255,255,255,.13); background: linear-gradient(160deg, rgba(255,255,255,.08), rgba(255,255,255,.02)); border-radius: 14px; padding: 20px; backdrop-filter: blur(4px); }
  .ia-card .n { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; color: #fdba74; letter-spacing: .1em; }
  .ia-card b { display: block; margin-top: 8px; font-family: Manrope, sans-serif; font-size: 16px; } .ia-card span { display: block; margin-top: 6px; font-size: 13.5px; line-height: 1.55; color: rgba(226,232,240,.72); }
  .ia-btns { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 34px; }
  .ia-btn { display: inline-flex; padding: 14px 26px; border-radius: 10px; font-weight: 700; font-size: 15px; text-decoration: none; border: 1px solid rgba(255,255,255,.3); color: #fff; } .ia-btn.p { background: #f97316; border-color: #f97316; box-shadow: 0 10px 30px -10px rgba(249,115,22,.8); }
  @media (max-width: 980px) { .ia-shots { grid-template-columns: 1fr; } .ia-grid { grid-template-columns: repeat(2, 1fr); } } @media (max-width: 560px) { .ia-grid { grid-template-columns: 1fr; } .ia-sec { padding: 64px 0; } }
</style>"""

IA_RECURSOS = [
    ('Manutenção preventiva', 'Aponta o que está atrasado, o que quebrou depois da preventiva e onde o tempo entre falhas está caindo.'),
    ('Retrabalho em corretivas', 'Mostra quantas vezes você voltou ao mesmo equipamento e o motivo mais comum.'),
    ('Diagnóstico de avarias', 'Lê as fotos do dano e sugere local, tipo, gravidade e causa provável para o dossiê.'),
    ('Saúde do estoque', 'Destaca materiais críticos, quantos dias faltam para acabar e itens parados.'),
    ('Rotas do dia', 'Resume as rotas por veículo, com distância e custo calculados pelo sistema.'),
    ('Risco de perda de leads', 'No CRM, avalia quais negociações estão em risco e o que fazer a seguir.'),
    ('Avaliação de propostas', 'Revisa coerência e clareza e estima a chance de fechamento antes do envio.'),
    ('Leitura de OS em papel', 'Fotografe a ordem de serviço de terceiros e os campos já vêm preenchidos.'),
]


def bloco_ia(titulo_h1=False):
    cards = ''.join(f'<div class="ia-card"><div class="n">{i + 1:02d}</div><b>{t}</b><span>{d}</span></div>' for i, (t, d) in enumerate(IA_RECURSOS))
    return (
        '<!-- site:ia -->'
        '<section class="ia-sec" id="ia" data-site="ia"><div class="ia-wrap"><span class="ia-tag"><i></i>Inteligência artificial no sistema</span>'
        '<h2>IA dentro do sistema</h2><p class="ia-sub">Menos achismo. Mais decisão com dados.</p>'
        '<p class="ia-lead">Os números vêm do próprio sistema, calculados com regra e sem invenção. A inteligência artificial lê esses números, destaca o que importa e diz o que fazer primeiro. Nas análises, ela nunca inventa dado: ela interpreta o que o sistema já mediu.</p>'
        '<div class="ia-trust"><span>Números calculados pelo sistema</span><span>IA só interpreta e prioriza</span><span>Dados da sua empresa, isolados das demais</span></div>'
        '<div class="ia-shots">'
        f'<figure>{mockups.ia_manutencao()}<figcaption><b>Manutenção que se antecipa</b>Prioridades e causas prováveis, equipamento por equipamento.</figcaption></figure>'
        f'<figure>{mockups.ia_proposta()}<figcaption><b>Proposta avaliada antes de enviar</b>Nota de coerência, clareza e chance de fechamento.</figcaption></figure>'
        f'<figure>{mockups.ia_avaria()}<figcaption><b>Avaria diagnosticada pela foto</b>Dano, gravidade e causa para o dossiê do equipamento.</figcaption></figure>'
        f'</div><div class="ia-grid">{cards}</div>'
        '<div class="ia-btns"><a class="ia-btn p" href="/inteligencia-artificial/">Ver tudo que a IA faz</a><a class="ia-btn" href="/contato.php">Falar com um especialista</a></div>'
        '</div></section><!-- /site:ia -->'
    )


def pagina_ia():
    corpo = CSS_IA + bloco_ia().replace('<a class="ia-btn p" href="/inteligencia-artificial/">Ver tudo que a IA faz</a>', '<a class="ia-btn p" href="/solucoes/">Escolher a minha solução</a>')
    return pagina_base('Inteligência artificial no sistema | Oravel',
                       'A Oravel usa inteligência artificial para priorizar manutenção, diagnosticar avarias, avaliar propostas e cuidar do estoque: números calculados pelo sistema e IA que interpreta.', corpo, '/inteligencia-artificial/')



# ---------------------------------------------------------------- visual de tecnologia (home e demais páginas)

TECH_HERO_BG = ('radial-gradient(900px 420px at 85% 0%, rgba(99,102,241,.38), transparent 60%), radial-gradient(700px 420px at 0% 100%, rgba(249,115,22,.26), transparent 60%), #070b14')
TECH_GRID = ('linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px)')

# Faixa de topo (hero) das páginas internas no mesmo estilo escuro da home: fundo noturno, grade e brilho.
CSS_TECH_INTERNAS = (
    f'.hero {{ position:relative; overflow:hidden; background:{TECH_HERO_BG} !important; color:#fff; }} '
    f'.hero::before {{ content:""; position:absolute; inset:0; background-image:{TECH_GRID}; background-size:46px 46px; '
    'mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); -webkit-mask-image:radial-gradient(ellipse at 50% 20%, #000 25%, transparent 75%); pointer-events:none; } '
    '.hero > * { position:relative; } .hero h1, .hero h1 * { color:#fff !important; } .hero .desc, .hero p.desc { color:rgba(226,232,240,.82) !important; } '
    '.hero .eyebrow { color:#fdba74; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; } .hero .slogan { color:#fdba74 !important; } '
    '.hero .btn-primary { background:#f97316 !important; border-color:#f97316 !important; color:#fff !important; box-shadow:0 10px 30px -12px rgba(249,115,22,.8); } '
    '.hero .btn-ghost { background:transparent !important; color:#fff !important; border-color:rgba(255,255,255,.3) !important; } '
    '.hero .hero-visual { background:rgba(255,255,255,.06) !important; border-color:rgba(255,255,255,.14) !important; box-shadow:0 30px 70px -30px rgba(99,102,241,.7); } '
    '.hero .status-pill { background:rgba(34,197,94,.16) !important; color:#86efac !important; }'
)
# A página de contato usa .chero no lugar de .hero: mesmo visual escuro.
CSS_TECH_INTERNAS += ' ' + CSS_TECH_INTERNAS.replace('.hero', '.chero') + ' .chero p { color:rgba(226,232,240,.82) !important; }'
CSS_NAV = CSS_NAV.replace('</style>', '  ' + CSS_TECH_INTERNAS + '\n</style>')

CSS_TECH_HOME = """<style data-site="hero-tech-css">
  .th-hero { position: relative; overflow: hidden; color: #fff; padding: 84px 0 64px; background: __BG__; }
  .th-hero::before { content: ""; position: absolute; inset: 0; background-image: __GRID__; background-size: 46px 46px; mask-image: radial-gradient(ellipse at 50% 25%, #000 25%, transparent 75%); -webkit-mask-image: radial-gradient(ellipse at 50% 25%, #000 25%, transparent 75%); pointer-events: none; }
  .th-wrap { position: relative; max-width: 1180px; margin: 0 auto; padding: 0 28px; }
  .th-grid { display: grid; grid-template-columns: 1.05fr 1fr; gap: 48px; align-items: center; }
  .th-tag { display: inline-flex; align-items: center; gap: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; letter-spacing: .14em; text-transform: uppercase; color: #c7d2fe; border: 1px solid rgba(165,180,252,.35); background: rgba(99,102,241,.12); padding: 7px 14px; border-radius: 999px; }
  .th-tag i { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; box-shadow: 0 0 10px #22c55e; }
  .th-h { font-family: Manrope, sans-serif; font-weight: 800; font-size: clamp(30px, 4.2vw, 50px); line-height: 1.08; letter-spacing: -.035em; margin: 20px 0 0;
    background: linear-gradient(95deg, #ffffff 0%, #fdba74 55%, #f97316 100%); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .th-copy { margin-top: 26px; } .th-copy p { color: rgba(226,232,240,.82) !important; font-size: 17px; line-height: 1.7; margin: 0 0 14px; }
  .th-copy p:first-child { font-family: Manrope, sans-serif; font-size: 22px !important; font-weight: 800; color: #fdba74 !important; margin-bottom: 10px; }
  .th-btns { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 26px; }
  .th-btn { display: inline-flex; padding: 14px 26px; border-radius: 10px; font-weight: 700; font-size: 15px; text-decoration: none; border: 1px solid rgba(255,255,255,.3); color: #fff; } .th-btn.p { background: #f97316; border-color: #f97316; box-shadow: 0 10px 30px -10px rgba(249,115,22,.8); }
  .th-visual { position: relative; padding-bottom: 36px; } .th-visual .th-main svg { width: 100%; height: auto; display: block; border-radius: 12px; box-shadow: 0 0 0 1px rgba(255,255,255,.12), 0 40px 80px -30px rgba(99,102,241,.75); }
  .th-visual .th-phone { position: absolute; right: -8px; bottom: -8px; width: 34%; } .th-visual .th-phone svg { width: 100%; height: auto; display: block; filter: drop-shadow(0 24px 30px rgba(0,0,0,.6)); }
  .th-chip { position: absolute; font-size: 12.5px; font-weight: 700; color: #fff; background: rgba(15,23,42,.85); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: 8px 14px; backdrop-filter: blur(6px); }
  .th-chip i { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; background: #22c55e; box-shadow: 0 0 8px #22c55e; }
  .th-stats { position: relative; display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-top: 56px; }
  .th-stat { border: 1px solid rgba(255,255,255,.13); background: linear-gradient(160deg, rgba(255,255,255,.08), rgba(255,255,255,.02)); border-radius: 14px; padding: 18px 20px; }
  .th-stat b { display: block; font-family: Manrope, sans-serif; font-size: 34px; letter-spacing: -.02em; color: #fff; } .th-stat span { font-size: 13.5px; color: rgba(226,232,240,.75); }
  .th-video-head { background: #070b14; color: #fff; text-align: center; padding: 34px 28px 22px; font-family: Manrope, sans-serif; } .th-video-head .e { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; letter-spacing: .14em; text-transform: uppercase; color: #fdba74; } .th-video-head h2 { font-size: clamp(22px, 3vw, 32px); margin: 8px 0 0; font-weight: 800; letter-spacing: -.02em; }
  @media (max-width: 980px) { .th-grid { grid-template-columns: 1fr; } .th-stats { grid-template-columns: repeat(2, 1fr); } .th-visual { max-width: 560px; margin: 0 auto; } }
  @media (max-width: 560px) { .th-hero { padding: 56px 0 44px; } .th-chip { display: none; } }
</style>""".replace('__BG__', TECH_HERO_BG).replace('__GRID__', TECH_GRID)


def bloco_hero_tech(texto_html):
    """Primeira dobra da home: título de tecnologia + o texto de boas-vindas de sempre + produto (painel e celular) + números."""
    painel = mockups.gerar(dados.POR_CODIGO['locadora']['mockups'][1])
    celular = mockups.app_celular(*dados.APPS['manutencao'])
    stats = [('7', 'soluções, cada uma com o seu app'), ('8', 'recursos de IA no sistema'), ('60+', 'aulas na Academia Oravel'), ('1', 'base única para contratos, OS, frota, estoque, comercial e financeiro')]
    stats_html = ''.join(f'<div class="th-stat"><b>{n}</b><span>{t}</span></div>' for n, t in stats)
    return (
        '<!-- site:hero-tech -->' + CSS_TECH_HOME +
        '<section class="th-hero" data-site="hero-tech"><div class="th-wrap"><div class="th-grid"><div>'
        '<span class="th-tag"><i></i>Plataforma de gestão com IA</span>'
        '<p class="th-h">Gestão que se encaixa na sua empresa, com inteligência artificial</p>'
        f'<div class="th-copy"><!-- hero-text -->{texto_html}<!-- /hero-text --></div>'
        '<div class="th-btns"><a class="th-btn p" href="https://oravel.com.br/contato.php">Solicite uma apresentação</a><a class="th-btn" href="https://oravel.com.br/solucoes/">Conheça as soluções</a></div></div>'
        f'<div class="th-visual"><div class="th-main">{painel}</div><div class="th-phone">{celular}</div>'
        '<span class="th-chip" style="top:-18px;left:24px"><i></i>IA analisando a sua operação</span><span class="th-chip" style="bottom:70px;left:-14px"><i></i>Academia Oravel</span></div></div>'
        f'<div class="th-stats">{stats_html}</div></div></section>'
        '<div class="th-video-head"><div class="e">Oravel em campo</div><h2>Veja a plataforma na operação de quem usa</h2></div>'
        '<!-- /site:hero-tech -->'
    )


# ---------------------------------------------------------------- menu "onav" (nav-fragment.html, usado por .php e gestao-locadoras)

def nav_fragment():
    return f'<style>{TOPBAR_CSS}{CSS_HDR}</style>' + nav_inav(None) + '\n'


def aplicar_home():
    rel = 'index.html'
    t = ler(rel)
    # Faixa preta: fica só o WhatsApp (a fileira de "frentes/segmentos" sai; o menu Soluções a substitui)
    t = trocar(t, r'\s*<div class="topbar-seg".*?</div>', '', 'fileira de frentes do topbar', obrigatorio=False)
    # Mesma mensagem dos planos na faixa preta da home (que tem CSS próprio: .topbar-in)
    t = re.sub(r'<span class="tb-msg"[^>]*>.*?</span>\s*', '', t, flags=re.S)
    msg = MSG_PLANOS.replace('href="/planos/"', 'href="https://oravel.com.br/planos/"').replace('class="tb-msg"', 'class="tb-msg" style="font-size:12px"')
    t = trocar(t, r'<div class="topbar-in">\s*', '<div class="topbar-in">\n      ' + msg + '\n      ', 'topbar da home')
    t = t.replace('.topbar-wa {', '.tb-msg a { color:#f97316; font-weight:700; margin-left:6px; } .tb-msg b { color:#fff; } @media (max-width:760px) { .topbar-wa { display:none !important; } .topbar-in { height:auto !important; padding:6px 16px !important; } }\n    .topbar-wa {', 1) if '.tb-msg a {' not in t else t
    dd = ''.join(f'<a href="https://oravel.com.br{p["pagina"]}">{p["nome"]}</a>' for p in PACOTES)
    nav = (f'<div class="nav-links" data-site="nav">\n        <div class="nav-dropdown">\n          <a href="https://oravel.com.br/solucoes/" class="nav-dropdown-btn" aria-haspopup="true">Soluções</a>\n'
           f'          <div class="dropdown-menu">{dd}</div>\n        </div>\n        <a href="https://oravel.com.br/planos/">Planos</a>\n'
           '        <a href="https://oravel.com.br/sobre/" class="nav-sobre">Sobre Nós</a>\n        <a href="https://oravel.com.br/contato.php">Contato</a>\n      </div>\n      ')
    t = trocar(t, r'<div class="nav-links"[^>]*>.*?</div>\s*(?=<a href="https://oravel\.com\.br/contato\.php" class="cta-btn">Falar com um Especialista</a>\s*</nav>)', nav, 'nav da home')

    cards = ''.join(
        f'<a class="frente-card" href="https://oravel.com.br{p["pagina"]}"><div class="sp-mini">{mockups.gerar(p["mockups"][0])}</div>'
        f'<h3>{p["nome"]}</h3><p>{p["frase"]} {p["descricao"]}</p><span class="frente-go">Conhecer →</span></a>' for p in PACOTES)
    secao = ('<section class="frentes-section" id="solucoes" aria-labelledby="solucoes-t" data-site="solucoes">\n    <div class="wrap">\n'
             '      <div class="frentes-eyebrow">Soluções</div>\n      <h2 id="solucoes-t">Escolha a solução certa para a sua operação</h2>\n'
             '      <p class="frentes-lead">Sete soluções, cada uma com as telas e os módulos que o dia a dia realmente usa. Contrate só o que precisa.</p>\n'
             '      <style>.sp-mini{margin:-4px -4px 14px;border-radius:10px;overflow:hidden;background:#f6f7f9}.sp-mini svg{width:100%;height:auto;display:block}</style>\n'
             f'      <div class="frentes-grid">{cards}</div>\n    </div>\n  </section>')
    # Texto de boas-vindas amplo: cobre locação, manutenção, prestadores de serviço e gestão de vendas (CRM).
    ANTIGO = ('A plataforma de gestão para quem opera com equipamentos, equipes e serviços em campo: locação, manutenção industrial, montagem, logística e transportes, '
              'gestão de serviços e distribuição. Contratos, ordens de serviço, frota, estoque, comercial e financeiro em uma só base. Acesse sua conta e domine sua operação.')
    NOVO = ('A plataforma de gestão para empresas que operam com equipamentos e equipes em campo, prestam serviços ou precisam organizar as vendas: locação, manutenção industrial, '
            'montagem, logística e transportes, prestação de serviços, distribuição e gestão de vendas (CRM). Contratos, ordens de serviço, frota, estoque, comercial, financeiro e equipe '
            'em uma só base, com inteligência artificial. Escolha só o que a sua operação precisa. Acesse sua conta e domine o seu negócio.')
    t = t.replace(ANTIGO, NOVO)
    t = t.replace('<title>Oravel — Software de Gestão para Locação, Manutenção e Logística</title>', '<title>Oravel — Plataforma de gestão com IA para serviços, vendas, manutenção e locação</title>')
    t = t.replace('content="Oravel: plataforma de gestão para locação, manutenção industrial, montagem, logística e transportes e gestão de serviços. Agende uma demonstração."',
                  'content="Oravel: plataforma de gestão com inteligência artificial para prestadores de serviço, gestão de vendas (CRM), manutenção, locação, logística e estoque. Agende uma demonstração."')
    # Primeira dobra de tecnologia: o texto de boas-vindas segue o da home (só muda de lugar).
    m = re.search(r'<section class="hero hero-intro">.*?<div class="hero-text">(.*?)</div>\s*</div>\s*</section>', t, re.S) or re.search(r'<!-- hero-text -->(.*?)<!-- /hero-text -->', t, re.S)
    texto_hero = re.sub(r'<h1 class="sr-only-h1">.*?</h1>', '', m.group(1), flags=re.S).strip()
    h1_oculto = (re.search(r'<h1 class="sr-only-h1">.*?</h1>', m.group(1), re.S) or re.search(r'<h1 class="sr-only-h1">.*?</h1>', t, re.S)).group(0)
    t = re.sub(r'\s*<!-- site:hero-tech -->.*?<!-- /site:hero-tech -->', '', t, flags=re.S)
    t = re.sub(r'\s*<section class="hero hero-intro">.*?</section>', '', t, count=1, flags=re.S)
    t = trocar(t, r'<section class="hero-full">', bloco_hero_tech(texto_hero).replace('<section class="th-hero"', h1_oculto + '<section class="th-hero"', 1) + '\n  <section class="hero-full">', 'hero da home')
    t = re.sub(r'\s*<style data-site="ia-css">.*?</style>', '', t, flags=re.S)
    t = re.sub(r'\s*<!-- site:ia -->.*?<!-- /site:ia -->', '', t, flags=re.S)
    t = re.sub(r'\s*<style data-site="academia-css">.*?</style>', '', t, flags=re.S)
    t = re.sub(r'\s*<!-- site:academia -->.*?<!-- /site:academia -->', '', t, flags=re.S)
    t = re.sub(r'\s*<!-- site:apps -->.*?<!-- /site:apps -->', '', t, flags=re.S)
    t = trocar(t, r'<section class="frentes-section".*?</section>', CSS_IA + bloco_ia() + '\n  ' + secao + '\n  ' + blocos_academia_e_apps(), 'seção frentes da home')

    passos = [
        ('1', '#f97316', 'Escolha a solução', 'ERP Locadoras, Manutenção, Serviços, Logística, Estoque ou Vendas: veja as telas e o que cada uma inclui.'),
        ('2', '#7c3aed', 'Contrate', 'Online, em poucos minutos, ou com a ajuda da nossa equipe comercial, que monta o contrato sob medida.'),
        ('3', '#0f172a', 'Comece a usar', 'Acesso liberado e treinamento na Academia Oravel, com aulas e provas para a sua equipe.'),
    ]
    cards_como = ''.join(
        f'<div class="dif-card"><div class="dif-badge" style="background:{cor};color:white;">{n}</div><h3>{tit}</h3>'
        f'<p style="color:#666;font-size:14px;line-height:1.6;margin-top:16px;">{txt}</p></div>' for n, cor, tit, txt in passos)
    como = ('<section class="diferenciacao-section" data-site="como">\n    <div class="wrap">\n      <h2 style="text-align: center; margin-bottom: 8px;">Como contratar</h2>\n'
            f'      <style>.diferenciacao-grid{{grid-template-columns:repeat(3,1fr)}}@media(max-width:860px){{.diferenciacao-grid{{grid-template-columns:1fr}}}}</style>\n      <div class="diferenciacao-grid">{cards_como}\n      </div>\n    </div>\n  </section>')
    t = trocar(t, r'<!-- (?:Seção de Diferenciação[^>]*|Como contratar) -->\s*<section class="diferenciacao-section".*?</section>', '<!-- Como contratar -->\n  ' + como, 'seção diferenciação da home')

    col = ''.join(f'<a href="https://oravel.com.br{p["pagina"]}">{p["nome"]}</a>' for p in PACOTES)
    colunas = (f'<div class="footer-col">\n          <h4>Soluções</h4>\n          {col}\n        </div>\n        <div class="footer-col">\n          <h4>Empresa</h4>\n'
               '          <a href="https://oravel.com.br/planos/">Planos</a>\n          <a href="https://oravel.com.br/sobre/">Sobre</a>\n'
               '          <a href="https://oravel.com.br/contato.php">Contato</a>\n        </div>\n        ')
    t = trocar(t, r'<div class="footer-col">\s*<h4>Produtos</h4>.*?(?=<div class="footer-col">\s*<h4>Legal</h4>)', colunas, 'colunas do rodapé da home', obrigatorio=False)
    gravar(rel, t)


CSS_SOBRE = """<style>
  .story { max-width: 760px; margin: 0 auto; padding: 56px 28px 24px; } .story p { font-size: 17.5px; line-height: 1.8; color: #2b3445; margin: 0 0 22px; }
  .story blockquote { margin: 34px 0; padding: 6px 0 6px 22px; border-left: 4px solid var(--accent); font-family: Manrope, sans-serif; font-size: clamp(21px, 2.6vw, 26px); font-weight: 800; line-height: 1.35; letter-spacing: -.01em; }
  .story .fecho { font-family: Manrope, sans-serif; font-weight: 800; font-size: 20px; margin-top: 34px; }
  .valores { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; max-width: 1180px; margin: 24px auto 8px; padding: 0 28px; }
  .valores div { border: 1px solid var(--border); border-radius: 14px; padding: 22px; } .valores h3 { font-size: 17px; margin-bottom: 6px; } .valores p { font-size: 14.5px; line-height: 1.6; color: var(--ink-soft); }
  .fim { max-width: 1180px; margin: 40px auto 64px; padding: 0 28px; } .fim > div { background: var(--navy); color: #fff; border-radius: 22px; padding: 44px; text-align: center; } .fim h2 { color: #fff; }
  .fim p { color: rgba(255,255,255,.78); margin: 10px auto 0; max-width: 56ch; line-height: 1.6; } .fim .btn { margin-top: 22px; background: var(--accent); color: #fff; }
  @media (max-width: 760px) { .valores { grid-template-columns: 1fr; } }
</style>"""

PARAGRAFOS_SOBRE = [
    'Existe um abismo entre o software criado em um escritório ar-condicionado e a solução forjada no calor da operação. Enquanto a maioria das empresas de tecnologia "desenha" sistemas, nós vivenciamos cada obstáculo da locação de máquinas.',
    'A Oravel não nasceu de uma reunião de diretoria; ela nasceu do silêncio angustiante de uma máquina parada no canteiro de obras, da urgência de um cliente que precisa de solução "para ontem" e da complexidade de gerenciar frotas que não param. Nós sabemos o peso de cada contrato, a incerteza de uma manutenção não planejada e a pressão de fazer o horímetro bater a meta.',
    ('q', 'Transformar essa vivência em um sistema não foi uma escolha de mercado; foi uma <strong>estratégia de sobrevivência</strong>.'),
    'Sabemos que, no seu negócio, o erro de margem é zero. Por isso, a Oravel não é apenas uma ferramenta de gestão: é a transposição da nossa experiência para a sua tela. Cada linha de código do nosso sistema é uma resposta para uma dor que nós mesmos já sentimos. Quando você clica em um botão na Oravel, não está apenas executando um comando: você está utilizando a inteligência acumulada de quem entende que a tecnologia precisa ter "cancha" de balcão.',
    'Nós eliminamos o achismo porque o mercado não perdoa o amadorismo. Substituímos a improvisação por previsibilidade, transformando a complexidade do dia a dia em uma operação fluida, onde o controle absoluto não é um luxo, é a sua base de sustentação.',
    'Não somos apenas fornecedores de software. Somos parceiros de trincheira. De quem já esteve do seu lado do balcão, para quem entende que a tecnologia é a única diferença entre apenas "operar" e "dominar" o mercado.',
]


def aplicar_sobre():
    """/sobre/ vira a página única de "Sobre" no padrão do site (a /sobre-nos, com banner laranja, redireciona para cá)."""
    rel = 'sobre/index.html'
    antigo = ler(rel)
    cauda = ''
    if 'data-site="sobre"' in antigo:
        m = re.search(r'<!-- site:cauda -->(.*?)<!-- /site:cauda -->', antigo, re.S)
        cauda = m.group(1) if m else ''
    else:
        m = re.search(r'</footer>\s*(<script>.*)$', antigo, re.S)
        cauda = re.sub(r'\s*</body>\s*</html>\s*$', '', m.group(1)) if m else ''
    corpo_txt = ''.join(f'<blockquote>{p[1]}</blockquote>' if isinstance(p, tuple) else f'<p>{p}</p>' for p in PARAGRAFOS_SOBRE)
    corpo = (
        '<section class="hero" data-site="sobre"><div class="wrap"><span class="eyebrow">Nossa história</span><h1>A tecnologia que nasceu da cicatriz, não da planilha</h1>'
        '<p class="desc">Software feito por quem já esteve do outro lado do balcão: na locação, na manutenção e no campo.</p></div></section>'
        f'<main><article class="story">{corpo_txt}<p class="fecho">Oravel: a tecnologia que fala a sua língua, porque conhece as suas batalhas.</p></article>'
        '<div class="valores"><div><h3>Feito na operação</h3><p>Cada tela nasceu de um problema real de pátio, obra e contrato.</p></div>'
        '<div><h3>Previsibilidade</h3><p>Menos achismo: manutenção, estoque, frota e financeiro com dados na mão.</p></div>'
        '<div><h3>Parceria</h3><p>Treinamento, suporte e uma equipe que entende o seu dia a dia.</p></div></div>'
        '<section class="fim"><div><h2>Pronto para tirar sua operação da planilha?</h2><p>Conheça as soluções Oravel ou agende uma demonstração com o cenário real da sua operação.</p>'
        '<a class="btn" href="/solucoes/">Ver as soluções</a></div></section></main>'
    )
    html = pagina_base('Sobre a Oravel — A tecnologia que nasceu da cicatriz, não da planilha',
                       'Conheça a história da Oravel: tecnologia forjada no calor da operação, feita por quem entende de locação, manutenção e campo.', corpo, '/sobre/')
    html = html.replace('</head>', CSS_SOBRE + '\n</head>', 1)
    if cauda:
        html = html.replace('</body>', '<!-- site:cauda -->' + cauda + '<!-- /site:cauda -->\n</body>', 1)
    gravar(rel, html)


def aplicar_contato():
    """contato.php (gitignored: tem credenciais): hero no padrão do site, menu/rodapé novos e origens por solução."""
    rel = 'contato.php'
    t = ler(rel)
    t = re.sub(r'  \.chero \{[^}]*\}\n', '', t)
    t = re.sub(r'  \.chero h1 \{[^}]*\}\n', '', t)
    t = re.sub(r'  \.chero p \{[^}]*\}\n', '', t)
    t = re.sub(r'  /\* site:hero \*/.*?/\* /site:hero \*/\n', '', t, flags=re.S)
    css_hero = ('  /* site:hero */\n  .chero { padding: 72px 0 40px; background: linear-gradient(180deg, #eef1f5 0%, #ffffff 100%); }\n'
                '  .chero .eyebrow { font-family: "Manrope", sans-serif; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); }\n'
                '  .chero h1 { font-size: clamp(30px, 4vw, 46px); line-height: 1.12; margin-top: 14px; }\n'
                '  .chero p { font-size: 17px; color: var(--ink-soft); line-height: 1.65; margin-top: 16px; max-width: 58ch; font-weight: 500; }\n  /* /site:hero */\n')
    t = t.replace('  .contact-section {', css_hero + '  .contact-section {', 1)
    t = trocar(t, r'<header class="chero">.*?</header>',
               '<header class="chero"><div class="wrap"><div class="eyebrow">Contato</div><h1>Vamos conversar sobre a sua operação?</h1>'
               '<p>Conte o que você precisa e mostramos, na prática, qual solução Oravel se encaixa no seu dia a dia.</p></div></header>', 'hero do contato')
    t = nav_e_rodape(t, '/contato.php', rel)
    t = trocar(t, r'<h4>Produto</h4>\s*</div>', '<h4>WhatsApp</h4>\n            <a href="https://wa.me/5519999332615" target="_blank" rel="noopener">(19) 99933-2615</a>\n          </div>', 'item Produto vazio do contato', obrigatorio=False)
    linhas = ''.join(
        f"    'pacote-{p['codigo']}' => '" + ('Quero falar com um especialista sobre o ERP Locadoras.' if p['codigo'] == 'locadora' else f"Quero conhecer a solução {p['nome']} da Oravel.") + "',\n"
        for p in PACOTES)
    t = trocar(t, r"\$origens = \[.*?\];",
               "$origens = [\n" + linhas + "    'crm'                 => 'Quero conhecer a solução Vendas da Oravel.',\n    'distribuidoras'      => 'Quero conhecer a solução Estoque da Oravel.',\n"
               "    'servicos-tecnicos'   => 'Quero conhecer a solução Serviços da Oravel.',\n    'segmento-a-sistema'  => 'Venho do sistema/ERP atual e quero avaliar trocar pelo ERP Locadoras da Oravel.',\n"
               "    'segmento-b-planilha' => 'Hoje controlo minha locadora em planilha e quero conhecer o ERP Locadoras da Oravel.',\n];", 'origens do contato', obrigatorio=False)
    t = trocar(t, r"\$origemKey = \$_GET\['origem'\] \?\? '';",
               "$origemKey = $_GET['origem'] ?? (isset($_GET['pacote']) ? 'pacote-' . preg_replace('/[^a-z]/', '', (string) $_GET['pacote']) : '');", 'origemKey do contato', obrigatorio=False)
    gravar(rel, t)


def varrer_links():
    """Links soltos que sobraram em qualquer página: #frentes, /segmentos/ e /sobre-nos."""
    for p in sorted(RAIZ.rglob('*')):
        if p.suffix not in ('.html', '.php') or not p.is_file() or 'PHPMailer' in p.parts:
            continue
        rel = str(p.relative_to(RAIZ))
        t = p.read_text(encoding='utf-8')
        n = re.sub(r'(<a href=")[^"]*(?:/#frentes|/segmentos/?)("[^>]*>)(?:Frentes|Nossas frentes|Segmentos|Produtos|Como Funciona)(</a>)', r'\1/solucoes/\2Soluções\3', t)
        n = re.sub(r'href="(?:https://oravel\.com\.br)?/#frentes"', 'href="/solucoes/"', n)
        n = re.sub(r'href="(https://oravel\.com\.br)?/segmentos/?"', lambda m: f'href="{m.group(1) or ""}/solucoes/"', n)
        n = n.replace("'/segmentos/'", "'/solucoes/'")
        n = re.sub(r'href="(https://oravel\.com\.br)?/sobre-nos(?:\.html)?"', lambda m: f'href="{m.group(1) or ""}/sobre/"', n)
        if n != t:
            gravar(rel, n)


# ---------------------------------------------------------------- principal

PAGINAS_INAV = [
    'erp-cmms/index.html', 'software-manutencao-industrial/index.html', 'software-montagem-industrial/index.html',
    'software-gestao-de-servicos/index.html', 'software-gestao-frota-logistica/index.html', 'wms/index.html', 'crm/index.html',
    'os/index.html', 'frota/index.html', 'termos-de-uso/index.html', 'politica-de-privacidade/index.html', 'erp.html',
]


def htaccess():
    regras = '\n'.join(f'RewriteRule ^{k}/?$ https://oravel.com.br{v} [R=301,L]' for k, v in dados.REDIRECTS.items())
    return f"""<IfModule mod_rewrite.c>
RewriteEngine On
# Reestruturação em Soluções (6 pacotes): páginas antigas apontam para o equivalente atual
{regras}
# Páginas antigas de FSM e PCM foram absorvidas por Manutenção Industrial
RewriteRule ^(fsm|pcm)/?$ https://oravel.com.br/software-manutencao-industrial/ [R=301,L]
# Página antiga solta no servidor: aponta para a atual
RewriteRule ^sobre\\.html$ https://oravel.com.br/sobre/ [R=301,L]
RewriteRule ^(.+)\\.html$ - [L]
RewriteCond %{{REQUEST_FILENAME}} !-f
RewriteCond %{{REQUEST_FILENAME}} !-d
RewriteRule ^(.*)$ $1.html [L]
</IfModule>
# Páginas (HTML/PHP) sempre revalidadas: o navegador não guarda a versão antiga do menu e dos textos
<IfModule mod_headers.c>
<FilesMatch "\\.(html|php)$">
Header set Cache-Control "no-cache, must-revalidate"
</FilesMatch>
</IfModule>
"""


# Páginas antigas que só existem no servidor (enterprise e proposta): ganham o MESMO cabeçalho do restante do site.
PAGINAS_LEGADO = ['nova/enterprise/index.php', 'nova/enterprise/solucoes/index.php', 'nova/enterprise/novidades/index.php', 'proposta/index.php']


def aplicar_legado():
    for rel in PAGINAS_LEGADO:
        t = ler(rel)
        t = re.sub(r'<style data-site="nav-css">.*?</style>\s*', '', t, flags=re.S)
        if 'data-site="nav"' in t:
            t = trocar(t, r'(?:<div class="tb" data-site="topbar">.*?</div></div>\s*)?<nav class="inav"[^>]*>.*?</nav>', nav_inav(None), f'nav em {rel}')
        else:
            t = trocar(t, r'<nav class="nav">.*?</nav>', nav_inav(None), f'nav antigo em {rel}')
        # O CSS do cabeçalho entra logo antes dele (estas páginas não têm um <head> próprio de fácil acesso).
        t = t.replace(TOPBAR, CSS_NAV + '\n' + TOPBAR, 1)
        gravar(rel, t)


def main():
    print('aplicando estrutura de Soluções em', RAIZ)
    for rel in PAGINAS_INAV:
        aplicar_pagina_inav(rel)
    gravar('solucoes/index.html', pagina_solucoes())
    gravar('planos/index.html', pagina_planos())
    gravar('academia/index.html', pagina_academia())
    gravar('inteligencia-artificial/index.html', pagina_ia())
    for p in PACOTES:
        if p.get('pagina_nova'):
            gravar(p['pagina'].strip('/') + '/index.html', pagina_pacote_nova(p['codigo']))
    gravar('inc/nav-fragment.html', nav_fragment())
    gravar('inc/precos.js', JS_PRECOS)
    aplicar_sobre()
    aplicar_home()
    aplicar_contato()
    aplicar_legado()
    varrer_links()
    gravar('.htaccess', htaccess())
    print('ok')


if __name__ == '__main__':
    main()
