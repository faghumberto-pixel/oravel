#!/usr/bin/env python3
"""Gera a página /reforma-tributaria/ (isca institucional: reforma tributária para locadoras de equipamentos).
Uso: python3 gerar_reforma_tributaria.py <public_html>   (grava <public_html>/reforma-tributaria/index.html)

Conteúdo: o que já é certo (LC 214/2025), linha do tempo, impacto, consequências de não se adequar a tempo,
simulador de 2027, seis perguntas, o módulo fiscal da Oravel (tela ilustrativa) e autoavaliação.
Regras de marketing: revelar o problema, proteger o diferencial (sem datas de entrega do módulo fiscal).
"""
import datetime
import sys
from pathlib import Path

import mockup_fiscal as mf
import mockups_v2 as m
import tema as t

RAIZ = Path(sys.argv[1])
DIAS = max(0, (datetime.date(2027, 1, 1) - datetime.date.today()).days)
HOJE = datetime.date.today().strftime('%d/%m/%Y')

LOCK = ('<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
        'stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>')

# ------------------------------------------------------------------ hero
hero = (
    '<section class="hero"><div class="wrap"><div>'
    '<div class="rt-alert"><i aria-hidden="true"></i>Prazo encerrando: 1º de janeiro de 2027</div>'
    f'<h1>A reforma tributária chega à locação de equipamentos. Faltam <span id="rt-d" style="color:#f97316">{DIAS}</span> dias.</h1>'
    '<p class="sub">Sua locação nunca pagou ISS. Em 2027, ela passa a pagar IBS e CBS, e o resultado da sua locadora vai depender dos créditos, dos contratos e dos dados que você tiver em ordem até a virada.</p>'
    '<div class="hero-act"><a class="btn btn-p" href="#simulador">Simular o impacto na minha locadora</a>'
    '<a class="btn btn-o" href="#consequencias">O que acontece se eu não me adequar</a></div>'
    '<ul class="trust"><li>Fontes legais citadas em cada ponto</li><li>Simulador com a sua realidade</li><li>Módulo fiscal da Oravel</li></ul>'
    '</div>'
    '<div class="rt-card"><p class="rt-k">CONTAGEM REGRESSIVA</p><p class="rt-big" id="rt-d2">' + str(DIAS) + '</p>'
    '<p class="rt-lead">dias para 1º de janeiro de 2027, quando a CBS passa a valer plenamente e o PIS/Cofins deixa de existir.</p>'
    '<p class="rt-foot">Até <b>15/12/2026</b> o Senado fixa as alíquotas de referência. Hoje elas ainda não existem em lei.</p></div>'
    '</div></section>')

# ------------------------------------------------------------------ o que já é certo
certo = t.secao(
    'O que já é certo', 'Três mudanças que já estão na lei e valem para toda locadora.', None,
    '<div class="grid3">'
    '<div class="card"><h3>Sem ISS não significa sem imposto</h3><p>O STF veda o ISS sobre a locação de bens móveis (Súmula Vinculante 31). Mas a LC 214/2025 trata a locação como operação onerosa com bens, e por isso ela entra no IBS e na CBS.</p></div>'
    '<div class="card"><h3>Saem PIS e Cofins, entra a CBS</h3><p>Em 2027 a CBS substitui o PIS/Cofins. O modelo passa a ser não cumulativo, com crédito amplo sobre as compras. Quem hoje paga 3,65% sem crédito muda de lógica, não só de alíquota.</p></div>'
    '<div class="card"><h3>A nota fiscal passa a contar a história</h3><p>Os documentos fiscais já carregam os campos de IBS e CBS desde 2026, e o crédito de quem aluga depende do imposto destacado corretamente na sua nota. Dado errado vira crédito perdido.</p></div>'
    '</div>')

# ------------------------------------------------------------------ linha do tempo
linha = t.secao(
    'Linha do tempo', '2026 foi o ensaio. O palco é 2027.', None,
    '<div class="rt-tl">'
    '<div><b>2026</b><small>ANO DE TESTE</small><p>CBS de 0,9% e IBS de 0,1%, compensáveis com PIS/Cofins. Campos obrigatórios nos documentos fiscais.</p></div>'
    '<div class="now"><b>2027</b><small>VIRADA DA CBS</small><p>CBS plena, fim do PIS/Cofins e do IPI para a maioria dos produtos. IBS segue em 0,1%.</p></div>'
    '<div><b>2028</b><small>CONSOLIDAÇÃO</small><p>Mantém as regras de 2027 para CBS e IBS. Último ano de IBS simbólico.</p></div>'
    '<div><b>2029–32</b><small>IBS SOBE</small><p>ICMS e ISS caem gradualmente, e o IBS cresce no lugar deles.</p></div>'
    '<div><b>2033</b><small>MODELO PLENO</small><p>ICMS e ISS extintos. IBS e CBS operam por inteiro.</p></div></div>'
    '<div class="rt-note"><b>O ponto que quase ninguém comenta.</b> A locação de bens móveis não tem ICMS nem ISS. Então, na sua operação, o IBS não substitui nada: ele é uma camada nova que cresce de 2029 a 2033. Vale confirmar o efeito disso com o seu contador.</div>',
    alt=True)

# ------------------------------------------------------------------ impacto
impacto = t.secao(
    'O impacto na sua locadora', 'A reforma muda quanto você paga, para quem você cobra e quando o dinheiro entra.',
    'Os números abaixo são o caminho, não o destino: a alíquota final ainda será fixada pelo Senado, e o efeito real depende da sua operação.',
    '<div class="grid4">'
    '<div class="card"><span class="rt-n">1</span><h3>A lógica da carga muda</h3><p>Hoje, no Lucro Presumido, são 3,65% de PIS/Cofins sobre a receita, sem crédito. Em 2027, a estimativa pública para a CBS gira em torno de 9%, mas com crédito sobre as compras. Quem gera crédito pode pagar menos. Quem não comprova, paga mais.</p></div>'
    '<div class="card"><span class="rt-n">2</span><h3>O seu cliente sente de formas diferentes</h3><p>Cliente que também é contribuinte aproveita o imposto como crédito. Pessoa física e quem não gera crédito sente o aumento inteiro. A sua tabela de preços passa a ter dois mundos.</p></div>'
    '<div class="card"><span class="rt-n">3</span><h3>O caixa pode apertar</h3><p>Com o split payment, o imposto é separado no momento do pagamento, e não mais na apuração do mês seguinte. Sem previsão, o capital de giro sente.</p></div>'
    '<div class="card"><span class="rt-n">4</span><h3>O IBS é uma camada nova</h3><p>De 2029 a 2033, o IBS cresce. Como a locação não tinha ICMS nem ISS para ser trocado, o efeito aparece por inteiro e dependerá do crédito que você tiver construído.</p></div>'
    '</div>')

# ------------------------------------------------------------------ consequências
consequencias = (
    '<section class="sec alt" id="consequencias"><div class="wrap"><div class="sec-h"><div class="eyebrow">Se a locadora não se adequar a tempo</div>'
    '<h2>O que está em jogo para quem entrar em 2027 sem os dados em ordem</h2>'
    '<p>Não é motivo para alarme, é motivo para começar agora. Estas são as consequências que as fontes apontam como mais prováveis.</p></div>'
    '<div class="rt-cons">'
    '<div><b>Crédito perdido</b><p>Compra sem imposto destacado corretamente não gera crédito de CBS. E se a sua nota sair errada, quem perde o crédito é o seu cliente.</p></div>'
    '<div><b>Perda de competitividade</b><p>Cliente contribuinte tende a preferir a locadora cujo documento fiscal lhe dá crédito sem retrabalho. Quem não entrega isso entra na negociação em desvantagem.</p></div>'
    '<div><b>Autuação por cruzamento de dados</b><p>Nota, pagamento e cadastro passam a ser cruzados quase em tempo real. Omitir a locação da base do IBS e da CBS é fácil de detectar.</p></div>'
    '<div><b>Contratos que absorvem o imposto</b><p>Contrato de preço fechado, assinado sem cláusula para IBS e CBS, empurra o imposto para a sua margem durante toda a vigência.</p></div>'
    '<div><b>Caixa sem previsão</b><p>O split payment retira o imposto na hora do recebimento. Sem projeção, a locadora descobre o efeito no saldo do banco.</p></div>'
    '<div><b>Perda da dispensa de 2026</b><p>No ano de teste, a dispensa do recolhimento de 1% depende de cumprir as obrigações acessórias. Quem não cumpre, perde.</p></div>'
    '</div>'
    '<p class="rt-fim">Cada um desses pontos se resolve com dado organizado antes da virada, e não depois dela.</p>'
    '<div class="hero-act"><a class="btn btn-d" href="#avaliacao">Medir a prontidão da minha locadora</a>'
    '<a class="btn btn-l" href="' + t.WHATS + '">Falar com um especialista</a></div></div></section>')

# ------------------------------------------------------------------ simulador
simulador = (
    '<section class="sec dark on-dark" id="simulador"><div class="wrap"><div class="sec-h"><div class="eyebrow">Ferramenta interativa</div>'
    '<h2>Simulador de impacto em 2027</h2>'
    '<p>Compare o que a sua locadora paga hoje de PIS/Cofins com a CBS e o IBS estimados para 2027. É uma simulação genérica, e logo abaixo dela mostramos o que ela não consegue enxergar.</p></div>'
    '<div class="rt-sim"><div class="rt-panel"><h3>Parâmetros da sua locadora</h3>'
    '<label for="s-rec">Receita mensal de locação (R$)</label><input id="s-rec" type="number" inputmode="decimal" min="0" step="10000" value="400000">'
    '<label for="s-com">Compras mensais com direito a crédito (R$)</label><input id="s-com" type="number" inputmode="decimal" min="0" step="5000" value="120000">'
    '<p class="hint">Equipamentos, peças, combustível, manutenção e outros insumos da operação.</p>'
    '<fieldset><legend>Regime tributário atual</legend>'
    '<label class="rt-radio"><input type="radio" name="s-reg" value="presumido" checked>Lucro Presumido</label>'
    '<label class="rt-radio"><input type="radio" name="s-reg" value="real">Lucro Real</label></fieldset>'
    '<label for="s-sem">Receita com clientes que não aproveitam crédito: <b id="s-sem-l">30</b>%</label>'
    '<input id="s-sem" type="range" min="0" max="100" value="30">'
    '<p class="hint">Pessoas físicas, Simples Nacional e quem não gera crédito de CBS/IBS.</p>'
    '<label for="s-ali">Alíquota combinada estimada em 2027: <b id="s-ali-l">9,21</b>%</label>'
    '<input id="s-ali" type="range" min="8.8" max="10.9" step="0.01" value="9.21">'
    '<p class="hint">Faixa das projeções públicas para a CBS (cerca de 8,8% a 10,9%). Estimativa, não é alíquota de lei.</p></div>'
    '<div><div class="rt-kp">'
    '<div class="rt-panel"><small>Hoje (PIS/Cofins)</small><b id="o-hoje">R$ 0</b><span id="o-hoje-n"></span></div>'
    '<div class="rt-panel"><small>2027 (CBS + IBS estimados)</small><b id="o-2027">R$ 0</b><span>Receita menos compras creditáveis</span></div>'
    '<div class="rt-panel rt-hi"><small>Diferença por mês</small><b id="o-dif">R$ 0</b><span id="o-dif-n"></span></div></div>'
    '<div class="rt-panel" style="margin-top:16px"><h3>Três cenários de alíquota</h3><div style="overflow-x:auto"><table class="rt-tb"><thead><tr><th>Cenário</th><th>Alíquota</th><th>Imposto em 2027</th><th>Diferença vs hoje</th></tr></thead><tbody id="o-cen"></tbody></table></div>'
    '<p class="rt-exp"><b>Receita exposta a reajuste:</b> <span id="o-exp">R$ 0</span> por mês. É a parte da receita em que o cliente não recupera o imposto como crédito e tende a sentir o aumento por inteiro.</p></div>'
    '<div class="rt-panel rt-gate"><div class="rt-gate-h">' + LOCK + '<h3>O que este simulador não enxerga</h3></div>'
    '<ul><li>O crédito real, nota a nota</li><li>Itens que não geram crédito</li><li>Contratos de preço fechado</li><li>Ativos comprados antes e depois da virada</li><li>O efeito do IBS de 2029 a 2033</li><li>Split payment e impacto no caixa</li></ul>'
    '<a class="btn btn-p" id="o-wa" href="' + t.WHATS + '">Refinar este cenário com os dados da minha locadora</a></div></div></div>'
    '<p class="rt-small">Simulação simplificada, com fins ilustrativos. Hoje: Lucro Presumido a 3,65% sobre a receita, sem crédito; Lucro Real a 9,25% sobre a receita menos as compras creditáveis. 2027: alíquota combinada informada, aplicada sobre a receita menos as compras creditáveis. Não substitui apuração fiscal.</p>'
    '</div></section>')

# ------------------------------------------------------------------ seis perguntas
def pergunta(q, sabe, trava):
    return (f'<details><summary>{q}</summary><p><b>O que se sabe:</b> {sabe}</p>'
            f'<div class="rt-lockl">{LOCK}<span>{trava}</span></div></details>')


PERGUNTAS = [
    ('1. Quanto crédito de CBS a sua locadora realmente gera?',
     'o crédito é amplo e vem do imposto destacado nas notas de compra de equipamentos, peças, combustível e manutenção.',
     'O número da sua locadora depende das suas compras e dos seus documentos.'),
    ('2. O seu mix de clientes muda o preço que você pode praticar?',
     'cliente que também é contribuinte aproveita o imposto como crédito e quase não sente o aumento. Quem não aproveita crédito sente.',
     'Quanto da sua carteira está de cada lado só aparece cruzando seus clientes.'),
    ('3. Quem absorve o imposto nos contratos que já estão assinados?',
     'contratos com preço fechado vão atravessar a virada de 2027, e a cláusula de preço bruto ou líquido passa a importar.',
     'Quais dos seus contratos estão expostos exige ler a sua base, contrato a contrato.'),
    ('4. Equipamento comprado antes e depois da virada gera o mesmo crédito?',
     'até 2026 a compra segue o regime de PIS/Cofins. A partir de 2027, o de CBS. A data da aquisição passa a pesar na decisão de investimento.',
     'O efeito no seu parque de ativos depende do que você comprou e quando.'),
    ('5. Você separa locação pura de locação com operador ou serviço?',
     'hoje o ISS só alcança a parte de serviço (operador, montagem, manutenção) em contratos mistos. A locação pura segue fora do ISS.',
     'Como isso aparece na sua nota hoje define o risco amanhã.'),
    ('6. Seu cadastro e seu sistema aguentam o cruzamento de dados?',
     'o Fisco passa a cruzar nota, pagamento e cadastro quase em tempo real, e o split payment é implantado por fases.',
     'Onde o seu cadastro quebra só aparece quando alguém o examina.'),
]
perguntas = t.secao(
    'O que decide o resultado', 'Seis perguntas que definem se a reforma vai custar ou render.',
    'A lei explica o que muda. Ela não diz o que muda para a sua locadora. Isso depende de dados que estão dentro da sua operação.',
    '<div class="faq rt-faq">' + ''.join(pergunta(*p) for p in PERGUNTAS) + '</div>')

# ------------------------------------------------------------------ como a Oravel prepara
oravel = (
    '<section class="sec alt"><div class="wrap split"><div class="split-t"><div class="eyebrow">Como a Oravel prepara a sua locadora</div>'
    '<h2>Um módulo fiscal que já nasce preparado para IBS e CBS</h2>'
    '<p>A Oravel está construindo o módulo fiscal junto com a operação: o mesmo sistema que cuida de contratos, manutenção, estoque e cobrança passa a organizar os dados que a reforma exige.</p>'
    '<ul class="ticks"><li><b>Cadastro fiscal completo</b> de clientes, equipamentos e itens, com o regime de cada cliente</li>'
    '<li><b>Créditos estimados</b> a partir das compras e da manutenção que já estão registradas</li>'
    '<li><b>Contratos e faturas</b> com IBS e CBS destacados e a cláusula de reajuste à vista</li>'
    '<li><b>Prontidão fiscal no painel do dono:</b> o que está pronto e o que falta, antes da virada</li>'
    '<li><b>Locação pura e serviço separados</b> desde o contrato</li></ul>'
    '<div class="hero-act"><a class="btn btn-d" href="/contato.php">Agendar uma demonstração</a></div></div>'
    '<div>' + mf.hero_fiscal() + '<p class="v2-nota">Tela ilustrativa, com dados fictícios.</p></div></div></section>')

# ------------------------------------------------------------------ autoavaliação
avaliacao = (
    '<section class="sec" id="avaliacao"><div class="wrap"><div class="sec-h"><div class="eyebrow">Autoavaliação · 2 minutos</div>'
    '<h2>Quão preparada está a sua locadora para 1º de janeiro?</h2>'
    '<p>Responda com sinceridade. Cada “Não sei” é um ponto em aberto, e ponto em aberto em 2027 costuma virar dinheiro.</p></div>'
    '<div id="rt-quiz" class="rt-quiz"></div><p id="rt-prog" class="rt-prog" aria-live="polite">0 de 6 respondidas</p>'
    '<div id="rt-res" class="rt-res" hidden><p class="rt-k2">SEU RESULTADO</p><p id="rt-lvl" class="rt-lvl"></p><p id="rt-msg"></p>'
    '<div class="hero-act"><a id="rt-wa" class="btn btn-p" href="' + t.WHATS + '">Quero conversar sobre o meu cenário</a></div>'
    '<p class="rt-small2">WhatsApp ' + t.FONE + '</p></div></div></section>')

# ------------------------------------------------------------------ fundamentação
fundamentos = t.secao(
    'Fundamentação', 'As normas por trás de cada afirmação.', None,
    '<div class="grid3">'
    '<div class="card"><h3>Súmula Vinculante 31, STF</h3><p>O Supremo declarou inconstitucional a incidência de ISS sobre operações de locação de bens móveis. Em contratos mistos, o ISS alcança apenas a parcela de serviço associada.</p></div>'
    '<div class="card"><h3>LC 214/2025, arts. 3º e 4º</h3><p>Incluem a locação, o arrendamento e a cessão temporária entre as operações com bens sujeitas ao IBS e à CBS. É por isso que sem ISS não significa sem imposto.</p></div>'
    '<div class="card"><h3>Documento fiscal na locação pura</h3><p>Em São Paulo, a Prefeitura informou em 2026 que a locação pura de bens móveis não tem NFS-e municipal por enquanto. O tema segue em regulamentação, e o padrão nacional da NFS-e já prevê item para locação de bens móveis.</p></div>'
    '</div>'
    '<p class="rt-src"><b>Fontes:</b> '
    '<a href="https://www.barbieriadvogados.com/reforma-tributaria/">Barbieri Advogados, cronograma</a> · '
    '<a href="https://taxup.com.br/solucoes/reforma-tributaria/locacao-bens-moveis/">Taxup, locação de bens móveis</a> · '
    '<a href="https://www.legisweb.com.br/legislacao/?id=496922">Solução de Consulta SF/DEJUG nº 9/2026 (SP)</a> · '
    '<a href="https://www.facilite.co/aliquota-ibs-cbs-2027">Facilite, alíquotas 2027</a> · '
    '<a href="https://www.migalhas.com.br/depeso/465114/reforma-tributaria--ibs-e-cbs-em-2027-aliquotas-e-proximos-passos">Migalhas, estimativas 2027</a> · '
    '<a href="https://www.migalhas.com.br/depeso/465622/ibs-e-cbs-os-impactos-na-locacao-por-holding-patrimoniais">Migalhas, crédito e documento fiscal</a> · '
    '<a href="https://clmcontroller.com.br/reforma-tributaria/locacao-de-bens-como-fica-a-nfs-e-com-ibs-e-cbs/">CLM Controller, NFS-e e locação de bens</a> · '
    '<a href="https://www.taxgroup.com.br/?p=23660">Tax Group, split payment</a> · '
    '<a href="https://notagateway.com.br/?p=11688">Nota Gateway, 1% em 2026</a></p>'
    f'<p class="rt-src">Conteúdo informativo, atualizado em {HOJE}, com base na EC 132/2023, na LC 214/2025 (com alterações da LC 227/2026) e em estimativas públicas que ainda não são oficiais. '
    'As simulações têm fins ilustrativos e não substituem a orientação do seu contador ou advogado tributarista.</p>')

cta = t.cta_final('Chegue em 2027 com a locadora em ordem',
                  'Converse com quem conhece a rotina de uma locadora e veja o que ainda falta na sua operação antes da virada.')

CSS = m.CSS_V2 + """
.rt-alert{display:inline-flex;align-items:center;gap:8px;padding:7px 14px;border-radius:999px;background:rgba(249,115,22,.16);border:1px solid rgba(249,115,22,.55);color:#fdba74;font-weight:700;font-size:13px}
.rt-alert i{width:8px;height:8px;border-radius:50%;background:#f97316}
.rt-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:18px;padding:30px}
.rt-k{font-size:12px;font-weight:800;letter-spacing:.14em;color:#94a3b8;margin-bottom:8px}
.rt-big{font:800 clamp(80px,12vw,120px)/1 "Manrope",sans-serif;letter-spacing:-.04em;color:var(--accent)}
.rt-lead{font-size:16px;line-height:1.45;margin-top:8px}.rt-foot{margin-top:20px;padding-top:18px;border-top:1px solid rgba(255,255,255,.14);font-size:14px;color:#94a3b8}.rt-foot b{color:#fff}
.rt-tl{display:grid;gap:14px}.rt-tl>div{background:#fff;border:1px solid var(--line);border-radius:14px;padding:20px}
.rt-tl b{display:block;font:800 26px "Manrope",sans-serif}.rt-tl small{display:block;font-size:11px;font-weight:800;letter-spacing:.1em;color:var(--ink-faint);margin:2px 0 10px}
.rt-tl p{font-size:14px;color:var(--ink-soft)}.rt-tl .now{background:var(--navy-2);border:2px solid var(--accent);color:#fff}.rt-tl .now b{color:var(--accent)}.rt-tl .now p,.rt-tl .now small{color:#cbd5e1}
@media(min-width:1000px){.rt-tl{grid-template-columns:repeat(5,1fr)}}
.rt-note{margin-top:20px;background:var(--accent-soft);border:1px solid #fed7aa;border-radius:14px;padding:20px 22px;color:#431407;font-size:15.5px}
.rt-n{display:inline-flex;width:30px;height:30px;border-radius:50%;background:var(--navy);color:#fff;font-weight:800;align-items:center;justify-content:center;margin-bottom:12px;font-size:14px}
.rt-cons{display:grid;gap:14px}@media(min-width:800px){.rt-cons{grid-template-columns:repeat(2,1fr)}}@media(min-width:1100px){.rt-cons{grid-template-columns:repeat(3,1fr)}}
.rt-cons>div{background:#fff;border:1px solid var(--line);border-left:4px solid var(--accent);border-radius:12px;padding:20px 22px}
.rt-cons b{font:800 17px "Manrope",sans-serif;display:block}.rt-cons p{font-size:14.5px;color:var(--ink-soft);margin-top:8px}
.rt-fim{margin-top:26px;font:700 18px "Manrope",sans-serif;max-width:60ch}
.rt-sim{display:grid;gap:22px}@media(min-width:1000px){.rt-sim{grid-template-columns:5fr 7fr}}
.rt-panel{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:22px}
.rt-panel h3{font-size:17px;margin-bottom:14px}
.rt-panel label{display:block;font-size:12.5px;font-weight:700;color:#cbd5e1;margin:14px 0 6px}.rt-panel label b{color:var(--accent)}
.rt-panel input[type=number]{width:100%;background:var(--navy);border:1px solid rgba(255,255,255,.2);border-radius:10px;padding:12px 14px;color:#fff;font:700 18px "Inter",sans-serif}
.rt-panel input[type=range]{width:100%;accent-color:#f97316}
.rt-panel fieldset{border:0;padding:0;margin:14px 0 0;display:grid;grid-template-columns:1fr 1fr;gap:10px}.rt-panel legend{font-size:12.5px;font-weight:700;color:#cbd5e1;margin-bottom:6px;padding:0}
.rt-panel .rt-radio{display:flex;align-items:center;gap:8px;margin:0;padding:12px;background:var(--navy);border:1px solid rgba(255,255,255,.2);border-radius:10px;cursor:pointer;color:#fff;font-size:14px}
.rt-panel .hint{font-size:11.5px;color:#94a3b8;margin-top:6px}
.rt-kp{display:grid;gap:14px}@media(min-width:700px){.rt-kp{grid-template-columns:repeat(3,1fr)}}
.rt-kp small{display:block;font-size:12px;color:#94a3b8;font-weight:600}.rt-kp b{display:block;font:800 26px "Manrope",sans-serif;margin:8px 0 4px;color:#fff;white-space:nowrap}.rt-kp span{font-size:11.5px;color:#94a3b8}
.rt-hi{border:2px solid var(--accent)}.rt-hi b{color:var(--accent)}
.rt-tb{width:100%;border-collapse:collapse;font-size:14px;color:#cbd5e1}.rt-tb th{text-align:left;padding:10px;font-size:12px;color:#94a3b8;border-bottom:1px solid rgba(255,255,255,.14)}
.rt-tb td{padding:12px 10px;border-bottom:1px solid rgba(255,255,255,.08)}.rt-tb td:last-child,.rt-tb th:last-child{text-align:right}.rt-tb td:first-child{color:#fff;font-weight:600}
.rt-exp{margin-top:16px;padding:14px;background:var(--navy);border-radius:12px;font-size:14px;color:#cbd5e1}.rt-exp span{color:var(--accent);font-weight:800}.rt-exp b{color:#fff}
.rt-gate{margin-top:16px;border:1px dashed rgba(249,115,22,.7)}.rt-gate-h{display:flex;gap:10px;align-items:center;color:var(--accent)}.rt-gate-h h3{margin:0;color:#fff}
.rt-gate ul{margin:14px 0 18px;padding-left:20px;display:grid;gap:6px;color:#cbd5e1;font-size:14px}@media(min-width:700px){.rt-gate ul{grid-template-columns:1fr 1fr;column-gap:24px}}
.rt-small{margin-top:24px;font-size:12px;color:#94a3b8;max-width:80ch}
.rt-lockl{display:flex;gap:12px;align-items:center;margin-bottom:18px;padding:14px 16px;border:1px dashed #c9c2b7;border-radius:12px;background:var(--surface);font-size:14px;color:var(--ink-soft)}.rt-lockl svg{flex:none;color:var(--accent-ink)}
.rt-faq{max-width:900px}
.rt-quiz{display:grid;gap:12px;max-width:820px}
.rt-qr{background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px 20px;display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between}
.rt-qr p{flex:1 1 320px;font-size:15.5px}.rt-qr p b{font-family:"Manrope",sans-serif;color:var(--accent-ink);margin-right:8px}
.rt-qb{display:flex;gap:8px}.rt-qb button{min-height:44px;min-width:96px;padding:0 16px;border-radius:10px;border:1.5px solid var(--line);background:#fff;font:700 14.5px "Inter",sans-serif;cursor:pointer;color:var(--ink)}
.rt-qb button.y{background:var(--navy);color:#fff;border-color:var(--navy)}.rt-qb button.n{background:var(--accent);color:#0f172a;border-color:var(--accent)}
.rt-prog{margin-top:14px;font-size:14px;color:var(--ink-soft)}
.rt-res{margin-top:22px;max-width:820px;background:var(--navy-2);color:#fff;border-radius:18px;padding:28px}.rt-res[hidden]{display:none}
.rt-k2{font-size:12px;font-weight:800;letter-spacing:.14em;color:#94a3b8}.rt-lvl{font:800 28px/1.15 "Manrope",sans-serif;margin:8px 0 10px}.rt-res #rt-msg{color:#cbd5e1;font-size:16px}.rt-small2{margin-top:12px;font-size:13px;color:#94a3b8}
.rt-src{margin-top:26px;font-size:12.5px;color:var(--ink-soft);line-height:1.8;max-width:100ch}
"""

JS = """
(function(){
  function $(i){return document.getElementById(i);}
  var WA='""" + t.WHATS + """';
  function brl(v){return v.toLocaleString('pt-BR',{style:'currency',currency:'BRL',maximumFractionDigits:0});}
  function pc(v){return v.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})+'%';}
  var d=Math.max(0,Math.ceil((new Date(2027,0,1).getTime()-Date.now())/864e5));
  ['rt-d','rt-d2'].forEach(function(i){if($(i))$(i).textContent=String(d);});

  function hoje(reg,r,c){return reg==='real'?0.0925*Math.max(0,r-c):0.0365*r;}
  function novo(a,r,c){return (a/100)*(r-c);}
  function dl(v){return (v>0?'+':v<0?'\\u2212':'')+brl(Math.abs(v));}
  function sim(){
    var r=Math.max(0,parseFloat($('s-rec').value)||0), c=Math.max(0,parseFloat($('s-com').value)||0);
    var reg=document.querySelector('input[name="s-reg"]:checked').value;
    var sem=parseFloat($('s-sem').value)||0, a=parseFloat($('s-ali').value)||9.21;
    $('s-sem-l').textContent=String(sem);
    $('s-ali-l').textContent=a.toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
    var h=hoje(reg,r,c), n=novo(a,r,c), df=n-h;
    $('o-hoje').textContent=brl(h);
    $('o-hoje-n').textContent=reg==='real'?'9,25% sobre receita menos compras':'3,65% sobre a receita, sem crédito';
    $('o-2027').textContent=brl(n);
    $('o-dif').textContent=dl(df);
    $('o-dif-n').textContent=df>0?'Aumento estimado da carga':df<0?'Redução estimada da carga':'Sem variação';
    $('o-exp').textContent=brl(r*sem/100);
    var linhas=[['Otimista',8.8],['Sua estimativa',a],['Conservador',10.9]];
    $('o-cen').innerHTML=linhas.map(function(x){var nn=novo(x[1],r,c),dd=nn-h;
      return '<tr><td>'+x[0]+'</td><td>'+pc(x[1])+'</td><td>'+brl(nn)+'</td><td>'+dl(dd)+(dd>0?' (aumenta)':dd<0?' (reduz)':'')+'</td></tr>';}).join('');
    var msg='Olá! Usei o simulador de reforma tributária da Oravel. Receita de locação: '+brl(r)+'/mês; compras com crédito: '+brl(c)+'/mês; regime: '+(reg==='real'?'Lucro Real':'Lucro Presumido')+'; alíquota estimada: '+pc(a)+'; diferença estimada: '+dl(df)+'/mês. Quero refinar esse cenário com os dados da minha locadora.';
    $('o-wa').href=WA+'?text='+encodeURIComponent(msg);
  }
  ['s-rec','s-com','s-sem','s-ali'].forEach(function(i){$(i).addEventListener('input',sim);});
  document.querySelectorAll('input[name="s-reg"]').forEach(function(e){e.addEventListener('change',sim);});
  sim();

  var QS=['Sei estimar quanto de crédito de CBS a minha locadora vai gerar em 2027.',
    'Sei qual parte do meu faturamento vem de clientes que aproveitam crédito.',
    'Meus contratos já preveem como o IBS e a CBS entram no preço.',
    'Consigo separar, na minha operação, locação pura de locação com operador ou serviço.',
    'Meu sistema emite nota com os campos de IBS e CBS sem digitação manual.',
    'Meu cadastro de equipamentos, clientes e itens está completo e padronizado.'];
  var ans={};
  function quiz(){
    $('rt-quiz').innerHTML=QS.map(function(q,i){var a=ans[i];
      return '<div class="rt-qr"><p><b>'+(i+1)+'</b>'+q+'</p><div class="rt-qb">'
        +'<button type="button" data-q="'+i+'" data-v="sim" aria-pressed="'+(a==='sim')+'" class="'+(a==='sim'?'y':'')+'">Sim</button>'
        +'<button type="button" data-q="'+i+'" data-v="nao" aria-pressed="'+(a==='nao')+'" class="'+(a==='nao'?'n':'')+'">Não sei / Não</button></div></div>';}).join('');
    var k=Object.keys(ans).length, gaps=Object.keys(ans).filter(function(x){return ans[x]==='nao';}).length, ok=k===QS.length;
    $('rt-prog').textContent=ok?'Avaliação concluída. Você pode mudar qualquer resposta.':k+' de '+QS.length+' respondidas';
    $('rt-res').hidden=!ok;
    if(ok){var l,m;
      if(gaps<=1){l='Bem posicionada, com pontos a confirmar';m='Você tem a maior parte das respostas. O que falta costuma ser o detalhe que pesa: confirmar que os números que você acredita ter batem com os documentos e com a sua base de clientes.';}
      else if(gaps<=3){l='Atenção: '+gaps+' pontos em aberto';m='Você já enxerga parte do cenário, mas ainda decide no escuro em itens que afetam preço, crédito e contratos. Com a virada em janeiro, vale fechar esses pontos antes do primeiro faturamento de 2027.';}
      else{l='Exposição alta: '+gaps+' pontos em aberto';m='A maior parte das respostas que decidem o resultado da sua locadora ainda não está na mesa. Não é motivo para alarme, mas é motivo para começar agora, enquanto ainda há tempo de ajustar contratos, cadastro e emissão.';}
      $('rt-lvl').textContent=l;$('rt-msg').textContent=m;
      $('rt-wa').href=WA+'?text='+encodeURIComponent('Olá! Fiz a autoavaliação da reforma tributária no site da Oravel e fiquei com '+gaps+' ponto(s) em aberto. Quero conversar sobre o cenário da minha locadora.');}
  }
  $('rt-quiz').addEventListener('click',function(e){var b=e.target.closest('button[data-q]');if(!b)return;
    var y=window.scrollY;ans[b.getAttribute('data-q')]=b.getAttribute('data-v');quiz();
    var nb=$('rt-quiz').querySelector('button[data-q="'+b.getAttribute('data-q')+'"][data-v="'+b.getAttribute('data-v')+'"]');if(nb)nb.focus({preventScroll:true});window.scrollTo(0,y);});
  quiz();
})();
"""

corpo = hero + certo + linha + impacto + consequencias + simulador + perguntas + oravel + avaliacao + fundamentos + cta + '<script>' + JS + '</script>'

TITULO = 'Reforma tributária para locadoras de equipamentos: o que muda em 2027 | Oravel'
DESC = ('A locação de equipamentos entra no IBS e na CBS em 1º de janeiro de 2027. Veja o que já é certo, o impacto, as consequências de não se adequar a tempo '
        'e simule a carga da sua locadora.')
pagina = t.pagina(TITULO, DESC, 'https://oravel.com.br/reforma-tributaria/', corpo, None).replace('</style>', CSS + '</style>', 1)
destino = RAIZ / 'reforma-tributaria'
destino.mkdir(parents=True, exist_ok=True)
(destino / 'index.html').write_text(pagina, encoding='utf-8')
print('ok', destino / 'index.html', len(pagina), 'bytes')
