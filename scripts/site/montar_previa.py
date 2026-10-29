#!/usr/bin/env python3
"""
Monta uma pré-visualização navegável do site novo em UM arquivo HTML (para publicar como artefato):
as páginas principais viram abas por #hash, imagens embutidas. Uso: montar_previa.py <public_html> <saida.html>
"""
import base64
import io
import re
import sys
from pathlib import Path

from PIL import Image

import tema as t

RAIZ, SAIDA = Path(sys.argv[1]), Path(sys.argv[2])

PAGINAS = [  # chave, rótulo, arquivo, links do site que apontam para ela
    ('home', 'Home', 'novo/index.html', ['/', '/novo/']),
    ('recursos', 'Recursos', 'recursos/index.html', ['/recursos/']),
    ('manutencao', 'Manutenção', 'software-manutencao-industrial/index.html', ['/software-manutencao-industrial/']),
    ('logistica', 'Logística', 'processo-logistica/index.html', ['/processo-logistica/']),
    ('solucoes', 'Soluções', 'solucoes/index.html', ['/solucoes/']),
    ('guindastes', 'Página de equipamento', 'software-manutencao-guindastes/index.html', ['/software-manutencao-guindastes/']),
    ('contato', 'Contato', 'contato.novo.php', ['/contato.php', '/contato/']),
    ('sobre', 'Sobre', 'sobre/index.html', ['/sobre/']),
    ('faq', 'FAQ', 'faq.php', ['/faq.php']),
]
ALVO = {}
for k, _, _, ls in PAGINAS:
    for l in ls:
        ALVO[l] = '#' + k


def img_data(m):
    p = RAIZ / m.group(1).lstrip('/')
    if not p.exists():
        return m.group(0)
    im = Image.open(p).convert('RGB')
    im.thumbnail((760, 760))
    b = io.BytesIO()
    im.save(b, 'JPEG', quality=66, optimize=True)
    return 'data:image/jpeg;base64,' + base64.b64encode(b.getvalue()).decode()


def principal(arq, chave):
    h = (RAIZ / arq).read_text(encoding='utf-8')
    if arq.endswith('.php'):
        h = h[h.find('?>') + 2:]
        h = re.sub(r"<\?php if \(\$status.*?<\?php endif; \?>", '', h, flags=re.S)
        h = re.sub(r"<\?php if \(\$origemKey.*?<\?php endif; \?>", '', h, flags=re.S)
        h = re.sub(r'<\?=.*?\?>', '', h)
    m = re.search(r'<main id="conteudo">(.*?)</main>', h, re.S)
    c = m.group(1)
    c = re.sub(r'<script.*?</script>', '', c, flags=re.S)
    c = re.sub(r'<div class="cf-turnstile"[^>]*></div>', '', c)
    c = re.sub(r'<video.*?</video>', '<img src="/img/equip/frota-poster.jpg" alt="">', c, flags=re.S)
    # ids/âncoras internas por página
    ids = set(re.findall(r'\bid="([^"]+)"', c))
    for i in ids:
        c = c.replace(f'id="{i}"', f'id="{chave}-{i}"').replace(f'href="#{i}"', f'href="#{chave}-{i}"').replace(f'for="{i}"', f'for="{chave}-{i}"')
    c = re.sub(r'(src|href)="(/img/[^"]+\.(?:jpg|png))"', lambda m: f'{m.group(1)}="' + img_data(re.match(r'(.*)', m.group(2))) + '"', c)
    # links do site
    def lk(m):
        u = m.group(1)
        base = u.split('#')[0].split('?')[0]
        return f'href="{ALVO.get(base, "#")}"'
    c = re.sub(r'href="(/[^"]*)"', lk, c)
    c = re.sub(r'href="https://wa\.me[^"]*"', lambda m: m.group(0), c)
    return c


cab = t.cabecalho()
# o menu da prévia navega pelas abas; links sem página ficam inertes
def lk_menu(m):
    u = m.group(1)
    return f'href="{ALVO.get(u.split("#")[0].split("?")[0], "#")}"'
cab = re.sub(r'href="(/[^"]*)"', lk_menu, cab)
rod = re.sub(r'href="(/[^"]*)"', lk_menu, t.rodape())
abas = ''.join(f'<a href="#{k}" data-aba="{k}">{r}</a>' for k, r, _, _ in PAGINAS)
secs = ''.join(f'<section class="pg" data-pg="{k}" hidden>{principal(a, k)}</section>' for k, _, a, _ in PAGINAS)
logo = RAIZ / 'img/logo-or.png'
css = (t.CSS + t.CSS_REC + t.CSS_REF).replace('url(/img/logo-or.png)', 'url(data:image/png;base64,' + base64.b64encode(logo.read_bytes()).decode() + ')')
css = css.replace('.top{position:sticky;top:0', '.top{position:sticky;top:44px').replace('html{-webkit-user-select:none;user-select:none}', '')
extra = """
:root{color-scheme:light}body{background:#fff;color:#0f172a;margin:0;padding-top:0}
.prev{position:sticky;top:0;z-index:70;background:#0f172a;color:#e2e8f0;font:600 13px Inter,system-ui,sans-serif;padding:0 max(16px,env(safe-area-inset-left,0px));min-height:44px;display:flex;gap:6px;align-items:center;overflow-x:auto;white-space:nowrap}
.prev b{color:#fdba74;margin-right:8px;flex:none}.prev a{color:#cbd5e1;text-decoration:none;padding:6px 12px;border-radius:999px;flex:none}.prev a.on{background:#f97316;color:#0f172a}.prev a:hover{background:rgba(255,255,255,.12)}
.top-in,.wrap{padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px))}
.hp{display:none}
"""
js = """
(function(){var K=%s,pgs=[].slice.call(document.querySelectorAll('.pg')),ab=[].slice.call(document.querySelectorAll('[data-aba]'));
function show(k){pgs.forEach(function(p){p.hidden=p.getAttribute('data-pg')!==k});ab.forEach(function(a){a.classList.toggle('on',a.getAttribute('data-aba')===k)});window.scrollTo(0,0)}
function rota(){var h=(location.hash||'').slice(1);if(K.indexOf(h)>-1){show(h)}else if(!document.querySelector('.pg:not([hidden])')){show('home')}}
window.addEventListener('hashchange',rota);rota();
var b=document.querySelector('.burger'),m=document.getElementById('mnav');if(b&&m){b.addEventListener('click',function(){var o=m.classList.toggle('open');b.setAttribute('aria-expanded',o?'true':'false')})}
[].slice.call(document.querySelectorAll('form')).forEach(function(f){f.addEventListener('submit',function(e){e.preventDefault();alert&&0;var n=f.querySelector('.fmsg')||document.createElement('div');n.className='fmsg ok';n.textContent='Prévia: no site publicado esta mensagem é enviada por e-mail para a equipe.';f.parentNode.insertBefore(n,f)})});
})();
""" % ('[' + ','.join(f"'{k}'" for k, *_ in PAGINAS) + ']')
html = ('<title>Novo site Oravel</title>\n<meta name="claude:viewport-fit-sides" content="cover">\n'
        + t.FONTES.replace('<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>', '')
        + f'\n<style>{css}{extra}</style>\n<div class="prev"><b>Pré-visualização do novo site</b>{abas}</div>\n{cab}\n<main id="conteudo">{secs}</main>\n{rod}\n<script>{js}</script>')
html = html.replace('<a class="skip" href="#conteudo">Ir para o conteúdo</a>', '')
SAIDA.write_text(html, encoding='utf-8')
print('ok', len(html) // 1024, 'KB')
