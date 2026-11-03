#!/usr/bin/env python3
"""
Converte as páginas de campanha para o padrão novo. /locadoras/ mantém o formulário (mesmos campos, mesmo
envio por fetch para /locadoras/enviar-lead.php e o evento de conversão oravelLead('locadoras')).
Uso: python3 gerar_campanha.py <html_publicado_dir> <public_html>
"""
import html as H
import re
import sys
from pathlib import Path

import converter as c
import tema as t

PUB, RAIZ = Path(sys.argv[1]), Path(sys.argv[2])

# ---------------------------------------------------------------- páginas sem formulário
for arq, rel, slug, mock, eyebrow in [
    ('pequenas-locadoras_html.html', 'pequenas-locadoras.html', '/pequenas-locadoras.html', t.mk_painel, 'Pequenas locadoras'),
    ('gestao-locadoras_.html', 'gestao-locadoras/index.html', '/gestao-locadoras/', t.mk_os, 'Locadoras e oficinas'),
    ('para-quem-tem-sistema_php.html', 'para-quem-tem-sistema.php', '/para-quem-tem-sistema.php', t.mk_os, 'Quem já tem sistema'),
    ('para-quem-nao-tem-sistema_php.html', 'para-quem-nao-tem-sistema.php', '/para-quem-nao-tem-sistema.php', t.mk_painel, 'Quem ainda não tem sistema'),
]:
    h = (PUB / arq).read_text(encoding='utf-8')
    ti = H.unescape(re.search(r'<title>(.*?)</title>', h, re.S).group(1).strip())
    de = H.unescape((re.search(r'<meta name="description" content="(.*?)"', h) or [None, ti])[1])
    out = c.converter(PUB / arq, slug, ti, de, mock(), eyebrow)
    p = RAIZ / rel
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(out, encoding='utf-8')
    print('ok', rel)

# ---------------------------------------------------------------- /locadoras/ (com formulário)
h = (PUB / 'locadoras_.html').read_text(encoding='utf-8')
ti = H.unescape(re.search(r'<title>(.*?)</title>', h, re.S).group(1).strip())
de = H.unescape((re.search(r'<meta name="description" content="(.*?)"', h) or [None, ti])[1])
h1, sub, secoes, ic = c.estrutura(c.tokens(h))
sub_txt = next((s for s in sub if len(s) > 40), de)
retirar = ('Módulo Fiscal', 'Módulo Contabilidade')   # a Oravel não emite nota fiscal nem faz contabilidade

FORM = f"""<div class="form" style="color:#0f172a"><div id="form-status" class="fmsg" hidden role="status" aria-live="polite"></div>
<form id="lead-form" action="/locadoras/enviar-lead.php" method="POST" novalidate style="display:grid;gap:14px">
<div><label for="f-nome">Nome completo</label><input type="text" id="f-nome" name="nome" autocomplete="name" required></div>
<div class="r2"><div><label for="f-empresa">Empresa</label><input type="text" id="f-empresa" name="empresa" autocomplete="organization" required></div>
<div><label for="f-telefone">WhatsApp / Telefone</label><input type="tel" id="f-telefone" name="telefone" autocomplete="tel" required></div></div>
<div><label for="f-email">E-mail</label><input type="email" id="f-email" name="email" autocomplete="email" required></div>
<div><label for="f-frota">Tamanho do parque de equipamentos</label><select id="f-frota" name="frota" required><option value="" disabled selected>Selecione uma faixa</option>
<option value="ate-15">Até 15 equipamentos</option><option value="16-50">16 a 50 equipamentos</option><option value="51-150">51 a 150 equipamentos</option><option value="mais-150">Mais de 150 equipamentos</option></select></div>
<input type="hidden" name="origem" value="landing-locadoras">
<div class="hp" aria-hidden="true"><label for="f-site">Não preencha este campo</label><input type="text" id="f-site" name="site" tabindex="-1" autocomplete="off"></div>
<button type="submit" class="btn btn-p" id="submit-btn" style="width:100%">Quero uma demonstração</button>
<p class="fine">Seus dados são usados apenas para contato comercial da Oravel. Sem spam.</p></form></div>"""

JS = """<script>(function(){var form=document.getElementById('lead-form'),box=document.getElementById('form-status'),btn=document.getElementById('submit-btn');
function show(k,m){box.className='fmsg '+k;box.hidden=false;box.textContent=m;box.scrollIntoView({behavior:'smooth',block:'nearest'})}
form.addEventListener('submit',function(e){e.preventDefault();
if(form.querySelector('#f-site').value.trim()!==''){show('ok','Obrigado! Em breve nossa equipe entra em contato.');form.reset();return}
btn.disabled=true;btn.textContent='Enviando...';box.hidden=true;
fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}})
.then(function(r){if(!r.ok)throw new Error('http-'+r.status);return r.json()})
.then(function(d){if(d&&d.ok){show('ok','Recebemos seus dados! Nossa equipe entra em contato em até 1 dia útil.');form.reset();if(window.oravelLead){window.oravelLead('locadoras')}}else{throw new Error((d&&d.error)||'unknown')}})
.catch(function(){show('err','Não foi possível enviar agora. Tente novamente ou fale com a gente pelo WhatsApp/e-mail contato@oravel.com.br.')})
.finally(function(){btn.disabled=false;btn.textContent='Quero uma demonstração'})})})();</script>"""

hero = ('<section class="hero"><div class="wrap"><div><div class="eyebrow">ERP para locadoras</div><h1>' + H.escape(h1 or 'O ERP para a gestão completa de locadoras.') + '</h1>'
        '<p class="sub">' + H.escape(sub_txt) + '</p><ul class="trust"><li>Feito por quem entende de locação</li><li>Migração assistida</li><li>Funciona ao lado do seu sistema</li></ul></div>'
        f'<div id="agende"><h2 style="color:#fff;font-size:22px;margin-bottom:14px">Agende uma demonstração</h2>{FORM}</div></div></section>')
corpo, n = hero, 0
for s in secoes:
    if s['h2'].startswith(('Agende', 'Pronto para ver')):
        continue
    cards = [cd for cd in s['cards'] if not cd[0].startswith(retirar)]
    if not cards:
        continue
    n += 1
    if 'implanta' in s['h2'].lower():
        corpo_s = '<div class="steps">' + ''.join(f'<div class="step"><h3>{H.escape(a)}</h3><p>{H.escape(b)}</p></div>' for a, b in cards) + '</div>'
    else:
        corpo_s = ('<div class="grid3">' + ''.join(f'<div class="card">{t.ic(ic[i % len(ic)])}<h3>{H.escape(a)}</h3><p>{H.escape(b)}</p></div>' for i, (a, b) in enumerate(cards)) + '</div>')
    corpo += t.secao('ERP para locadoras', H.escape(s['h2']), H.escape(s['intro']) if s['intro'] else None, corpo_s, alt=n % 2 == 0)
corpo += ('<section class="sec"><div class="wrap"><div class="cta"><h2>Pronto para ver a Oravel funcionando na sua operação?</h2><p>Agende uma demonstração e veja o sistema com o cenário real da sua locadora.</p>'
          '<div class="hero-act"><a class="btn btn-p" href="#agende">Agendar uma demonstração</a></div></div></div></section>')
pg = t.pagina_ref(ti, de, 'https://oravel.com.br/locadoras/', corpo, {'@context': 'https://schema.org', '@type': 'WebPage', 'name': ti, 'url': 'https://oravel.com.br/locadoras/'}, JS)
d = RAIZ / 'locadoras'
d.mkdir(parents=True, exist_ok=True)
(d / 'index.html').write_text(pg, encoding='utf-8')
print('ok locadoras/index.html (cartões retirados:', [cd[0] for s in secoes for cd in s['cards'] if cd[0].startswith(retirar)], ')')
