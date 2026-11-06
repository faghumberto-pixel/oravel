#!/usr/bin/env python3
"""Gera a home v2 (hero com dashboard, CRM, controle do dono, IA no campo, treinamento).
Uso: python3 gerar_home_v2.py <pasta de saída>   (grava <pasta>/index.html)"""
import sys
from pathlib import Path

import mockups_v2 as m
import tema as t

RAIZ = Path(sys.argv[1])

FAQ = [
    ('A Oravel substitui o sistema que a minha locadora já usa?', 'Não. A Oravel complementa o seu sistema atual. Você mantém o que funciona e ganha o controle de vendas, manutenção, estoque, frota e financeiro num painel só.'),
    ('A Oravel tem CRM para a equipe de vendas?', 'Sim. O funil comercial acompanha cada negociação do primeiro contato ao contrato, com valor em aberto, previsão do mês, aviso de retorno e origem dos leads.'),
    ('O que a inteligência artificial faz na prática?', 'Ela ajuda o técnico no campo com diagnóstico guiado e organiza as tarefas do dia, e ajuda o gestor a decidir: trocar ou vender um equipamento, repor estoque, retomar uma proposta parada. Os números são calculados pelo sistema; a IA interpreta e prioriza.'),
    ('Quem faz o treinamento e a implantação?', 'Consultores que conhecem a rotina de uma locadora. Eles fazem o diagnóstico da operação, treinam cada função (técnico, almoxarife, comercial e gestor) e acompanham a sua equipe nas primeiras semanas. Tudo isso está incluso nos 30 dias de teste.'),
    ('Quanto tempo leva para começar?', 'Você começa em poucos dias. A implementação e o treinamento da equipe estão inclusos nos 30 dias de teste.'),
    ('Meus técnicos precisam de computador?', 'Não. O técnico usa o celular para ver as ordens do dia, preencher o checklist com fotos, coletar a assinatura e receber ajuda da IA.'),
    ('Serve para qualquer tipo de equipamento?', 'Sim. Guindastes, munks, plataformas elevatórias, empilhadeiras, geradores, compressores e outros equipamentos da frota.'),
    ('A Oravel emite nota fiscal?', 'Não. A Oravel cuida da medição e da cobrança e trabalha ao lado do sistema fiscal que a sua locadora já usa.'),
    ('Como funciona o teste de 30 dias?', 'Você usa a Oravel por 30 dias, com acompanhamento, treinamento e implementação inclusos. Depois decide se continua.'),
]

LD = {
    '@context': 'https://schema.org',
    '@graph': [
        {'@type': 'Organization', '@id': 'https://oravel.com.br/#organization', 'name': 'Oravel', 'url': 'https://oravel.com.br/',
         'legalName': 'Oravel Desenvolvimento de Software LTDA', 'logo': 'https://oravel.com.br/img/logo-or.png',
         'contactPoint': {'@type': 'ContactPoint', 'telephone': '+55-19-99933-2615', 'email': t.EMAIL, 'contactType': 'sales', 'availableLanguage': 'pt-BR'}},
        {'@type': 'SoftwareApplication', 'name': 'Oravel', 'applicationCategory': 'BusinessApplication', 'operatingSystem': 'Web',
         'description': 'Sistema de gestão para locadoras de equipamentos: CRM e vendas, manutenção, estoque, frota, logística e financeiro, com painel do dono, IA no campo e implantação acompanhada.'},
        {'@type': 'FAQPage', 'mainEntity': [{'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': a}} for q, a in FAQ]},
    ],
}

NOTA = '<p class="v2-nota">Tela ilustrativa, com dados fictícios.</p>'


def ck(itens, escuro=False):
    return f'<ul class="v2-ck{" dk" if escuro else ""}">' + ''.join(f'<li><span>{i}</span></li>' for i in itens) + '</ul>'


# ---------------------------------------------------------------- hero: o painel do dono
hero = (
    '<section class="hero v2h"><div class="wrap"><div class="v2h-top"><div><div class="eyebrow">Para locadoras de equipamentos</div>'
    '<h1>Tenha a locadora inteira sob controle, da venda à manutenção.</h1>'
    '<p class="sub">Vendas, manutenção, estoque, frota e financeiro num painel que mostra resultado de verdade. Com IA que ajuda o time no campo e consultores que já viveram a rotina de uma locadora.</p></div>'
    '<div class="v2h-act"><div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a><a class="btn btn-o" href="#controle">Ver o painel do dono</a></div>'
    '<ul class="trust"><li>30 dias grátis com acompanhamento</li><li>Treinamento e implementação inclusos</li><li>Funciona ao lado do seu sistema</li></ul>'
    '<p class="v2-nota v2h-n1">Tela ilustrativa, com dados fictícios.</p></div></div>'
    f'<div class="v2h-dash">{m.hero_dashboard()}<p class="v2-nota v2h-n2">Tela ilustrativa, com dados fictícios.</p></div></div></section>')

segmentos = ('<div class="strip"><div class="wrap"><span>Equipamentos atendidos</span>'
             + ''.join(f'<a class="chip-l" href="{u}">{n}</a>' for u, n in t.EQUIPAMENTOS) + '</div></div>')

# ---------------------------------------------------------------- a dor do dono
dor = t.secao(
    'A dor de quem é dono de locadora', 'Você consegue responder estas quatro perguntas agora, sem ligar para ninguém?',
    'Quando as respostas estão espalhadas em planilhas, mensagens e na cabeça de cada pessoa, o dono vira o centro de tudo e ainda assim não controla nada.',
    '<div class="v2-dor">'
    '<div class="p"><q>Onde está cada equipamento agora?</q><p class="a"><b>Mapa da frota</b> com obra, pátio e rastreador. Você vê na hora.</p></div>'
    '<div class="p"><q>Quanto cada equipamento me custa de manutenção?</q><p class="a"><b>Custo por equipamento</b> sobre o valor dele, com aviso de quando vale trocar ou vender.</p></div>'
    '<div class="p"><q>Quanto vou fechar de vendas este mês?</q><p class="a"><b>Funil com previsão do mês</b>, valor em aberto e quem está sem retorno.</p></div>'
    '<div class="p"><q>O que está atrasado e me custando dinheiro?</q><p class="a"><b>Equipamento parado, revisão vencendo, conta em atraso</b> e proposta esquecida, num lugar só.</p></div></div>'
    '<div class="v2-prova"><div><b>Números calculados pelo sistema</b><span>Nada de achismo: cada indicador vem do que foi registrado na operação.</span></div>'
    '<div><b>A IA interpreta e prioriza</b><span>Ela aponta o que merece atenção e sugere o próximo passo.</span></div>'
    '<div><b>Dados isolados por empresa</b><span>As informações da sua locadora são só da sua locadora.</span></div></div>')

# ---------------------------------------------------------------- vendas e CRM
vendas = (
    '<section class="sec alt" id="vendas"><div class="wrap v2-split rev"><div class="t"><div class="eyebrow">Vendas e CRM</div>'
    '<h2>Venda mais e pare de perder negócio por falta de retorno</h2>'
    '<p>A gestão comercial é uma das maiores dores das locadoras. Na Oravel o funil vive no mesmo sistema da frota: cada negociação com valor, dono e próximo passo, do primeiro contato ao contrato.</p>'
    + ck(['<b>Funil em colunas</b> com o valor de cada negociação e a previsão de fechamento do mês',
          '<b>Retorno no prazo:</b> aviso de follow-up e de proposta sem resposta',
          '<b>Origem dos leads e conversão por etapa</b>, para saber o que realmente traz resultado',
          '<b>Orçamento e proposta</b> no mesmo lugar, até virar contrato',
          '<b>App Oravel Vendas</b> no celular do vendedor'])
    + '<a class="more" href="/crm/">Conhecer o CRM da Oravel</a></div>'
    f'<div>{m.mk_crm()}{NOTA}</div></div></section>')

# ---------------------------------------------------------------- controle do dono
controle = (
    '<section class="sec" id="controle"><div class="wrap v2-split"><div class="t"><div class="eyebrow">Controle total</div>'
    '<h2>Do mapa ao custo: você enxerga a operação sem perguntar para ninguém</h2>'
    '<p>Onde está cada equipamento, quanto ele custa de manutenção e o que aconteceu hoje, num painel que se lê em segundos.</p>'
    + ck(['<b>Mapa</b> com a posição de cada equipamento: obra, pátio ou rastreador',
          '<b>Custo de manutenção sobre o valor</b>, com aviso para revisar, trocar ou vender',
          '<b>Linha do tempo do dia:</b> saídas, visitas e equipamentos parados',
          '<b>Receita, taxa de locação e contas em atraso</b> no mesmo painel',
          '<b>Um painel por área:</b> Comercial, Manutenção, Estoque, Frota e Financeiro'])
    + '<a class="more" href="/recursos/">Conhecer os painéis</a></div>'
    f'<div>{m.mk_dono()}{NOTA}</div></div></section>')

# ---------------------------------------------------------------- IA no campo
ia = (
    '<section class="sec alt" id="ia"><div class="wrap v2-split rev"><div class="t"><div class="eyebrow">Inteligência artificial</div>'
    '<h2>Uma IA que ajuda quem está no campo e quem decide</h2>'
    '<p>A IA não inventa números: o sistema calcula e ela interpreta, prioriza e sugere o próximo passo, no celular do técnico e no painel do gestor.</p>'
    + ck(['<b>Diagnóstico guiado</b> de avarias e falhas, com o histórico do equipamento e as peças em estoque',
          '<b>Tarefas do dia organizadas</b> por urgência e por rota',
          '<b>Decisões com dados:</b> trocar ou vender, repor estoque, revisar antes de quebrar',
          '<b>Alerta de venda parada:</b> leads em risco de perda e propostas sem retorno'])
    + '<a class="more" href="/inteligencia-artificial/">Ver a IA da Oravel</a></div>'
    f'<div>{m.mk_ia_celular()}<p class="v2-nota" style="text-align:center">Tela ilustrativa, com dados fictícios.</p></div></div></section>')

# ---------------------------------------------------------------- operação (cards de manutenção)
operacao = t.secao(
    'Manutenção e operação', 'O dia a dia do pátio e da oficina, sem depender de papel e de memória',
    'Do plano de revisão ao histórico de cada equipamento, a operação deixa de depender de mensagem e de quem lembrou.',
    '<div class="grid3">' + t.cards([
        ('pm', 'Manutenção preventiva', 'Revisão por horímetro ou por tempo, para o equipamento chegar ao cliente sempre em dia.', '/processo-manutencao/'),
        ('os', 'Ordem de serviço por equipamento', 'Cada ordem com dono, status, peças e custo, separada por equipamento.', '/processo-manutencao/'),
        ('ck', 'Checklist com fotos e assinatura', 'Entrega e devolução comprovadas, no celular, com assinatura digital.', '/equipamento-devolvido-com-defeito/'),
        ('es', 'Estoque que se conta pelo celular', 'Almoxarifado com corredores e prateleiras, contagem sem papel e rastreio de cada item.', '/processo-gestao-de-estoques/'),
        ('lo', 'Frota e logística', 'Quem está com cada veículo, onde está, quanto rodou e quanto custa.', '/processo-logistica/'),
        ('al', 'Alertas de vencimento', 'Aviso antes de a revisão ou a inspeção NR-13 vencer, para ninguém ser pego de surpresa.', '/nr-13-inspecao-equipamentos/'),
    ]) + '</div><a class="more" href="/recursos/">Ver todos os recursos</a>')

# ---------------------------------------------------------------- treinamento e acompanhamento
treino = (
    '<section class="sec dark on-dark" id="implantacao"><div class="wrap v2-split dk"><div class="t"><div class="eyebrow">Treinamento e acompanhamento</div>'
    '<h2>Quem já viveu uma locadora implanta junto com você</h2>'
    '<p>A Oravel nasceu da operação: administramos operações de equipamentos por anos. A implantação não é um manual, é o acompanhamento de gente que conhece o silêncio de uma máquina parada e a peça que falta na hora errada.</p>'
    + ck(['<b>Diagnóstico da sua operação</b> antes de configurar qualquer coisa',
          '<b>Treinamento por função:</b> técnico, almoxarife, comercial e gestor',
          '<b>Academia Oravel</b> dentro do sistema, com trilhas e prova de conhecimento',
          '<b>Acompanhamento semanal</b> e revisão dos resultados no painel do dono',
          '<b>Tudo incluso</b> nos 30 dias de teste'], True)
    + '<div class="v2-nums"><div><b>30 dias</b><span>de teste com acompanhamento</span></div><div><b>4 funções</b><span>treinadas de ponta a ponta</span></div></div>'
    '<a class="more" style="color:#fdba74" href="/sobre/">Conhecer a nossa história</a></div>'
    f'<div>{m.mk_implantacao()}<p class="v2-nota">Tela ilustrativa, com dados fictícios.</p></div></div></section>')

modulos = t.secao('Módulos', 'Comece pelo que dói mais e cresça no seu ritmo',
                  'Cada módulo funciona sozinho e conversa com os outros quando você quiser.',
                  '<div class="grid4">' + t.cards([
                      ('hs', 'CRM e vendas', 'Funil, propostas, follow-up e previsão de fechamento.', '/crm/'),
                      ('pm', 'Manutenção', 'Preventiva, ordens de serviço, checklist e avarias.', '/processo-manutencao/'),
                      ('es', 'Estoque', 'Almoxarifado, contagem pelo celular e reposição.', '/processo-gestao-de-estoques/'),
                      ('lo', 'Frota e logística', 'Veículos, motoristas, fretes e movimentações.', '/processo-logistica/'),
                      ('eq', 'Equipes', 'Agenda, carga e desempenho dos técnicos.', '/processo-gestao-de-equipes/'),
                      ('co', 'Cobrança automática', 'Da medição ao aviso e ao pagamento identificado.', '/processo-cobranca-automatica/'),
                      ('os', 'Locação', 'Contratos, medições e disponibilidade da frota.', '/erp-cmms/'),
                      ('av', 'Inteligência artificial', 'Apoio à decisão no campo e na gestão.', '/inteligencia-artificial/'),
                  ]) + '</div>', alt=True)

comecar = t.secao('Como começar', 'Em poucos dias, sua operação rodando',
                  'Sem projeto longo e sem trocar o que já funciona.',
                  '<div class="steps"><div class="step"><h3>Diagnóstico</h3><p>Um consultor entende a sua operação e define por onde começar.</p></div>'
                  '<div class="step"><h3>Cadastro e primeiras ordens</h3><p>Importe a frota, monte os planos de revisão e os checklists.</p></div>'
                  '<div class="step"><h3>Treinamento por função</h3><p>Técnicos, almoxarife, comercial e gestor entram cada um com a sua rotina.</p></div>'
                  '<div class="step"><h3>Resultados no painel</h3><p>Revisão com números reais e o plano dos próximos 90 dias.</p></div></div>')

faq = t.secao('Perguntas frequentes', 'O que donos de locadora mais perguntam', None, t.faq(FAQ), alt=True)

EXTRA = m.CSS_V2 + """
.v2h-top{display:grid;gap:26px;grid-template-columns:minmax(0,1fr)}.v2h-act .hero-act{margin-top:0}.v2h-act .trust{margin-top:18px}
.hero.v2h .wrap{grid-template-columns:minmax(0,1fr);gap:36px;align-items:start}.v2h-top>*,.v2h-dash{min-width:0}
.hero.v2h h1{font-size:clamp(28px,4vw,46px);line-height:1.08;max-width:18ch}
.hero.v2h .sub{max-width:52ch}
.v2h-n1{display:none}
/* computador: o painel fica ao lado do texto, maior que a coluna, e a janela do hero corta o que passa (nao aparece inteiro) */
@media(min-width:1000px){
.hero.v2h{padding:68px 0 0;overflow:hidden}
.hero.v2h .wrap{grid-template-columns:minmax(0,.82fr) minmax(0,1.18fr);gap:40px;align-items:start;min-height:620px}
.v2h-top{align-self:center;padding-bottom:70px}
.v2h-dash{position:relative;align-self:stretch;height:auto}
.v2h-dash .v2-app{position:absolute;left:0;top:0;width:clamp(780px,63vw,1010px);max-width:none;margin:0}
.v2h-dash::after{content:"";position:absolute;left:-20px;right:-100vw;bottom:0;height:150px;background:linear-gradient(to bottom,rgba(8,20,44,0),var(--navy-2) 90%);pointer-events:none;z-index:2}
.v2h-n1{display:block;margin-top:16px}.v2h-n2{display:none}
}
.hero.v2h .v2-nota{margin-top:12px}
.more{display:inline-block;margin-top:22px}
"""

corpo = hero + segmentos + dor + vendas + controle + ia + operacao + treino + modulos + comecar + faq + t.cta_final()
RAIZ.mkdir(parents=True, exist_ok=True)
pagina = t.pagina('Oravel | Vendas, manutenção, estoque e frota para locadoras, num painel só',
                  'Sistema de gestão para locadoras de equipamentos: CRM e vendas, manutenção, estoque, frota e financeiro com painel do dono, IA no campo e implantação acompanhada por quem já viveu locadora.',
                  'https://oravel.com.br/', corpo, LD)
(RAIZ / 'index.html').write_text(pagina.replace('</style>', EXTRA + '</style>', 1), encoding='utf-8')
print('ok', RAIZ / 'index.html')
