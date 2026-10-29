#!/usr/bin/env python3
"""Gera a home nova (pré-via em public_html/novo/index.html). Uso: python3 gerar_home_nova.py <public_html>"""
import sys
from pathlib import Path

import tema as t

RAIZ = Path(sys.argv[1])

FAQ = [
    ('A Oravel substitui o sistema que a minha locadora já usa?', 'Não. A Oravel complementa o seu sistema atual. Você mantém o que funciona e ganha o controle de manutenção, estoque, logística e cobrança que faltava.'),
    ('Quanto tempo leva para começar?', 'Você começa em poucos dias. A implementação e o treinamento da equipe estão inclusos nos 30 dias de teste.'),
    ('Meus técnicos precisam de computador?', 'Não. O técnico usa o celular para ver as ordens do dia, preencher o checklist com fotos e coletar a assinatura.'),
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
         'description': 'Sistema de gestão de manutenção (CMMS) para locadoras de equipamentos: ordens de serviço, preventiva por horímetro, estoque, logística e cobrança.'},
        {'@type': 'FAQPage', 'mainEntity': [{'@type': 'Question', 'name': q, 'acceptedAnswer': {'@type': 'Answer', 'text': a}} for q, a in FAQ]},
    ],
}

hero = (
    '<section class="hero"><div class="wrap"><div><div class="eyebrow">Para locadoras de equipamentos</div>'
    '<h1>Seus equipamentos disponíveis, documentados e rentáveis.</h1>'
    '<p class="sub">A Oravel reúne manutenção, ordens de serviço, estoque, logística e cobrança em um só lugar, ao lado do sistema que a sua locadora já usa.</p>'
    '<div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a>'
    f'</div>'
    '<ul class="trust"><li>30 dias grátis com acompanhamento</li><li>Treinamento e implementação inclusos</li><li>Funciona ao lado do seu sistema</li></ul></div>'
    f'<div>{t.mk_painel()}<p class="nota" style="color:#94a3b8">Tela ilustrativa, com dados fictícios.</p></div></div></section>')

segmentos = ('<div class="strip"><div class="wrap"><span>Equipamentos atendidos</span>'
             + ''.join(f'<a class="chip-l" href="{u}">{n}</a>' for u, n in t.EQUIPAMENTOS) + '</div></div>')

manut = t.secao('Manutenção', 'Tudo o que a manutenção da locadora precisa, em um só lugar',
                'Do plano de revisão ao histórico de cada equipamento, a manutenção deixa de depender de memória, papel e mensagem.',
                '<div class="grid3">' + t.cards([
                    ('pm', 'Manutenção preventiva', 'Revisão por horímetro ou por tempo, para o equipamento chegar ao cliente sempre em dia.', '/processo-manutencao/'),
                    ('os', 'Ordem de serviço por equipamento', 'Cada ordem com dono, status, peças e custo, separada por equipamento.', '/processo-manutencao/'),
                    ('ck', 'Checklist com fotos e assinatura', 'Entrega e devolução comprovadas, no celular, com assinatura digital.', '/equipamento-devolvido-com-defeito/'),
                    ('av', 'Controle de avarias', 'Cada avaria registrada com fotos, data e responsável, e o equipamento bloqueado até a liberação.', '/equipamento-devolvido-com-defeito/'),
                    ('hs', 'Histórico individual', 'A vida inteira de cada equipamento em uma tela: serviços, avarias, custos e inspeções.', '/software-manutencao-industrial/'),
                    ('al', 'Alertas de vencimento', 'Aviso antes de a revisão ou a inspeção NR-13 vencer, para ninguém ser pego de surpresa.', '/nr-13-inspecao-equipamentos/'),
                ]) + '</div><a class="more" href="/software-manutencao-industrial/">Conhecer a manutenção na Oravel</a>')

ordens = (
    '<section class="sec alt"><div class="wrap split"><div class="split-t"><div class="eyebrow">Ordens de serviço</div>'
    '<h2>Todas as ordens num painel que dá para entender em segundos</h2>'
    '<p>Lista de um lado, detalhes do outro. Você acompanha o andamento, o responsável e o custo de cada serviço sem ligar para ninguém.</p>'
    '<ul class="ticks"><li>Status e responsável sempre à vista</li><li>Peças e mão de obra somadas no custo total</li><li>Checklist e assinatura anexados à ordem</li></ul>'
    '<a class="more" href="/processo-manutencao/">Ver como funciona</a></div>'
    f'<div>{t.mk_os()}<p class="nota">Tela ilustrativa, com dados fictícios.</p></div></div></section>')

campo = (
    '<section class="sec"><div class="wrap split rev"><div class="split-t"><div class="eyebrow">App de campo</div>'
    '<h2>O técnico resolve tudo pelo celular, no pátio ou na obra</h2>'
    '<p>A ordem do dia, o checklist com fotos e a assinatura do cliente ficam na mão de quem está executando o serviço.</p>'
    '<ul class="ticks"><li>Ordens do dia no celular</li><li>Fotos do equipamento na saída e na volta</li><li>Assinatura digital do cliente</li></ul>'
    '<a class="more" href="/processo-gestao-de-equipes/">Conhecer a gestão de equipes</a></div>'
    f'<div>{t.mk_celular()}<p class="nota" style="text-align:center">Tela ilustrativa.</p></div></div></section>')

logistica = t.secao('Logística', 'Do pátio ao cliente e de volta, sem perder o fio',
                    'Pedido, saída comprovada e chegada conferida: o caminho de cada equipamento fica registrado.',
                    t.vs(['Pedidos de retirada perdidos em mensagens', 'Equipamento sai sem checklist', 'Volta com avaria e sem prova', 'Comercial sem saber o que está disponível'],
                         ['Pedido registrado, com status até a conclusão', 'Saída com fotos e assinatura digital', 'Chegada conferida, avaria bloqueia o equipamento', 'Comercial vê só o que está pronto']
                         ) + '<a class="more" href="/processo-logistica/">Conhecer a logística</a>', alt=True)

modulos = t.secao('Módulos', 'Comece pelo que dói mais e cresça no seu ritmo',
                  'Cada módulo funciona sozinho e conversa com os outros quando você quiser.',
                  '<div class="grid4">' + t.cards([
                      ('es', 'Estoque', 'Almoxarifado, alerta de mínimo, reposição e recebimento.', '/processo-gestao-de-estoques/'),
                      ('eq', 'Equipes', 'Agenda, carga e desempenho dos técnicos.', '/processo-gestao-de-equipes/'),
                      ('co', 'Cobrança automática', 'Da medição ao aviso e ao pagamento identificado.', '/processo-cobranca-automatica/'),
                      ('lo', 'Locação', 'Contratos, medições e disponibilidade da frota.', '/erp-cmms/'),
                  ]) + '</div>')

historia = (
    '<section class="sec dark on-dark"><div class="wrap split"><div class="split-t"><div class="eyebrow">Por que a Oravel existe</div>'
    '<h2 style="color:#fff">Feita por quem já esteve do seu lado do balcão</h2>'
    '<p style="color:rgba(226,232,240,.85)">Administramos operações de equipamentos por anos. Conhecemos o silêncio de uma máquina parada, a peça que falta na hora errada e o cliente esperando. A Oravel nasceu para que a operação funcione com clareza, previsibilidade e tranquilidade.</p>'
    '<a class="more" style="color:#fdba74" href="/sobre/">Conhecer a nossa história</a></div>'
    '<div class="grid3" style="grid-template-columns:1fr">' + t.cards([
        ('pm', 'Clareza', 'Informação certa, na hora certa.'), ('hs', 'Previsibilidade', 'Revisão antes da quebra.'), ('ck', 'Tranquilidade', 'Tudo registrado e comprovado.')]) + '</div></div></section>')

comecar = t.secao('Como começar', 'Em poucos dias, sua operação rodando',
                  'Sem projeto longo e sem trocar o que já funciona.',
                  '<div class="steps"><div class="step"><h3>Cadastre os equipamentos</h3><p>Importe a frota e organize por tipo, com o histórico que você já tem.</p></div>'
                  '<div class="step"><h3>Defina planos e checklists</h3><p>Monte as revisões por horímetro ou tempo e os checklists de entrega e devolução.</p></div>'
                  '<div class="step"><h3>Convide a equipe</h3><p>Técnicos, almoxarife e comercial entram com o treinamento incluso.</p></div></div>', alt=True)

faq = t.secao('Perguntas frequentes', 'O que donos de locadora mais perguntam', None, t.faq(FAQ))

corpo = hero + segmentos + manut + ordens + campo + logistica + modulos + historia + comecar + faq + t.cta_final()
out = RAIZ
out.mkdir(parents=True, exist_ok=True)
(out / 'index.html').write_text(t.pagina('Oravel | Manutenção, estoque, logística e cobrança para locadoras',
                                          'Sistema de gestão de manutenção (CMMS) para locadoras de equipamentos: ordens de serviço, preventiva por horímetro, checklist com fotos e assinatura digital, estoque, logística e cobrança.',
                                          'https://oravel.com.br/', corpo, LD), encoding='utf-8')
print('ok', out / 'index.html')
