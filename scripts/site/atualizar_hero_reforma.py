#!/usr/bin/env python3
"""
Troca o hero da home (public_html/index.html): texto de alerta sobre o prazo da reforma tributária
(1º de janeiro de 2027) e, no lugar do painel do dono, a tela do módulo fiscal já preparado para IBS e CBS.
A home recebeu ajustes manuais depois de gerada, por isso este script edita o arquivo no lugar
(não regenera). Idempotente: se o hero já foi trocado (marcador rt-dias), não faz nada.

Uso: python3 atualizar_hero_reforma.py <public_html/index.html>
"""
import datetime
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import mockup_fiscal as mf  # noqa: E402

ARQ = Path(sys.argv[1])
s = ARQ.read_text(encoding='utf-8')
if 'id="rt-dias"' in s:
    print('hero já atualizado')
    sys.exit(0)

dias = max(0, (datetime.date(2027, 1, 1) - datetime.date.today()).days)

PILULA = ('<div style="display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;'
          'background:rgba(249,115,22,.16);border:1px solid rgba(249,115,22,.55);color:#fdba74;font-weight:700;font-size:13px">'
          '<span aria-hidden="true" style="width:8px;height:8px;border-radius:50%;background:#f97316"></span>'
          'Prazo encerrando: 1º de janeiro de 2027</div>')

TOPO = (
    '<div class="v2h-top"><div>' + PILULA
    + f'<h1 style="margin-top:16px;max-width:22ch">Faltam <span id="rt-dias" style="color:#f97316">{dias}</span> dias para a CBS substituir o PIS/Cofins na sua locadora.</h1>'
    '<p class="sub">A partir de 1º de janeiro de 2027, a locação de equipamentos entra no IBS e na CBS, e o seu crédito depende dos seus dados. '
    'A Oravel prepara cadastro, contratos, módulo fiscal e cobrança antes da virada.</p></div>'
    '<div class="v2h-act"><div class="hero-act"><a class="btn btn-p" href="/reforma-tributaria/">Ver o que muda para locadoras</a>'
    '<a class="btn btn-o" href="/contato.php">Agendar uma demonstração</a></div>'
    '<ul class="trust"><li>Simulador de impacto para a sua locadora</li><li>Módulo fiscal com IBS e CBS</li><li>Funciona ao lado do seu sistema</li></ul>'
    '<p class="v2-nota v2h-n1">Tela ilustrativa, com dados fictícios.</p></div></div>'
    '<script>(function(){var e=document.getElementById("rt-dias");if(!e)return;'
    'var d=Math.max(0,Math.ceil((new Date(2027,0,1).getTime()-Date.now())/864e5));e.textContent=String(d);})();</script>')

i1 = s.index('<div class="v2h-top">')
i2 = s.index('<div class="v2h-dash">')
assert i1 < i2
s = s[:i1] + TOPO + s[i2:]

ini = s.index('<div class="v2h-dash">') + len('<div class="v2h-dash">')
fim = s.index('<p class="v2-nota v2h-n2">', ini)
s = s[:ini] + mf.hero_fiscal() + s[fim:]

ARQ.write_text(s, encoding='utf-8')
print('hero atualizado; dias até a virada:', dias)
