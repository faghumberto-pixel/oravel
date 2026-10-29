#!/usr/bin/env python3
"""Converte o lote de páginas de produto/solução para o padrão novo. Uso: converter_lote.py <originais> <public_html>"""
import re
import sys
from pathlib import Path

import converter as c
import tema as t

ORIG, SAIDA = Path(sys.argv[1]), Path(sys.argv[2])
LOTE = [
    ('erp-cmms', t.mk_painel, 'Locação'),
    ('wms', t.mk_estoque, 'Estoque'),
    ('crm', t.mk_funil, 'Vendas'),
    ('departamento-pessoal', t.mk_ponto, 'Departamento pessoal'),
    ('os', t.mk_os, 'Ordens de serviço'),
    ('software-manutencao-industrial', t.mk_os, 'Manutenção'),
    ('software-montagem-industrial', t.mk_painel, 'Montagem industrial'),
    ('software-gestao-frota-logistica', t.mk_painel, 'Frota e logística'),
    ('software-gestao-de-servicos', t.mk_os, 'Gestão de serviços'),
]
for slug, mock, eyebrow in LOTE:
    f = ORIG / slug / 'index.html'
    h = f.read_text(encoding='utf-8')
    titulo = re.search(r'<title>(.*?)</title>', h, re.S).group(1).strip()
    desc = (re.search(r'<meta name="description" content="(.*?)"', h) or [None, titulo])[1]
    out = c.converter(f, f'/{slug}/', titulo, desc, mock(), eyebrow)
    (SAIDA / slug).mkdir(parents=True, exist_ok=True)
    (SAIDA / slug / 'index.html').write_text(out, encoding='utf-8')
    print('ok', slug, len(out) // 1024, 'KB')
