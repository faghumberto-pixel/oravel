#!/usr/bin/env python3
"""Acrescenta "CRM e vendas" e "IA no campo e na gestão" ao menu Recursos das páginas do site (idempotente).

Páginas do tema novo (menu .dd-menu): item no menu do computador, no menu do celular e na coluna Recursos do rodapé.
Páginas antigas (menu .hd-menu): link "Inteligência artificial" em Soluções (o "Vendas" já existe lá).

Uso: python3 atualizar_menu_crm_ia.py <pasta> [--listar]
  <pasta> é uma raiz de public_html (a do repositório ou uma cópia baixada do servidor).
"""
import glob
import sys
from pathlib import Path

RAIZ = Path(sys.argv[1])
SO_LISTAR = '--listar' in sys.argv

DD_VELHO = '<a href="/recursos/"><strong>Todos os recursos</strong><span>Visão completa da plataforma</span></a>'
DD_NOVO = (DD_VELHO + '<a href="/crm/"><strong>CRM e vendas</strong><span>Funil, propostas e retorno no prazo</span></a>'
           '<a href="/inteligencia-artificial/"><strong>IA no campo e na gestão</strong><span>Diagnóstico, rotas e decisão com dados</span></a>')
LISTA_VELHO = '<a href="/recursos/">Todos os recursos</a>'
LISTA_NOVO = LISTA_VELHO + '<a href="/crm/">CRM e vendas</a><a href="/inteligencia-artificial/">IA no campo e na gestão</a>'
ANTIGO_VELHO = '<a href="/crm/">Vendas</a>'
ANTIGO_NOVO = ANTIGO_VELHO + '<a href="/inteligencia-artificial/">Inteligência artificial</a>'

EXCLUIR = ('bak', '/inc/', '/nova/enterprise/', '/proposta/', '/landingpages/')


def candidatos():
    achados = set()
    for padrao in ('**/*.html', '**/*.php'):
        achados |= {Path(p) for p in glob.glob(str(RAIZ / padrao), recursive=True)}
    return sorted(p for p in achados if not any(x in '/' + str(p.relative_to(RAIZ)) for x in EXCLUIR))


def tipo(s):
    if 'class="dd-menu"' in s and 'Todos os recursos' in s:
        return 'novo'
    if 'class="hd-menu"' in s and ANTIGO_VELHO in s:
        return 'antigo'
    return None


alterados, ja_tinham, ignorados, listados = [], [], 0, []
for p in candidatos():
    s = p.read_text(encoding='utf-8', errors='surrogateescape')
    t = tipo(s)
    if t is None:
        ignorados += 1
        continue
    listados.append((str(p.relative_to(RAIZ)), t))
    if t == 'novo':
        if '<a href="/crm/"><strong>CRM e vendas</strong>' in s:
            ja_tinham.append(str(p.relative_to(RAIZ)))
            continue
        n = s.count(DD_VELHO)
        if n < 1:
            print('AVISO: menu do computador não encontrado em', p.relative_to(RAIZ))
            continue
        novo = s.replace(DD_VELHO, DD_NOVO).replace(LISTA_VELHO, LISTA_NOVO)
    else:
        if '<a href="/inteligencia-artificial/">Inteligência artificial</a>' in s:
            ja_tinham.append(str(p.relative_to(RAIZ)))
            continue
        novo = s.replace(ANTIGO_VELHO, ANTIGO_NOVO, 1)
    if SO_LISTAR:
        continue
    p.write_text(novo, encoding='utf-8', errors='surrogateescape')
    alterados.append(str(p.relative_to(RAIZ)))

if SO_LISTAR:
    for f, t in listados:
        print(t, f)
    print('páginas com menu:', len(listados))
else:
    print('alterados:', len(alterados), '| já tinham:', len(ja_tinham), '| sem menu reconhecido:', ignorados)
    Path(RAIZ / '.menu-alterados.txt').write_text('\n'.join(alterados) + '\n', encoding='utf-8')
