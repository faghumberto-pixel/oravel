"""Auxiliares para escrever as aulas da Academia (HTML com mockups e telas reais)."""
import os, html as _h
LESSON_DIR = './visuals'
IMG_DIR = '/home/oravel/oravel/public/academy/img'

def slug(page): return page.strip('/').replace('/', '__').replace('.html', '')
def e(t): return _h.escape(t, quote=False)

def p(t): return f'<p>{t}</p>'
def h2(t): return f'<h2>{t}</h2>'
def h3(t): return f'<h3>{t}</h3>'
def menu(path): return f'<div class="mk-menu">📍 {path}</div>'
def chip(t, c='blue'): return f'<span class="mk-chip {c}">{t}</span>'
def chips(*items): return '<div>' + ''.join(chip(t, c) for t, c in items) + '</div>'
def ul(items): return '<ul>' + ''.join(f'<li>{i}</li>' for i in items) + '</ul>'
def ol(items): return '<ol>' + ''.join(f'<li>{i}</li>' for i in items) + '</ol>'
def note(kind, title, text): return f'<div class="mk-note {kind}"><strong>{title}</strong><p>{text}</p></div>'
def tip(t, title='💡 Dica'): return note('tip', title, t)
def warn(t, title='⚠️ Atenção'): return note('warn', title, t)
def who(t, title='👤 Quem usa'): return note('who', title, t)
def bad(t, title='🚫 Importante'): return note('bad', title, t)

def fig(img, title, legend, narrow=False):
    """Tela real com destaques numerados; `legend` explica cada número, na ordem."""
    if not os.path.exists(f'{IMG_DIR}/{img}.jpg'): print('  !! imagem ausente:', img)
    items = ''.join(f'<li>{l}</li>' for l in legend)
    return (f'<figure class="mk-fig{" narrow" if narrow else ""}"><img src="/academy/img/{img}.jpg" alt="{e(title)}">'
            f'<figcaption><b>{title}</b><ol class="mk-legend">{items}</ol></figcaption></figure>')

def flow(steps):
    """Passos horizontais: (titulo, texto, cor?)"""
    out = ''
    for i, s in enumerate(steps, 1):
        t, tx, *c = s; c = c[0] if c else ''
        out += f'<div class="mk-step {c}"><span class="n">{i}</span><b>{t}</b><span class="t">{tx}</span></div>'
    return f'<div class="mk-flow">{out}</div>'

def vflow(steps):
    out = ''
    for s in steps:
        t, tx, *c = s; c = c[0] if c else ''
        out += f'<div class="{c}"><b>{t}</b><br>{tx}</div>'
    return f'<div class="mk-vflow">{out}</div>'

def check(items):
    """(texto, 'ok'|'no'|'wait')"""
    return '<ul class="mk-check">' + ''.join(f'<li class="{"" if k=="ok" else k}">{t}</li>' for t, k in items) + '</ul>'

def cols(cards):
    """(titulo, html, cor)"""
    return '<div class="mk-cols">' + ''.join(f'<div class="mk-card2 {c}"><h4>{t}</h4>{b}</div>' for t, b, c in cards) + '</div>'

def kpis(items):
    return '<div class="mk-kpis">' + ''.join(f'<div class="mk-kpi {c}"><small>{l}</small><strong>{v}</strong></div>' for l, v, c in items) + '</div>'

def table(headers, rows):
    return '<table><thead><tr>' + ''.join(f'<th>{h}</th>' for h in headers) + '</tr></thead><tbody>' + \
        ''.join('<tr>' + ''.join(f'<td>{c}</td>' for c in r) + '</tr>' for r in rows) + '</tbody></table>'

def seq(*items):
    """Sequência com setas: textos ou (texto, cor)"""
    out = []
    for it in items:
        t, c = (it, 'gray') if isinstance(it, str) else it
        out.append(chip(t, c))
    return '<div class="mk-seq">' + '<span class="arr">➜</span>'.join(out) + '</div>'

def lesson(page, *blocks):
    os.makedirs(LESSON_DIR, exist_ok=True)
    body = '\n'.join(b for b in blocks if b)
    open(f'{LESSON_DIR}/{slug(page)}.html', 'w', encoding='utf-8').write(body)
    print(f'  ✓ {page} ({len(body)//1024 or 1} KB)')
