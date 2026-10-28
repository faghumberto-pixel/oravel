#!/usr/bin/env python3
"""
Atualiza o menu (grupos Equipamentos e Processos) e o rodapé das páginas já existentes de
public_html, a partir das listas do gerador. Idempotente. Não mexe nas páginas geradas.
Uso: python3 atualizar_menu.py <public_html> <molde.html>
"""
import glob
import os
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import gerar_paginas_equipamentos as g  # noqa: E402

RAIZ = Path(sys.argv[1])
EQ_PT = [(u, n) for u, n in re.findall(r'<a href="([^"]+)">([^<]+)</a>', g.NAV_EQUIP)]
PR_PT = list(g.PROCESSOS)
EN_EQ = ['Cranes', 'Munk trucks', 'Aerial work platforms', 'Forklifts', 'Generators', 'Compressors', 'Other equipment']
EN_PR = ['Maintenance', 'Team management', 'Inventory management', 'Logistics', 'Automatic billing', 'NR-13 inspections', 'Returns with defects']
EQ_EN = [(u, n) for (u, _), n in zip(EQ_PT, EN_EQ)]
PR_EN = [(u, n) for (u, _), n in zip(PR_PT, EN_PR)]
NOVAS = {p.strip('/') for p in [
    '/nr-13-inspecao-equipamentos/', '/equipamento-devolvido-com-defeito/', '/guindastes-e-plataformas/']} | {u.strip('/') for u, _ in EQ_PT + PR_PT}


def links(lst):
    return ''.join(f'<a href="{u}">{n}</a>' for u, n in lst)


def det(h, lst):
    return ('      <details class="dd">\n        <summary class="link">' + h + '</summary>\n        <div class="dd-lista">\n'
            + ''.join(f'          <a href="{u}">{t}</a>\n' for u, t in lst) + '        </div>\n      </details>\n')


arquivos = [f for f in glob.glob(str(RAIZ / '**/index.*'), recursive=True) + [str(RAIZ / 'contato.php'), str(RAIZ / 'index-en.html')]
            if 'bak' not in f and not f.endswith('.backup') and Path(f).parent.name not in NOVAS]
alterados = 0
for f in arquivos:
    s = open(f, encoding='utf-8').read()
    o = s
    en = f.endswith('index-en.html')
    EQ, PR, h_eq, h_pr = (EQ_EN, PR_EN, 'Equipment', 'Processes') if en else (EQ_PT, PR_PT, 'Equipamentos', 'Processos')
    s = re.sub(r'<div class="hd-dd"><a href="/guindastes-e-plataformas/" aria-haspopup="true">Equipamentos</a><div class="hd-menu">.*?</div></div>'
               r'(?:<div class="hd-dd"><a href="/guindastes-e-plataformas/#proc-t" aria-haspopup="true">Processos</a><div class="hd-menu">.*?</div></div>)?',
               lambda m: ('<div class="hd-dd"><a href="/guindastes-e-plataformas/" aria-haspopup="true">Equipamentos</a><div class="hd-menu">' + links(EQ) + '</div></div>'
                          '<div class="hd-dd"><a href="/guindastes-e-plataformas/#proc-t" aria-haspopup="true">Processos</a><div class="hd-menu">' + links(PR) + '</div></div>'),
               s, count=1, flags=re.S)
    s = re.sub(r'      <details class="dd">\s*<summary class="link">(?:Equipamentos|Equipment)</summary>.*?</details>\n'
               r'(?:      <details class="dd">\s*<summary class="link">(?:Processos|Processes)</summary>.*?</details>\n)?',
               lambda m: det(h_eq, EQ) + det(h_pr, PR), s, count=1, flags=re.S)
    i = s.find('<footer')
    head, foot = s[:i], s[i:]
    foot = re.sub(r'<div class="ft-col"><h4>(?:Equipamentos|Equipment)</h4>.*?</div>(?:<div class="ft-col"><h4>(?:Processos|Processes)</h4>.*?</div>)?',
                  lambda m: f'<div class="ft-col"><h4>{h_eq}</h4>' + links(EQ) + f'</div><div class="ft-col"><h4>{h_pr}</h4>' + links(PR) + '</div>',
                  foot, count=1, flags=re.S)
    s = head + foot
    if s != o:
        open(f, 'w', encoding='utf-8').write(s)
        alterados += 1
print('alterados', alterados, 'de', len(arquivos))
