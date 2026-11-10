#!/usr/bin/env python3
"""
Acrescenta o item "Reforma tributária" ao menu e ao rodapé das páginas já geradas de public_html.
Idempotente (pula o arquivo que já tem o link). Não mexe em backups (*bak*).

Cobre os três formatos de menu do site:
  1. tema novo      -> <nav class="nav" ...>, menu do celular (#mnav) e rodapé "Conteúdo"
  2. menu "hd-links" -> páginas antigas e inc/nav-fragment.html (carregado por nav-loader.js)

Uso: python3 adicionar_menu_reforma.py <public_html>
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import tema as t  # noqa: E402

RAIZ = Path(sys.argv[1])
LINK = '/reforma-tributaria/'
HD_RT = '<a href="/reforma-tributaria/">Reforma tributária</a>'

TROCAS_TEMA = [
    ('<a href="/materiais-gratuitos/">Materiais gratuitos</a><div class="dd"><button type="button" aria-haspopup="true">Soluções</button>',
     t.NAV_RT + '<a href="/materiais-gratuitos/">Materiais gratuitos</a><div class="dd"><button type="button" aria-haspopup="true">Soluções</button>'),
    ('<a href="/materiais-gratuitos/">Materiais gratuitos</a><a href="/contato.php">Contato</a><div style="display:grid',
     t.MNAV_RT + '<a href="/materiais-gratuitos/">Materiais gratuitos</a><a href="/contato.php">Contato</a><div style="display:grid'),
    ('<div><h4>Conteúdo</h4><a href="/central-de-conhecimento/">',
     '<div><h4>Conteúdo</h4>' + t.FOOT_RT + '<a href="/central-de-conhecimento/">'),
]
TROCAS_HD = [
    ('<a href="/planos/">Planos</a><a href="/sobre/" class="hd-sobre">', HD_RT + '<a href="/planos/">Planos</a><a href="/sobre/" class="hd-sobre">'),
]

alterados, sem_padrao = [], []
for f in sorted(RAIZ.rglob('*')):
    if not f.is_file() or f.suffix not in ('.html', '.php') or 'bak' in f.name or '.bak' in str(f):
        continue
    s = f.read_text(encoding='utf-8', errors='ignore')
    if LINK in s:
        continue
    novo = s
    if 'class="top-in"' in s:
        for a, b in TROCAS_TEMA:
            novo = novo.replace(a, b, 1)
    if 'class="hd-links"' in s:
        for a, b in TROCAS_HD:
            novo = novo.replace(a, b, 1)
    if novo != s:
        f.write_text(novo, encoding='utf-8')
        alterados.append(str(f.relative_to(RAIZ)))
    elif 'class="top-in"' in s or 'class="hd-links"' in s:
        sem_padrao.append(str(f.relative_to(RAIZ)))

print('alterados:', len(alterados))
for a in alterados:
    print('  +', a)
if sem_padrao:
    print('menu fora do padrão (conferir à mão):', len(sem_padrao))
    for a in sem_padrao:
        print('  ?', a)
