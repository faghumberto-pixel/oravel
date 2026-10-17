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


def nav_inav(atual=None):
    """Menu padrão (páginas com o bloco .inav). `atual` = caminho da página, para marcar o item."""
    itens_dd = ''.join(
        f'<a href="{p["pagina"]}"{" aria-current=\"page\"" if atual == p["pagina"] or dados.PAGINA_PACOTE.get(atual) == p["codigo"] else ""}>{p["nome"]}</a>'
        for p in PACOTES)
    cur = lambda href: ' aria-current="page"' if atual == href else ''  # noqa: E731
    links = [('/planos/', 'Planos'), ('/sobre/', 'Sobre'), ('/faq.php', 'FAQ'), ('/central-de-conhecimento/', 'Ajuda'), ('/contato.php', 'Contato')]
    desk = ''.join(f'<a href="{h}"{cur(h)}>{t}</a>' for h, t in links)
    mob = ''.join(f'<a href="{p["pagina"]}">{p["nome"]}</a>' for p in PACOTES) + '<hr>' + ''.join(f'<a href="{h}">{t}</a>' for h, t in links)
    return (
        '<nav class="inav" data-site="nav" aria-label="Principal"><div class="inav-wrap">'
        '<a href="/" class="inav-logo"><span class="inav-logo-mark">O</span>Oravel</a>'
        '<div class="inav-links">'
        f'<div class="inav-dd"><a href="/solucoes/" aria-haspopup="true">Soluções</a><div class="inav-dd-menu">{itens_dd}</div></div>'
        f'{desk}</div>'
        f'<details class="inav-m"><summary>Menu</summary><div class="inav-m-list"><a href="/solucoes/">Todas as soluções</a>{mob}</div></details>'
        '<a href="/contato.php" class="inav-cta">Agendar demonstração</a>'
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
        '<div class="ifoot-links"><a href="/">Home</a><a href="/solucoes/">Soluções</a><a href="/planos/">Planos</a><a href="/contato.php">Contato</a>'
        '<a href="/politica-de-privacidade/">Privacidade</a><a href="/termos-de-uso/">Termos</a></div>'
        '</div></footer>'
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
        '<!-- site:pacote -->', CSS_PACOTE,
        f'<section class="sp-sec" id="incluido"><div class="sp-wrap"><div class="sp-eyebrow">Solução {p["nome"]}</div>',
        f'<h2>O que está incluído</h2><p class="sp-lead">{p["descricao"]}{vert}</p>',
        f'<div class="sp-inclui">{inclui}</div><p class="sp-base">Em todas as soluções: {dados.BASE_COMUM}.</p></div></section>',
        '<section class="sp-sec alt" id="telas"><div class="sp-wrap"><div class="sp-eyebrow">O sistema por dentro</div>',
        f'<h2>Veja as telas da solução {p["nome"]}</h2><p class="sp-lead">Exemplos de telas, com dados fictícios.</p>',
        f'<div class="sp-galeria">{figs}</div></div></section>',
        f'<section class="sp-sec" id="contratar"><div class="sp-wrap"><div class="sp-cta"><h2>Comece com a solução {p["nome"]}</h2><p>{texto_cta}</p>',
        f'<div class="sp-cta-btns"><a class="sp-btn p" href="{href}">{rotulo}</a><a class="sp-btn" href="/planos/#{codigo}">Comparar planos</a>{extra_btn}</div>',
        '</div></div></section>', '<!-- /site:pacote -->',
    ]
    return ''.join(partes)


def nav_e_rodape(texto, atual, rel):
    """Troca o menu .inav e o rodapé .ifoot pelo padrão e (re)aplica o CSS do menu."""
    texto = trocar(texto, r'<nav class="inav"[^>]*>.*?</nav>', nav_inav(atual), f'nav inav em {rel}')
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
        f'{CSS_HUB}\n{CSS_NAV}\n</head>\n<body>\n{nav_inav(atual)}\n{corpo}\n{footer_inav()}\n</body>\n</html>\n'
    )


def cards_pacotes(com_shot=True):
    out = []
    for p in PACOTES:
        shot = f'<div class="plan-shot">{mockups.gerar(p["mockups"][0])}</div>' if com_shot else ''
        lis = ''.join(f'<li>{t}</li>' for t, _ in p['inclui'][:5])
        out.append(
            f'<article class="plan" id="{p["codigo"]}">{shot}<div class="plan-body"><h3>{p["nome"]}</h3><p class="plan-desc">{p["frase"]} {p["descricao"]}</p>'
            f'<ul>{lis}</ul><div class="plan-actions"><a class="btn btn-primary" href="{cta(p["codigo"])[0]}" data-pacote="{p["codigo"]}">{cta(p["codigo"])[1]}</a>'
            f'<a class="btn btn-ghost" href="{p["pagina"]}">Conhecer a solução</a></div></div></article>')
    return ''.join(out)


def pagina_solucoes():
    corpo = (
        '<section class="hero"><div class="wrap"><span class="eyebrow">Soluções Oravel</span><h1>Escolha a solução certa para a sua operação</h1>'
        '<p class="desc">Seis soluções, cada uma completa no que se propõe. Comece pela que resolve o seu dia a dia e contrate online ou com a nossa equipe.</p></div></section>'
        f'<main class="wrap"><div class="plans">{cards_pacotes()}</div>'
        '<div class="como"><div><b>1</b><h3>Escolha a solução</h3><p>Veja as telas e o que está incluído em cada uma.</p></div>'
        '<div><b>2</b><h3>Contrate</h3><p>Online, em poucos minutos, ou com apoio da nossa equipe comercial.</p></div>'
        '<div><b>3</b><h3>Comece a usar</h3><p>Acesso liberado e treinamento na Academia Oravel.</p></div></div>'
        '<p class="note">Precisa de uma combinação diferente? Fale com a nossa equipe em <a href="/contato.php">contato</a> e montamos o contrato sob medida.</p></main>'
    )
    return pagina_base('Soluções — ERP Locadoras, Manutenção, Serviços, Logística, Estoque e Vendas | Oravel',
                       'Conheça as soluções Oravel: ERP Locadoras, Manutenção, Serviços, Logística, Estoque e Vendas. Contrate só o que a sua operação precisa.', corpo, '/solucoes/')


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


# ---------------------------------------------------------------- menu "onav" (nav-fragment.html, usado por .php e gestao-locadoras)

def nav_fragment():
    dd = ''.join(f'<a href="{p["pagina"]}">{p["nome"]}</a>' for p in PACOTES)
    return f'''<nav class="onav" data-site="nav">
  <div class="onav-wrap">
    <a href="/" class="onav-brand">
      <img class="onav-mark" src="/nova/assets/oravel-wordmark-only.png?v=1" alt="Oravel" />
    </a>
    <button type="button" class="onav-burger" id="onav-burger" aria-label="Abrir menu" aria-expanded="false">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    </button>
    <div class="onav-links" id="onav-links">
      <div class="onav-dropdown" id="onav-dropdown-solucoes">
        <button type="button" class="onav-dropdown-trigger" aria-expanded="false">
          Soluções
          <svg width="10" height="10" viewBox="0 0 16 16" fill="none"><path d="M3 6l5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="onav-dropdown-menu">
          {dd}
          <a href="/solucoes/" class="onav-dropdown-all">Ver todas as soluções</a>
        </div>
      </div>
      <a href="/planos/" data-nav-planos>Planos</a>
      <a href="/sobre/">Sobre</a>
      <a href="/faq.php">FAQ</a>
      <a href="/central-de-conhecimento/">Ajuda</a>
      <a href="/contato.php">Contato</a>
      <a href="/contato.php" class="onav-cta">Agendar Demonstração</a>
    </div>
  </div>
</nav>
'''


def aplicar_home():
    rel = 'index.html'
    t = ler(rel)
    # Faixa preta: fica só o WhatsApp (a fileira de "frentes/segmentos" sai; o menu Soluções a substitui)
    t = trocar(t, r'\s*<div class="topbar-seg".*?</div>', '', 'fileira de frentes do topbar', obrigatorio=False)
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
             '      <p class="frentes-lead">Seis soluções, cada uma com as telas e os módulos que o dia a dia realmente usa. Contrate só o que precisa.</p>\n'
             '      <style>.sp-mini{margin:-4px -4px 14px;border-radius:10px;overflow:hidden;background:#f6f7f9}.sp-mini svg{width:100%;height:auto;display:block}</style>\n'
             f'      <div class="frentes-grid">{cards}</div>\n    </div>\n  </section>')
    t = trocar(t, r'<section class="frentes-section".*?</section>', secao, 'seção frentes da home')

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
RewriteRule ^(.+)\\.html$ - [L]
RewriteCond %{{REQUEST_FILENAME}} !-f
RewriteCond %{{REQUEST_FILENAME}} !-d
RewriteRule ^(.*)$ $1.html [L]
</IfModule>
"""


def main():
    print('aplicando estrutura de Soluções em', RAIZ)
    for rel in PAGINAS_INAV:
        aplicar_pagina_inav(rel)
    gravar('solucoes/index.html', pagina_solucoes())
    gravar('planos/index.html', pagina_planos())
    gravar('inc/nav-fragment.html', nav_fragment())
    aplicar_sobre()
    aplicar_home()
    aplicar_contato()
    varrer_links()
    gravar('.htaccess', htaccess())
    print('ok')


if __name__ == '__main__':
    main()
