#!/usr/bin/env python3
"""
Converte páginas existentes (conteúdo textual) para o padrão novo (tema.py).
Lê o texto de cada página, monta topo + seções (cartões, passos, listas) + convite final,
e descarta formulário, blocos de preço/plano e telas antigas (entram os mockups novos).
"""
import html as H
import re
import sys
from pathlib import Path

import tema as t

IGNORAR = ('Fale com um especialista', 'WhatsApp (19)', 'Falar com a equipe', 'Comparar planos', 'Conhecer o produto completo', 'Falar com o nosso time',
           'Falar com um especialista', 'Solicitar Demonstração', 'Disponível agora', 'Exemplos de telas', 'Em todas as soluções')
CORTAR_SEC = ('Comece com a solução', 'Veja as telas', 'O sistema por dentro', 'App incluído', 'Quer ver o', 'Veja a Oravel', 'Sem preço')


def tokens(h):
    corpo = h[h.find('<body'):]
    corpo = re.sub(r'<style.*?</style>|<script.*?</script>|<nav.*?</nav>|<footer.*?</footer>|<svg.*?</svg>|<form.*?</form>|<noscript.*?</noscript>', ' ', corpo, flags=re.S)
    corpo = re.sub(r'<div id="wa-widget".*', ' ', corpo, flags=re.S)
    pos = [(m.start(), m.group(1), m.group(2)) for m in re.finditer(r'<(h1|h2|h3|h4|p|li)\b[^>]*>(.*?)</\1>', corpo, re.S)]
    out = []
    for _, tag, txt in pos:
        s = re.sub(r'\s+', ' ', re.sub(r'<[^>]+>', '', txt)).strip()
        if len(s) < 2 or any(s.startswith(i) for i in IGNORAR):
            continue
        out.append((tag, H.unescape(s)))
    return out


def estrutura(toks):
    h1, sub, secoes = None, [], []
    atual = None
    ic = ['pm', 'os', 'ck', 'av', 'hs', 'al', 'eq', 'es', 'co', 'lo']
    for tag, s in toks:
        if tag == 'h1' and not h1:
            h1 = s
        elif tag == 'h2':
            atual = {'h2': s, 'intro': '', 'cards': [], 'lis': []}
            secoes.append(atual)
        elif atual is None:
            if tag == 'p':
                sub.append(s)
        elif tag in ('h3', 'h4'):
            atual['cards'].append([s, ''])
        elif tag == 'p':
            if atual['cards'] and not atual['cards'][-1][1]:
                atual['cards'][-1][1] = s
            elif not atual['intro']:
                atual['intro'] = s
        elif tag == 'li':
            atual['lis'].append(s)
    return h1, sub, secoes, ic


def converter(arq, slug, titulo, desc, hero_mock, eyebrow, rotulo_cta=None):
    h = Path(arq).read_text(encoding='utf-8')
    h1, sub, secoes, ic = estrutura(tokens(h))
    h1 = h1 or titulo
    sub_txt = sub[0] if sub else desc
    hero = ('<section class="hero"><div class="wrap"><div><div class="eyebrow">' + eyebrow + '</div><h1>' + H.escape(h1) + '</h1><p class="sub">' + H.escape(sub_txt) + '</p>'
            '<div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar demonstração</a>'
            f'<a class="btn btn-o" href="{t.WHATS_TESTE}" target="_blank" rel="noopener">Testar 30 dias</a></div>'
            '<ul class="trust"><li>30 dias grátis com acompanhamento</li><li>Treinamento e implementação inclusos</li><li>Funciona ao lado do seu sistema</li></ul></div>'
            f'<div>{hero_mock}<p class="nota" style="color:#94a3b8">Tela ilustrativa, com dados fictícios.</p></div></div></section>')
    corpo, n = hero, 0
    for s in secoes:
        if any(s['h2'].startswith(c) for c in CORTAR_SEC):
            continue
        if not s['cards'] and not s['lis'] and not s['h2'].startswith('App '):
            continue
        n += 1
        alt = n % 2 == 0
        if s['h2'].startswith('App '):
            corpo += ('<section class="sec"><div class="wrap split rev"><div class="split-t"><div class="eyebrow">App de campo</div><h2>' + H.escape(s['h2']) + '</h2><p>' + H.escape(s['intro']) + '</p>'
                      '<ul class="ticks">' + ''.join(f'<li>{H.escape(x)}</li>' for x in s['lis']) + '</ul></div>' f'<div>{t.mk_celular()}</div></div></section>')
            continue
        passos = bool(re.search(r'Fluxo|Como Funciona|passos', s['h2'], re.I)) or all(len(c[0]) < 24 for c in s['cards']) and 3 <= len(s['cards']) <= 4 and 'Fluxo' in s['h2']
        if s['cards']:
            if passos:
                corpo_s = '<div class="steps">' + ''.join(f'<div class="step"><h3>{H.escape(a)}</h3><p>{H.escape(b)}</p></div>' for a, b in s['cards']) + '</div>'
            else:
                corpo_s = ('<div class="grid3">' if len(s['cards']) != 4 else '<div class="grid4">') + ''.join(
                    f'<div class="card">{t.ic(ic[i % len(ic)])}<h3>{H.escape(a)}</h3><p>{H.escape(b)}</p></div>' for i, (a, b) in enumerate(s['cards'])) + '</div>'
        elif s['lis']:
            corpo_s = '<ul class="ticks">' + ''.join(f'<li>{H.escape(x)}</li>' for x in s['lis']) + '</ul>'
        else:
            corpo_s = ''
        corpo += t.secao(eyebrow, H.escape(s['h2']), H.escape(s['intro']) if s['intro'] else None, corpo_s, alt=alt)
    corpo += t.cta_final(rotulo_cta or 'Veja a Oravel na rotina da sua operação')
    ld = {'@context': 'https://schema.org', '@type': 'WebPage', 'name': titulo, 'description': desc, 'url': 'https://oravel.com.br' + slug}
    return t.pagina(titulo, desc, 'https://oravel.com.br' + slug, corpo, ld)
