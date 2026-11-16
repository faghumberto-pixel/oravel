#!/usr/bin/env python3
"""
Adequa o site institucional ao novo posicionamento da Oravel:
"ajudamos locadoras a reduzir equipamento parado, controlar a manutenção e aumentar a rentabilidade, sem obrigar a
trocar tudo de uma vez". O site passa a falar primeiro do PROBLEMA do dono da locadora (e do que ele custa), depois
da solução, e o botão principal deixa de ser "agendar uma demonstração" e vira "solicitar um diagnóstico gratuito".

O que faz (tudo idempotente: se a home já tem o marcador <!--pos-v3-->, não repete):
  1. Home (index.html): título/descrição, hero sobre o problema (a faixa da reforma tributária continua, menor),
     faixa "Já possui ERP? Não precisa trocar", seção "o que cada problema custa", IA simplificada em 4 perguntas,
     "Como fica a rotina" (antes/depois, ilustrativo), a frase da história da Oravel, CTA final e botão do topo.
  2. Cria /calculadora/ ("Quanto custa não controlar a sua locadora?") e /diagnostico/ (formulário que envia para o
     mesmo /contato.php, com o mesmo anti-robô).
  3. sitemap.xml: acrescenta as duas páginas novas (se existir).
  4. --cta-global: troca o botão "Agendar uma demonstração" nas OUTRAS páginas (*.html e *.php) da pasta.

NÃO inventa prova social: não há depoimento, cliente nem número de resultado neste script. Depoimentos e casos reais
entram só com autorização do cliente (ver a seção "Como fica a rotina", que é explicitamente ilustrativa).

Uso: python3 adequar_posicionamento.py <public_html> [--cta-global]
"""
import argparse
import datetime
import html as H
import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import mockups_v2 as m2  # noqa: E402
import tema as t  # noqa: E402

MARCA = '<!--pos-v3-->'
TITULO = 'Oravel | Menos equipamento parado, manutenção em dia e mais rentabilidade para locadoras'
DESC = ('Ajudamos locadoras de equipamentos a reduzir equipamentos parados, controlar a manutenção e aumentar a '
        'rentabilidade da operação, sem obrigar você a trocar tudo de uma vez. Funciona ao lado do sistema que você já usa.')
TURNSTILE_KEY = '0x4AAAAAAEUrIcrw2tIq9KEj'  # chave pública do formulário de contato (a mesma de /contato.php)


# ------------------------------------------------------------------ utilitários
def trocar(s, antigo, novo, nome):
    assert antigo in s, f'âncora não encontrada: {nome}'
    return s.replace(antigo, novo, 1)


def entre(s, ini, fim, novo, nome, incluir_fim=False, a_partir=0):
    i = s.index(ini, a_partir) if ini in s[a_partir:] else -1
    assert i >= 0, f'início não encontrado: {nome}'
    j = s.index(fim, i)
    return s[:i] + novo + s[j + (len(fim) if incluir_fim else 0):]


def fim_da_secao(s, trecho_dentro, nome):
    """Índice logo depois do </section> da seção que contém `trecho_dentro`."""
    assert trecho_dentro in s, f'seção não encontrada: {nome}'
    k = s.index(trecho_dentro)
    return s.index('</section>', k) + len('</section>')


# ------------------------------------------------------------------ blocos da home
def hero_topo():
    return (
        '<div class="v2h-top"><div>'
        '<div style="display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;'
        'background:rgba(249,115,22,.16);border:1px solid rgba(249,115,22,.55);color:#fdba74;font-weight:700;font-size:13px">'
        '<span aria-hidden="true" style="width:8px;height:8px;border-radius:50%;background:#f97316"></span>'
        'Para donos de locadora de equipamentos</div>'
        '<h1 style="margin-top:16px;max-width:19ch">Sua locadora está perdendo dinheiro <span style="color:#f97316">sem perceber.</span></h1>'
        '<p class="sub">Equipamentos parados. Manutenções atrasadas. Contratos espalhados. Horímetro sem controle. '
        'A Oravel nasceu para eliminar esses problemas sem obrigar sua empresa a trocar tudo de uma vez.</p></div>'
        '<div class="v2h-act"><div class="hero-act">'
        f'<a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_LONGO}</a>'
        '<a class="btn btn-o" href="/calculadora/">Quanto custa não controlar?</a></div>'
        '<ul class="trust"><li>Funciona ao lado do sistema que você já usa</li>'
        '<li>Diagnóstico antes de configurar qualquer coisa</li>'
        '<li>Implantação com quem já viveu uma locadora</li></ul>'
        '<p class="v2-nota v2h-n1">Tela ilustrativa, com dados fictícios.</p></div></div>')


def faixa_cbs():
    dias = max(0, (datetime.date(2027, 1, 1) - datetime.date.today()).days)
    return (
        '<div style="background:#0b0f0d;border-top:1px solid rgba(249,115,22,.35);border-bottom:1px solid rgba(249,115,22,.35)">'
        '<div class="wrap" style="padding-block:12px;display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;justify-content:center;text-align:center">'
        '<span style="color:#fdba74;font-weight:700;font-size:14px">Reforma tributária: faltam '
        f'<span id="rt-dias">{dias}</span> dias para a CBS (1º de janeiro de 2027)</span>'
        '<a href="/reforma-tributaria/" style="color:#fff;font-weight:700;font-size:14px;text-decoration:underline">Ver o que muda para locadoras</a></div>'
        '<script>(function(){var e=document.getElementById("rt-dias");if(!e)return;'
        'var d=Math.max(0,Math.ceil((new Date(2027,0,1).getTime()-Date.now())/864e5));e.textContent=String(d);})();</script></div>')


def faixa_erp():
    return (
        '<section class="sec" style="padding-block:34px"><div class="wrap">'
        '<div style="display:grid;gap:18px;align-items:center;background:var(--accent-soft);border:1px solid #fed7aa;'
        'border-radius:20px;padding:26px 28px">'
        '<div><div class="eyebrow">Já tem um sistema?</div>'
        '<h2 style="font-size:clamp(24px,3.4vw,34px);margin:6px 0 8px">Já possui ERP? Ótimo. Não precisa trocar.</h2>'
        '<p style="margin:0;color:var(--ink-soft);max-width:70ch">A Oravel trabalha ao lado do sistema que você já usa. '
        'Você mantém o que funciona e ganha controle de manutenção, frota, estoque e vendas num painel só, '
        'sem parar a operação e sem trocar tudo de uma vez.</p></div>'
        f'<div><a class="btn btn-p" href="{t.CTA_URL}">Ver como funciona na minha operação</a></div></div></div></section>')


def secao_custo():
    cartoes = t.cards([
        ('pm', 'Preventiva que venceu sem ninguém ver',
         'Evite que um equipamento de centenas de milhares de reais fique parado porque a revisão passou do prazo.', '/processo-manutencao/'),
        ('eq', 'Horímetro sem controle',
         'Saiba quando cada equipamento chega na hora da revisão e cobre as horas usadas além do contrato.', '/central-de-conhecimento/horimetro.php'),
        ('co', 'Contrato e cobrança espalhados',
         'Contrato, medição e cobrança no mesmo lugar, sem planilha paralela e sem esquecer de faturar.', '/processo-cobranca-automatica/'),
        ('av', 'Equipamento devolvido com defeito',
         'Registre o estado do equipamento na saída e na volta, com fotos, para ter o histórico quando precisar cobrar o dano.', '/equipamento-devolvido-com-defeito/'),
        ('es', 'Peça em falta na hora errada',
         'A peça certa no estoque quando a manutenção precisa dela, em vez de equipamento parado esperando compra.', '/processo-gestao-de-estoques/'),
        ('al', 'Proposta que esfriou sem retorno',
         'O funil avisa quando um negócio está parado, para a venda não se perder por falta de acompanhamento.', '/crm/'),
    ])
    return (
        '<section class="sec alt" id="custo"><div class="wrap"><div class="sec-h"><div class="eyebrow">O que isso custa</div>'
        '<h2>Cada problema que você não vê vira dinheiro parado</h2>'
        '<p>Quase nunca é falta de esforço: é falta de visibilidade. Veja o que a Oravel evita no dia a dia da locadora.</p></div>'
        f'<div class="grid3">{cartoes}</div>'
        f'<p style="margin-top:22px"><a class="more" href="/calculadora/">Calcular quanto isso pode custar na minha locadora</a></p>'
        '</div></section>')


def secao_rotina():
    corpo = t.vs(
        ['Preventiva controlada em planilha e na memória do mecânico',
         'Contratos, medições e cobranças em arquivos diferentes',
         'Defeito descoberto só depois que o cliente já devolveu o equipamento',
         'Quem decide pergunta para três pessoas o que está parado'],
        ['Preventiva avisada pelo horímetro e pela data, antes de vencer',
         'Contrato, medição e cobrança no mesmo lugar',
         'Estado do equipamento registrado na saída e na volta, com fotos',
         'O painel do dono mostra o que está parado, onde e por quê'],
        'Hoje, em muitas locadoras', 'Com a Oravel')
    return t.secao('Na prática', 'Como fica a rotina da sua locadora', '', corpo)


IA_ITENS_V3 = ['Qual equipamento tem maior risco de quebrar?', 'Qual cliente gera mais manutenção?',
               'Quais contratos estão dando prejuízo?', 'Quais preventivas precisam acontecer esta semana?']
IA_ITENS = ['Quais equipamentos têm maior risco de quebrar?', 'Quais propostas têm maior chance de fechar?',
            'Quais clientes estão dando prejuízo?']


def bloco_ia(itens=None, botao='Ver todas as aplicações da IA'):
    itens = itens or IA_ITENS
    li = ''.join(f'<li><span><b>{x}</b></span></li>' for x in itens)
    return ('<div class="eyebrow">Inteligência artificial</div><h2>Pergunte à Oravel, em vez de procurar em planilha</h2>'
            '<p>A IA da Oravel responde perguntas como:</p>'
            f'<ul class="v2-ck">{li}</ul>'
            '<p>Quem calcula é o sistema; a IA interpreta, prioriza e sugere o próximo passo, no celular do técnico e no painel do gestor.</p>'
            f'<a class="more" href="/inteligencia-artificial/">{botao}</a>')


def citacao_historia():
    return ('<div style="border-left:4px solid #f97316;padding:2px 0 2px 16px;margin:0 0 22px;max-width:60ch">'
            '<p style="margin:0;font-size:clamp(20px,2.6vw,26px);font-weight:800;line-height:1.25;color:#fff">'
            '“A tecnologia que nasceu da cicatriz, não da planilha.”</p>'
            '<p style="margin:8px 0 0;color:#cbd5e1">Software feito por quem já esteve do outro lado do balcão: '
            'na locação, na manutenção e no campo.</p></div>')


def cta_final_home():
    return ('<div class="cta"><h2>Descubra onde sua locadora está perdendo dinheiro</h2>'
            '<p>Conte como é a sua operação e receba um retorno sobre onde está o dinheiro parado. '
            'Depois, teste por 30 dias, com acompanhamento, treinamento e implementação inclusos. '
            'A Oravel complementa o sistema que você já usa.</p>'
            f'<div class="hero-act"><a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_LONGO}</a>'
            '<a class="btn btn-o" href="/contato.php">Prefiro agendar uma demonstração</a></div>')


# ------------------------------------------------------------------ home
def adequar_home(s):
    if MARCA in s:
        return s, False

    # título, descrição, redes sociais e dados estruturados
    s = re.sub(r'<title>.*?</title>', f'<title>{H.escape(TITULO)}</title>', s, count=1, flags=re.S)
    s = re.sub(r'(<meta name="description" content=")[^"]*(")', lambda m: m.group(1) + H.escape(DESC) + m.group(2), s, count=1)
    s = re.sub(r'(<meta property="og:title" content=")[^"]*(")', lambda m: m.group(1) + H.escape(TITULO) + m.group(2), s, count=1)
    s = re.sub(r'(<meta property="og:description" content=")[^"]*(")', lambda m: m.group(1) + H.escape(DESC) + m.group(2), s, count=1)
    s = re.sub(r'("description":\s*")[^"]*(")', lambda m: m.group(1) + json.dumps(DESC, ensure_ascii=False)[1:-1] + m.group(2), s, count=1)

    # hero: texto sobre o problema + painel do dono (no lugar do módulo fiscal)
    s = entre(s, '<div class="v2h-top">', '<div class="v2h-dash">', hero_topo(), 'hero (texto)')
    ini = s.index('<div class="v2h-dash">') + len('<div class="v2h-dash">')
    fim = s.index('<p class="v2-nota v2h-n2">', ini)
    s = s[:ini] + m2.hero_dashboard() + s[fim:]

    # logo abaixo do hero: faixa da reforma tributária (menor) e "já possui ERP?"
    pos = fim_da_secao(s, '<section class="hero v2h">', 'hero')
    s = s[:pos] + MARCA + faixa_cbs() + faixa_erp() + s[pos:]

    # depois da seção das quatro perguntas: o que cada problema custa
    pos = fim_da_secao(s, 'Você consegue responder estas quatro perguntas', 'quatro perguntas')
    s = s[:pos] + secao_custo() + s[pos:]

    # IA: quatro perguntas, simples
    s = entre(s, '<div class="eyebrow">Inteligência artificial</div>', '<a class="more" href="/inteligencia-artificial/">Ver a IA da Oravel</a>',
              bloco_ia(), 'IA', incluir_fim=True)

    # antes de "Quem já viveu uma locadora": como fica a rotina (antes/depois)
    s = trocar(s, '<section class="sec dark on-dark" id="implantacao">', secao_rotina() + '<section class="sec dark on-dark" id="implantacao">', 'implantação')

    # a frase da história da Oravel, que antes só aparecia em /sobre/
    s = trocar(s, '<div class="eyebrow">Treinamento e acompanhamento</div>', citacao_historia() + '<div class="eyebrow">Treinamento e acompanhamento</div>', 'história')

    # CTA final
    s = re.sub(r'<div class="cta"><h2>Veja a Oravel na rotina da sua locadora</h2>.*?<div class="hero-act">.*?</div>',
               lambda m: cta_final_home(), s, count=1, flags=re.S)
    assert 'Descubra onde sua locadora está perdendo dinheiro' in s, 'CTA final não substituído'

    # botão do topo e do menu (as demais ocorrências de "Agendar uma demonstração" viram o botão secundário acima)
    s = trocar(s, '<div class="top-cta"><a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a></div>',
               f'<div class="top-cta"><a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_CURTO}</a></div>', 'botão do topo')
    s = trocar(s, '<a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a></div></div></header>',
               f'<a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_LONGO}</a></div></div></header>', 'botão do menu')
    return s, True


def trocar_botao_global(s):
    """Páginas internas: o botão principal passa a pedir o diagnóstico (o topo/menu já vem do tema nas páginas novas)."""
    n0 = s
    s = s.replace('<div class="top-cta"><a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a></div>',
                  f'<div class="top-cta"><a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_CURTO}</a></div>')
    s = s.replace('<a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a></div></div></header>',
                  f'<a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_LONGO}</a></div></div></header>')
    s = s.replace('<div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a></div>',
                  f'<div class="hero-act"><a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_LONGO}</a><a class="btn btn-o" href="/contato.php">Prefiro agendar uma demonstração</a></div>')
    # hero das páginas internas: o botão principal (seguido de outro, ex. "Ver como funciona") também pede o diagnóstico
    s = s.replace('<a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a>',
                  f'<a class="btn btn-p" href="{t.CTA_URL}">{t.CTA_LONGO}</a>')
    return s, s != n0


# ------------------------------------------------------------------ segunda rodada (v4)
MARCA4 = '<!--pos-v4-->'


def promessas_hero():
    itens = ['Reduza equipamentos parados.', 'Antecipe manutenções.', 'Controle contratos.',
             'Tenha toda a operação em um único lugar.']
    li = ''.join(f'<li style="display:flex;gap:10px;align-items:flex-start"><span aria-hidden="true" style="color:#f97316;font-weight:800">&#10003;</span>{x}</li>' for x in itens)
    return ('<ul style="list-style:none;padding:0;margin:18px 0 0;display:grid;gap:8px;color:#fff;font-weight:600;font-size:clamp(16px,2vw,19px)">'
            f'{li}</ul>')


def secao_como_comeca():
    passos = t.passos([
        ('Você já possui ERP', 'Ele continua rodando. Não há troca de sistema nem parada da operação.'),
        ('Existe um problema específico', 'Equipamento parado, manutenção atrasada, contrato ou cobrança: começamos pelo que mais dói.'),
        ('Implantamos apenas um módulo', 'Só o que resolve esse problema, com treinamento e acompanhamento.'),
        ('Depois você decide se quer expandir', 'Os outros módulos conversam com o primeiro, quando e se fizer sentido para você.'),
    ])
    return t.secao('Como normalmente começa', 'Quatro passos, sem trocar tudo de uma vez', '', passos, alt=True)


def adequar_home_v4(s):
    if MARCA4 in s or MARCA not in s:
        return s, False
    # promessa logo abaixo do título (primeira dobra)
    ini = s.index('<p class="sub">')
    fim = s.index('</p>', ini) + 4
    s = s[:fim] + promessas_hero() + s[fim:]
    # "Como normalmente começa", antes de "Como começar"
    k = s.index('<section class="sec', s.rindex('<section', 0, s.index('Em poucos dias, sua operação rodando')))
    s = s[:k] + secao_como_comeca() + s[k:]
    # IA: 3 aplicações + botão
    s = trocar(s, bloco_ia(IA_ITENS_V3, 'Ver a IA da Oravel'), bloco_ia(), 'IA v4')
    # frase de posicionamento junto da história
    s = trocar(s, 'na locação, na manutenção e no campo.</p></div>',
               'na locação, na manutenção e no campo.</p>'
               '<p style="margin:14px 0 0;color:#fff;font-weight:600;max-width:60ch">Ajudamos locadoras a reduzir equipamentos parados, controlar a manutenção '
               'e aumentar a rentabilidade da operação. O software é apenas a ferramenta.</p></div>', 'posicionamento')
    # módulos: frase de abertura
    s = trocar(s, '<h2>Comece pelo que dói mais e cresça no seu ritmo</h2>',
               '<h2>Você não precisa contratar tudo. Comece pelo problema que mais dói hoje.</h2>', 'módulos')
    return s.replace(MARCA, MARCA + MARCA4, 1), True


# ------------------------------------------------------------------ páginas novas
CSS_NOVAS = """
.calc{display:grid;gap:26px;align-items:start}
@media(min-width:900px){.calc{grid-template-columns:1.05fr .95fr}}
.calc input[type=number]{width:100%;font:inherit;font-size:16px;padding:12px;border:1px solid #c3c9d3;border-radius:10px;background:#fff}
.calc input[type=range]{width:100%;accent-color:#f97316}
.calc-res{background:var(--navy-2);color:#fff;border-radius:22px;padding:30px 28px}
.calc-res .k{font-size:14px;color:#cbd5e1;font-weight:600}
.calc-res .v{font-family:Manrope,Inter,sans-serif;font-weight:800;font-size:clamp(34px,6vw,52px);line-height:1.05;color:#fdba74;margin:6px 0 4px}
.calc-res .sub2{color:#cbd5e1;font-size:14px;margin:0 0 18px}
.calc-res .ev{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:0 0 20px}
.calc-res .ev div{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:14px;padding:14px}
.calc-res .ev b{display:block;font-size:20px;color:#fff}
.calc-res .ev span{font-size:12.5px;color:#cbd5e1}
.calc-res .fine2{font-size:12.5px;color:#94a3b8;margin:14px 0 0;line-height:1.5}
.dg{display:grid;gap:26px;align-items:start}
@media(min-width:900px){.dg{grid-template-columns:.9fr 1.1fr}}
.form .hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.form select{width:100%;font:inherit;font-size:16px;padding:12px;border:1px solid #c3c9d3;border-radius:10px;background:#fff}
.form .r2{display:grid;gap:14px}
@media(min-width:700px){.form .r2{grid-template-columns:1fr 1fr}}
.form .fine{font-size:12.5px;color:var(--ink-soft);margin:0}
.nota-calc{background:var(--accent-soft);border:1px solid #fed7aa;border-radius:12px;padding:12px 14px;font-size:14px}
"""

JS_CALC = """
(function(){
  var f=function(id){return document.getElementById(id)};
  var brl=function(v){return v.toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0})};
  // limites razoáveis: dígito a mais no campo não vira "R$ 998 bilhões" na tela
  function num(id,min,max){var v=parseFloat(String(f(id).value).replace(',','.'));if(!isFinite(v)||v<min)return min;return Math.min(v,max)}
  function calc(){
    var eq=Math.max(1,Math.round(num('c-eq',1,5000))), di=num('c-di',0,50000), pa=num('c-pa',0,1000), dd=num('c-dd',0,60), ev=num('c-ev',0,100)/100;
    var dias=pa*dd, maxDias=eq*30, limitado=dias>maxDias;
    if(limitado)dias=maxDias;           // não dá para perder mais do que a frota inteira parada o mês todo
    var mes=dias*di, evMes=mes*ev;
    var pct=Math.min(100,dias/maxDias*100);
    f('c-ev-v').textContent=Math.round(ev*100)+'%';
    f('r-mes').textContent=brl(mes);
    f('r-det').textContent=limitado?'Limitado ao máximo possível: todos os '+eq+' equipamentos parados o mês inteiro. Confira os números informados.':pa+' paradas × '+dd+' dias × '+brl(di)+' de diária';
    f('r-pct').textContent=pct.toLocaleString('pt-BR',{maximumFractionDigits:1})+'% do tempo da sua frota';
    f('r-ev-mes').textContent=brl(evMes);
    f('r-ev-ano').textContent=brl(evMes*12);
    f('r-cta').href='/diagnostico/?eq='+eq+'&perda='+Math.round(mes);
  }
  ['c-eq','c-di','c-pa','c-dd','c-ev'].forEach(function(id){f(id).addEventListener('input',calc)});
  calc();
})();
"""

JS_DIAG = """
(function(){
  var q=new URLSearchParams(location.search), eq=q.get('eq'), perda=q.get('perda');
  var nota=document.getElementById('nota-calc');
  if(perda&&/^\\d+$/.test(perda)){
    nota.hidden=false;
    nota.textContent='Estimativa da calculadora: cerca de '+Number(perda).toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0})+' por mês em equipamentos parados. Vamos conferir isso com os dados da sua operação.';
  }
  document.getElementById('dg-form').addEventListener('submit',function(){
    var v=function(id){var e=document.getElementById(id);return e&&e.value?e.options?e.options[e.selectedIndex].text:e.value:'não informado'};
    var linhas=['[Diagnóstico gratuito]','Equipamentos: '+v('d-eq'),'Sistema hoje: '+v('d-sis'),'Maior dor: '+v('d-dor')];
    if(perda&&/^\\d+$/.test(perda))linhas.push('Estimativa da calculadora: R$ '+perda+'/mês'+(eq&&/^\\d+$/.test(eq)?' ('+eq+' equipamentos)':''));
    var livre=(document.getElementById('d-obs').value||'').trim();
    if(livre)linhas.push('Observações: '+livre);
    document.getElementById('message').value=linhas.join('\\n');
  });
})();
"""


def pagina_calculadora():
    corpo = (
        t.hero_pg('Calculadora', 'Quanto custa não controlar a sua locadora?',
                  'Responda quatro números e veja uma estimativa de quanto a sua locadora pode estar deixando de faturar por mês '
                  'com equipamentos parados por manutenção que poderia ter sido evitada.', ctas=False)
        + '<section class="sec"><div class="wrap"><div class="calc">'
        '<form class="form" id="calc" onsubmit="return false" aria-label="Calculadora de equipamentos parados">'
        '<div class="r2"><div><label for="c-eq">Quantos equipamentos você tem?</label><input id="c-eq" type="number" min="1" inputmode="numeric" value="40"></div>'
        '<div><label for="c-di">Valor médio da diária (R$)</label><input id="c-di" type="number" min="0" inputmode="decimal" value="350"></div></div>'
        '<div class="r2"><div><label for="c-pa">Paradas por manutenção corretiva, por mês</label><input id="c-pa" type="number" min="0" inputmode="numeric" value="6"></div>'
        '<div><label for="c-dd">Dias parado em cada ocorrência</label><input id="c-dd" type="number" min="0" step="0.5" inputmode="decimal" value="3"></div></div>'
        '<div><label for="c-ev">Parte dessas paradas que uma preventiva em dia evitaria: <b id="c-ev-v">30%</b></label>'
        '<input id="c-ev" type="range" min="10" max="60" step="5" value="30"></div>'
        '<p class="fine">Corretiva é a manutenção que acontece depois da quebra. Troque os números pelos da sua operação: o resultado muda na hora.</p></form>'
        '<div class="calc-res" aria-live="polite"><div class="k">Você pode estar deixando de faturar, por mês</div>'
        '<div class="v" id="r-mes">R$ 0</div>'
        '<p class="sub2" id="r-det"></p>'
        '<p class="sub2" style="margin-top:-8px">Isso equivale a <b id="r-pct" style="color:#fff"></b> parado.</p>'
        '<div class="ev"><div><b id="r-ev-mes"></b><span>por mês poderia ser evitado</span></div>'
        '<div><b id="r-ev-ano"></b><span>por ano</span></div></div>'
        f'<a class="btn btn-p" id="r-cta" href="{t.CTA_URL}">Descobrir onde estou perdendo dinheiro</a>'
        '<p class="fine2">É uma estimativa simples, feita só com os números que você informou (paradas × dias × diária). '
        'Não é promessa de resultado: o diagnóstico da Oravel usa os dados reais da sua operação.</p></div>'
        '</div></div></section>'
        + t.secao('Como calculamos', 'A conta é simples e você confere cada passo', '',
                  '<ol style="max-width:70ch;line-height:1.7"><li><b>Faturamento parado</b> = paradas por mês × dias parado × valor médio da diária.</li>'
                  '<li><b>Parte evitável</b> = a fatia dessas paradas que uma preventiva em dia evitaria. Você escolhe o percentual no controle acima.</li>'
                  '<li>Não entram custo de peça, mão de obra, multa contratual nem o cliente que você deixou de atender. A perda real costuma ser maior.</li></ol>', alt=True)
        + t.cta_final('Quer saber o número real da sua locadora?', 'Em vez de estimar, um consultor que já viveu locadora olha a sua operação e mostra onde está o dinheiro parado.'))
    pg = t.pagina('Quanto custa não controlar a sua locadora? Calculadora | Oravel',
                  'Calculadora para donos de locadora: estime quanto você deixa de faturar por mês com equipamentos parados por manutenção corretiva.',
                  'https://oravel.com.br/calculadora/', corpo)
    pg = pg.replace('</style>', CSS_NOVAS + '</style>', 1)
    return pg.replace('</main>', f'<script>{JS_CALC}</script></main>', 1)


def pagina_diagnostico():
    def op(*itens):
        return ''.join(f'<option value="{H.escape(x)}">{H.escape(x)}</option>' for x in itens)

    form = (
        '<form class="form" id="dg-form" method="post" action="/contato.php#form">'
        '<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">'
        '<div class="r2"><div><label for="name">Nome *</label><input type="text" id="name" name="name" required></div>'
        '<div><label for="company">Empresa *</label><input type="text" id="company" name="company" required></div></div>'
        '<div class="r2"><div><label for="email">E-mail *</label><input type="email" id="email" name="email" required></div>'
        '<div><label for="phone">WhatsApp *</label><input type="tel" id="phone" name="phone" required></div></div>'
        '<div class="r2"><div><label for="d-eq">Quantos equipamentos?</label><select id="d-eq">'
        + op('Até 20', 'De 21 a 50', 'De 51 a 100', 'Mais de 100') + '</select></div>'
        '<div><label for="d-sis">Como controla hoje?</label><select id="d-sis">'
        + op('Planilha e papel', 'Tenho um ERP', 'Um sistema só de manutenção', 'Não controlo direito') + '</select></div></div>'
        '<div><label for="d-dor">O que mais incomoda hoje?</label><select id="d-dor">'
        + op('Equipamento parado', 'Manutenção atrasada', 'Contratos e cobrança', 'Estoque de peças', 'Vendas e propostas', 'Outro') + '</select></div>'
        '<div><label for="d-obs">Quer contar mais alguma coisa? (opcional)</label>'
        '<textarea id="d-obs" rows="3" style="width:100%;font:inherit;font-size:16px;padding:12px;border:1px solid #c3c9d3;border-radius:10px"></textarea></div>'
        '<textarea id="message" name="message" hidden></textarea>'
        '<p class="nota-calc" id="nota-calc" hidden></p>'
        f'<div class="cf-turnstile" data-sitekey="{TURNSTILE_KEY}"></div>'
        f'<button type="submit" class="btn btn-p" style="width:100%">{t.CTA_LONGO}</button>'
        '<p class="fine">Usamos seus dados só para responder a este pedido, conforme a '
        '<a href="/politica-de-privacidade/" style="color:inherit;text-decoration:underline">Política de Privacidade</a>.</p></form>'
        '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>')

    passos = t.passos([
        ('1. Você conta como é a sua operação', 'Seis respostas rápidas: quantos equipamentos, como controla hoje e o que mais incomoda.'),
        ('2. Um consultor analisa', 'Alguém que já viveu uma locadora olha o seu caso, sem configurar nada antes.'),
        ('3. Você recebe um retorno prático', 'Onde está o dinheiro parado e por onde começar, funcionando ao lado do sistema que você já usa.'),
    ])
    corpo = (
        t.hero_pg('Diagnóstico gratuito', 'Descubra onde sua locadora está perdendo dinheiro',
                  'Conte como é a sua operação. Um consultor que já viveu uma locadora analisa e devolve onde estão os custos escondidos '
                  'e por onde começar, sem trocar o sistema que você usa.', ctas=False)
        + f'<section class="sec"><div class="wrap"><div class="dg"><div><div class="eyebrow">Como funciona</div>{passos}'
        '<p style="margin-top:18px;color:var(--ink-soft)">Prefere falar agora? Chame no WhatsApp '
        f'<a href="{t.WHATS}">{t.FONE}</a>.</p></div><div>{form}</div></div></div></section>'
        + t.faq([
            ('Preciso trocar o sistema que já uso?', 'Não. A Oravel trabalha ao lado do seu sistema atual: você mantém o que funciona e ganha o controle de manutenção, frota, estoque e vendas num painel só.'),
            ('Quanto tempo leva?', 'O retorno sai depois que um consultor entender a sua operação. Entramos em contato pelo WhatsApp ou e-mail que você informar.'),
            ('Preciso ter muitos equipamentos?', 'Não. O diagnóstico serve para locadoras de qualquer tamanho que queiram saber onde estão perdendo dinheiro.'),
        ]))
    pg = t.pagina('Diagnóstico gratuito para locadoras | Oravel',
                  'Peça um diagnóstico gratuito da sua locadora: um consultor que já viveu locadora mostra onde está o dinheiro parado e por onde começar.',
                  'https://oravel.com.br/diagnostico/', corpo)
    pg = pg.replace('</style>', CSS_NOVAS + '</style>', 1)
    return pg.replace('</main>', f'<script>{JS_DIAG}</script></main>', 1)


def atualizar_sitemap(arq):
    s = arq.read_text(encoding='utf-8')
    if '/diagnostico/' in s:
        return False
    novo = ''.join(f'<url><loc>https://oravel.com.br/{p}/</loc></url>' for p in ('calculadora', 'diagnostico'))
    arq.write_text(s.replace('</urlset>', novo + '</urlset>', 1), encoding='utf-8')
    return True


# ------------------------------------------------------------------ principal
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('raiz', help='pasta public_html (ou cópia dela)')
    ap.add_argument('--cta-global', action='store_true', help='troca o botão antigo nas outras páginas da pasta')
    a = ap.parse_args()
    raiz = Path(a.raiz)

    home = raiz / 'index.html'
    s, mudou = adequar_home(home.read_text(encoding='utf-8'))
    if mudou:
        home.write_text(s, encoding='utf-8')
    s, m4 = adequar_home_v4(home.read_text(encoding='utf-8'))
    if m4:
        home.write_text(s, encoding='utf-8')
    print('home:', 'adequada' if mudou else 'já estava adequada (pos-v3)', '| v4:', 'aplicada' if m4 else 'já aplicada')

    for nome, fn in (('calculadora', pagina_calculadora), ('diagnostico', pagina_diagnostico)):
        (raiz / nome).mkdir(exist_ok=True)
        (raiz / nome / 'index.html').write_text(fn(), encoding='utf-8')
        print(f'página /{nome}/ gerada')

    sm = raiz / 'sitemap.xml'
    if sm.exists():
        print('sitemap:', 'atualizado' if atualizar_sitemap(sm) else 'já tinha as páginas')

    if a.cta_global:
        n = 0
        for arq in list(raiz.rglob('*.html')) + list(raiz.rglob('*.php')):
            if arq == home or 'calculadora' in arq.parts or 'diagnostico' in arq.parts:
                continue
            novo, ch = trocar_botao_global(arq.read_text(encoding='utf-8', errors='ignore'))
            if ch:
                arq.write_text(novo, encoding='utf-8')
                n += 1
        print(f'botão principal trocado em {n} página(s)')


if __name__ == '__main__':
    main()
