#!/usr/bin/env python3
"""
Gera o conjunto único de favicons da Oravel (marca "OR" laranja) e atualiza as tags <link rel="icon">
das páginas. Fonte: oravel-wordmark-only.png (nova/assets do servidor).
Uso: python3 favicon.py <wordmark.png> <public_html>
"""
import base64
import glob
import io
import re
import sys
from pathlib import Path

from PIL import Image

SRC, RAIZ = Path(sys.argv[1]), Path(sys.argv[2])
im = Image.open(SRC).convert('RGBA')
box = im.getchannel('A').point(lambda a: 255 if a > 30 else 0).getbbox()
marca = im.crop(box)


def quadrado(lado, margem=.12):
    c = Image.new('RGBA', (lado * 4, lado * 4), (255, 255, 255, 255))
    alvo = int(lado * 4 * (1 - 2 * margem))
    m = marca.copy()
    m.thumbnail((alvo, alvo), Image.LANCZOS)
    c.alpha_composite(m, ((lado * 4 - m.width) // 2, (lado * 4 - m.height) // 2))
    return c.convert('RGB').resize((lado, lado), Image.LANCZOS)


saidas = {}
for n in (32, 48, 96, 192, 512):
    saidas[f'favicon-{n}.png'] = quadrado(n)
saidas['favicon.png'] = saidas['favicon-512.png']
saidas['apple-touch-icon.png'] = quadrado(180, .1)
for nome, img in saidas.items():
    for d in (RAIZ, RAIZ / 'nova/assets'):
        d.mkdir(parents=True, exist_ok=True)
        img.save(d / nome, 'PNG', optimize=True)
ico = quadrado(64)
for d in (RAIZ, RAIZ / 'nova/assets'):
    ico.save(d / 'favicon.ico', sizes=[(16, 16), (24, 24), (32, 32), (48, 48), (64, 64)])
buf = io.BytesIO()
saidas['favicon-96.png'].save(buf, 'PNG', optimize=True)
svg = ('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 96 96" width="96" height="96">'
       f'<image width="96" height="96" xlink:href="data:image/png;base64,{base64.b64encode(buf.getvalue()).decode()}"/></svg>')
for d in (RAIZ, RAIZ / 'nova/assets'):
    (d / 'favicon.svg').write_text(svg, encoding='utf-8')

BLOCO = ('<link rel="icon" href="/favicon.ico" sizes="any">\n'
         '<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">\n'
         '<link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">\n'
         '<link rel="apple-touch-icon" href="/apple-touch-icon.png">\n')
ANTIGOS = re.compile(r'[ \t]*<link rel="icon"[^>]*nova/assets/favicon[^>]*>\s*\n?')


def ajustar(html):
    if 'href="/favicon-48.png"' in html:
        return html
    if ANTIGOS.search(html):
        primeiro = True

        def sub(m):
            nonlocal primeiro
            if primeiro:
                primeiro = False
                return BLOCO
            return ''
        return ANTIGOS.sub(sub, html)
    return re.sub(r'(<meta name="description"[^>]*>\s*\n)', lambda m: m.group(1) + BLOCO, html, count=1)


if __name__ == '__main__':
    n = 0
    for f in glob.glob(str(RAIZ / '**/index.*'), recursive=True) + [str(RAIZ / 'contato.php'), str(RAIZ / 'index-en.html')]:
        if 'bak' in f or f.endswith('.backup') or not Path(f).exists():
            continue
        s = Path(f).read_text(encoding='utf-8')
        t = ajustar(s)
        if t != s:
            Path(f).write_text(t, encoding='utf-8')
            n += 1
    print('páginas atualizadas:', n)
