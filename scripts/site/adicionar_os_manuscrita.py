#!/usr/bin/env python3
"""Insere (ou atualiza) na página de IA a seção "A IA lê a OS feita à mão" com o gerador de folhas em PHP. Idempotente.
Uso: python3 adicionar_os_manuscrita.py <public_html>"""
import re
import sys
from pathlib import Path

import mockups_v2 as m

ARQ = Path(sys.argv[1]) / 'inteligencia-artificial' / 'index.html'
INI, FIM = '<!--os-manuscrita:inicio-->', '<!--os-manuscrita:fim-->'
CSS_INI, CSS_FIM = '/*os-manuscrita:css-inicio*/', '/*os-manuscrita:css-fim*/'

CSS = CSS_INI + m.CSS_OS + """
.v2-split{display:grid;gap:40px;align-items:center;grid-template-columns:minmax(0,1fr)}.v2-split>*{min-width:0}
@media(min-width:1000px){.v2-split{grid-template-columns:minmax(0,.95fr) minmax(0,1.2fr);gap:56px}}
.v2-split h2{font-size:clamp(26px,3.6vw,38px);line-height:1.12;margin:10px 0 16px}.v2-split p{color:var(--ink-soft);font-size:17px}
.v2-split .aviso{font-size:14px;margin-top:14px;color:var(--ink-soft)}
""" + CSS_FIM

SECAO = INI + (
    '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@500;700&display=swap">'
    '<section class="sec" id="os-manuscrita"><div class="wrap v2-split"><div class="t"><div class="eyebrow">OS no papel, no sistema</div>'
    '<h2>O mecânico escreve no papel. A IA passa para o sistema.</h2>'
    '<p>Nem todo pátio tem tempo ou sinal para digitar. Imprima a ordem de serviço, deixe o mecânico preencher à mão e fotografe: a IA lê a letra e deixa a O.S. pronta para você revisar.</p>'
    '<ol class="v2-pass"><li><span><b>Imprima as folhas.</b> Elas saem numeradas, em A4, com quadros pensados para a IA ler bem.</span></li>'
    '<li><span><b>O mecânico preenche à mão</b> no pátio ou na obra, sem celular nem computador.</span></li>'
    '<li><span><b>Fotografe a folha inteira</b> em Ordens de serviço, Nova, Ler O.S. de papel. A IA preenche cliente, equipamento, horímetro, tipo, serviço, peças e datas.</span></li></ol>'
    '<form class="v2-gen" action="/inteligencia-artificial/os-para-imprimir.php" method="get" target="_blank">'
    '<h3>Gere as folhas para imprimir</h3><div class="g">'
    '<label class="w" for="osg-emp">Nome da locadora (opcional, sai impresso no topo)<input id="osg-emp" name="empresa" maxlength="60" autocomplete="organization" placeholder="Ex.: Locadora Alvorada"></label>'
    '<label for="osg-cop">Quantas folhas<input id="osg-cop" name="copias" type="number" min="1" max="20" value="4" inputmode="numeric"></label>'
    '<label for="osg-ini">Começar no número<input id="osg-ini" name="inicio" type="number" min="1" max="99999" value="1" inputmode="numeric"></label></div>'
    '<div class="a"><button class="btn btn-p" type="submit">Gerar folhas para imprimir</button><small>Abre numa nova aba, pronta para imprimir. Sem cadastro.</small></div></form>'
    '<p class="aviso">A IA pode errar a leitura de nomes e valores: você sempre revisa antes de salvar. Também funciona com formulários de outros modelos, mas a folha da Oravel dá o melhor resultado.</p></div>'
    f'<div>{m.mk_os_manuscrita()}<p class="nota" style="margin-top:14px;font-size:13px;color:var(--ink-soft)">Exemplo ilustrativo, com dados fictícios.</p></div></div></section>'
) + FIM

s = ARQ.read_text(encoding='utf-8')

# CSS (substitui o bloco anterior, se houver)
if CSS_INI in s:
    s = re.sub(re.escape(CSS_INI) + r'.*?' + re.escape(CSS_FIM), lambda _: CSS, s, count=1, flags=re.S)
else:
    s = s.replace('</style>', CSS + '</style>', 1)

# secao: logo depois do hero (primeira </section>)
if INI in s:
    s = re.sub(re.escape(INI) + r'.*?' + re.escape(FIM), lambda _: SECAO, s, count=1, flags=re.S)
else:
    i = s.index('</section>') + len('</section>')
    s = s[:i] + SECAO + s[i:]

ARQ.write_text(s, encoding='utf-8')
print('ok', ARQ, len(s))
