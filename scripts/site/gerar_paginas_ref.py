#!/usr/bin/env python3
"""
Gera Manutenção (CMMS), Logística, Soluções e Contato no padrão novo.
Contato é o contato.php do servidor: mantém o bloco PHP (envio, CAPTCHA, CRM) e troca só o HTML.
Uso: python3 gerar_paginas_ref.py <public_html> <contato_original.php>
"""
import html as H
import sys
from pathlib import Path

import tema as t

RAIZ = Path(sys.argv[1])
CONTATO_ORIG = Path(sys.argv[2])
EQ = t.EQUIPAMENTOS


def salvar(slug, titulo, desc, corpo, ld=None):
    d = RAIZ / slug.strip('/')
    d.mkdir(parents=True, exist_ok=True)
    (d / 'index.html').write_text(t.pagina_ref(titulo, desc, 'https://oravel.com.br' + slug, corpo, ld), encoding='utf-8')
    print('ok', slug)


# ------------------------------------------------------------------ Manutenção (CMMS)
FAQ_M = [
    ('O que é um sistema de gestão de manutenção (CMMS)?', 'É o sistema que organiza a manutenção da frota: planos de revisão, ordens de serviço, peças, checklists e o histórico de cada equipamento, em vez de planilhas e mensagens.'),
    ('A Oravel substitui o sistema que a minha locadora já usa?', 'Não. A Oravel complementa o seu sistema atual. Você mantém o que funciona e ganha o controle de manutenção.'),
    ('Como funciona a manutenção preventiva?', 'Você define o plano por horímetro, por tempo ou por ciclo, e a Oravel avisa quando cada equipamento precisa de atenção.'),
    ('O técnico precisa de computador?', 'Não. O técnico usa o celular para ver as ordens do dia, preencher o checklist com fotos e coletar a assinatura.'),
    ('Dá para saber quanto cada equipamento custa?', 'Sim. Peças, mão de obra e custo total ficam registrados por ordem de serviço e acumulados por equipamento.'),
    ('Posso bloquear um equipamento em manutenção para locação?', 'Sim. É possível bloquear o equipamento em manutenção para que ninguém o aluguem por engano, até a liberação.'),
    ('Como funciona a implantação?', 'Começa com uma conversa sobre a sua operação, passa pelo cadastro dos equipamentos e pela definição de planos e checklists e termina com o treinamento da equipe. Tudo incluso nos 30 dias de teste.'),
    ('Meus dados ficam seguros?', 'Os dados de cada empresa ficam separados dos das outras, os arquivos são privados e há cópias de segurança diárias.'),
]
corpo = t.hero_pg('Manutenção (CMMS)', 'Sistema de gestão de manutenção para locadoras de equipamentos.',
                  'Do plano de revisão à ordem de serviço, do checklist ao histórico de cada equipamento: a Oravel organiza a manutenção da sua frota para ela estar sempre pronta e comprovada.', t.mk_os())
corpo += t.secao('Como funciona', 'Como um reparo acontece na Oravel', 'Três passos, do aviso à liberação do equipamento.',
                 t.passos([('Abertura', 'A ordem nasce de um aviso de revisão, de uma avaria na devolução ou de um pedido do gestor, já ligada ao equipamento certo.'),
                           ('Execução', 'O técnico recebe a ordem no celular, registra o tempo, as peças e as fotos, e preenche o checklist do serviço.'),
                           ('Fechamento', 'Com a assinatura e o custo total calculados, o equipamento é liberado e volta a aparecer como disponível.')]))
corpo += t.secao('Recursos', 'O que a Oravel faz pela manutenção', 'Os recursos que sustentam a rotina da oficina.',
                 t.tabela(['Recurso', 'O que você ganha', 'No celular'], [
                     ('Manutenção preventiva', 'Planos por horímetro, tempo ou ciclo, com alertas de vencimento', '<span class="ok">✓</span>'),
                     ('Ordens de serviço', 'Painel com status, responsável e custo por equipamento', '<span class="ok">✓</span>'),
                     ('Checklist com fotos', 'Roteiro por tipo de equipamento, com fotos de cada etapa', '<span class="ok">✓</span>'),
                     ('Assinatura digital', 'Aceite do técnico e do cliente direto na ordem', '<span class="ok">✓</span>'),
                     ('Controle de avarias', 'Registro com fotos, gravidade, causa e revisão do supervisor', '<span class="ok">✓</span>'),
                     ('Histórico individual', 'Linha do tempo de serviços, avarias e inspeções', '<span class="ok">✓</span>'),
                     ('Inspeções NR-13', 'Datas, laudos, responsável técnico e alertas de vencimento', '—'),
                     ('Peças e estoque', 'Estoque mínimo, reposição e peças por ordem de serviço', '<span class="ok">✓</span>'),
                     ('Equipes', 'Carga e agenda dos técnicos', '<span class="ok">✓</span>'),
                     ('Indicadores', 'Disponibilidade, preventivas em dia e retrabalho', '—'),
                     ('Apontamento de horímetro', 'Leitura registrada por equipamento', '<span class="ok">✓</span>'),
                     ('Perfis de acesso', 'Cada função vê só o que precisa', '<span class="ok">✓</span>')]) + '<a class="more" href="/recursos/">Ver todos os recursos</a>', alt=True)
corpo += t.secao('Na nuvem', 'Sem servidor e sem instalação', 'Você acessa pelo navegador e pelo celular, e nós cuidamos do resto.',
                 t.tabela(['Item', 'Como é na Oravel'], [
                     ('Acesso', 'Pelo navegador, no computador e no celular'), ('Instalação', 'Nenhuma: o app do celular instala pelo navegador'),
                     ('Atualizações', 'Incluídas, sem parada para a sua equipe'), ('Cópias de segurança', 'Diárias, por empresa'), ('Dados', 'Separados dos de outras empresas, com arquivos privados')]))
corpo += t.secao('Escolha com segurança', 'Como escolher um sistema de manutenção', 'Perguntas que vale fazer a qualquer fornecedor, inclusive a nós.',
                 t.lista_num(['O técnico consegue usar no celular, sem treinamento longo?', 'O sistema registra o custo de cada equipamento sozinho, sem planilha paralela?',
                              'Dá para provar o estado do equipamento na saída e na volta?', 'Funciona ao lado do sistema que eu já tenho, sem exigir troca?']), alt=True)
corpo += t.secao('Implantação', 'Como é começar na Oravel', 'Em poucos dias, com acompanhamento.',
                 t.linha_tempo([('Conversa e levantamento', 'Entendemos a sua operação, os equipamentos e como a manutenção funciona hoje.'),
                                ('Cadastro dos equipamentos', 'Importamos a frota e organizamos por tipo, com o histórico que você já tem.'),
                                ('Planos e checklists', 'Montamos as revisões por horímetro ou tempo e os checklists de entrega e devolução.'),
                                ('Treinamento e início', 'A equipe aprende na prática, com o treinamento incluso, e começa a usar no dia a dia.')]))
corpo += t.secao('Para quem é', 'Para quem cuida de qualquer tipo de equipamento', None,
                 '<div class="grid4">' + ''.join(f'<a class="card" href="{u}"><h3>{n}</h3><p>Manutenção, histórico e inspeções em dia.</p></a>' for u, n in EQ[:4]) + '</div>'
                 '<div class="grid3" style="margin-top:18px">' + ''.join(f'<a class="card" href="{u}"><h3>{n}</h3><p>Manutenção, histórico e inspeções em dia.</p></a>' for u, n in EQ[4:]) + '</div>', alt=True)
corpo += t.secao('Perguntas frequentes', 'Perguntas frequentes sobre a manutenção na Oravel', None, t.faq(FAQ_M))
corpo += t.cta_final('Leve esse controle para a manutenção da sua locadora')
salvar('/software-manutencao-industrial/', 'Sistema de gestão de manutenção (CMMS) para locadoras | Oravel',
       'Manutenção preventiva por horímetro ou tempo, ordens de serviço por equipamento, checklist com fotos e assinatura digital e histórico individual. Conheça a Oravel.',
       corpo, t.faq_ld(FAQ_M, 'Manutenção (CMMS) | Oravel'))

# ------------------------------------------------------------------ Logística
FAQ_L = [
    ('A Oravel mostra onde o equipamento está?', 'A Oravel registra cada etapa da movimentação (pedido, saída, entrega e chegada ao pátio), para você saber em que fase cada equipamento está.'),
    ('A saída do equipamento fica comprovada?', 'Sim. O checklist com fotos e assinatura digital documenta o estado do equipamento na saída e na volta.'),
    ('Consigo acompanhar os pedidos de retirada e entrega?', 'Sim. Cada solicitação tem status, do pedido à conclusão, e responsável pela execução.'),
    ('O que acontece se o equipamento volta com avaria?', 'Ele é conferido na chegada ao pátio e, se houver avaria, fica bloqueado até a liberação.'),
    ('O custo do frete é registrado?', 'Sim. Transportadoras e fretes ficam cadastrados para você acompanhar o custo de cada movimentação.'),
    ('A logística funciona junto com o sistema que eu já uso?', 'Sim. A Oravel complementa o sistema que a sua locadora já usa.'),
]
corpo = t.hero_pg('Logística', 'Entregas e retiradas organizadas, do pedido à chegada ao pátio.',
                  'Cada equipamento sai e volta com o estado comprovado, e o comercial só vê como disponível o que está realmente pronto.', t.mk_programacao())
corpo += t.secao('Como funciona', 'Do pedido à chegada, em cinco passos', None,
                 t.passos([('Pedido registrado', 'A retirada ou a entrega entra no sistema com cliente, equipamento e prazo.'), ('Programação', 'O pedido é agendado e atribuído a quem vai executar.'),
                           ('Saída comprovada', 'Checklist com fotos e assinatura na saída do pátio.'), ('Entrega', 'O cliente recebe e assina no celular de quem entrega.'),
                           ('Chegada conferida', 'Na volta, o equipamento é conferido e só então é liberado.')]), alt=True)
blocos = [
    ('Programação de entregas e retiradas', 'Chega de pedido perdido em mensagem. Todas as solicitações ficam numa lista com status, prazo e responsável.',
     ['Status do pedido, da solicitação à conclusão', 'Agenda de saídas e chegadas', 'Responsável por cada execução']),
    ('Saída e chegada conferidas', 'O equipamento só segue viagem depois do checklist, e só volta a ser alugado depois da conferência.',
     ['Checklist de saída com fotos', 'Conferência na chegada ao pátio', 'Avaria encontrada bloqueia o equipamento']),
    ('Comprovante para o cliente', 'A assinatura na entrega e na devolução elimina a dúvida sobre o estado do equipamento.',
     ['Assinatura digital no celular', 'Registro na movimentação', 'Comprovante guardado no histórico']),
]
corpo += '<section class="sec"><div class="wrap">' + ''.join(
    f'<div class="split{" rev" if i % 2 else ""}" style="margin-bottom:56px"><div class="split-t"><h2>{a}</h2><p>{b}</p><ul class="ticks">' + ''.join(f'<li>{x}</li>' for x in c) + '</ul></div>'
    f'<div>{[t.mk_logistica(), t.mk_checklist(), t.mk_celular()][i]}</div></div>' for i, (a, b, c) in enumerate(blocos)) + '</div></section>'
corpo += t.secao('Recursos', 'Tudo o que a logística precisa', None, t.cartoes_lista([
    ('lo', 'Solicitações', ['Retirada e entrega', 'Status até a conclusão', 'Prazo e responsável']), ('os', 'Agenda', ['Saídas e chegadas do dia', 'Atribuição a quem executa', 'Visão por semana']),
    ('eq', 'Frota e veículos', ['Veículos cadastrados', 'Documentos e vencimentos', 'Disponibilidade da frota']), ('ck', 'Motoristas', ['Cadastro e documentos', 'Vínculo com veículos', 'Alertas de vencimento']),
    ('ck', 'Checklist de saída', ['Itens por tipo de equipamento', 'Fotos de cada etapa', 'Assinatura digital']), ('es', 'Chegada no pátio', ['Conferência com fotos', 'Quarentena para avarias', 'Liberação registrada']),
    ('av', 'Avarias', ['Registro com fotos', 'Gravidade e causa', 'Revisão do supervisor']), ('co', 'Frete', ['Transportadoras', 'Registro de fretes', 'Custo por movimentação']),
    ('es', 'Pátio', ['Planta do pátio', 'Localização dos equipamentos', 'Fila de aprovação']), ('hs', 'Histórico', ['Movimentações por equipamento', 'Quem executou', 'Quando e onde'])]), alt=True)
corpo += t.secao('Perguntas frequentes', 'Perguntas frequentes sobre logística', None, t.faq(FAQ_L))
corpo += t.cta_final('Leve esse controle para a logística da sua locadora')
salvar('/processo-logistica/', 'Logística de equipamentos de locação: entrega, retirada e pátio | Oravel',
       'Solicitações de retirada e entrega, checklist de saída com fotos e assinatura digital e conferência na chegada ao pátio. Conheça a Oravel.', corpo, t.faq_ld(FAQ_L, 'Logística | Oravel'))

# ------------------------------------------------------------------ Soluções
FAQ_S = [
    ('A Oravel atende o meu tipo de equipamento?', 'Sim. Atendemos guindastes, munks, plataformas elevatórias, empilhadeiras, geradores, compressores e outros equipamentos da frota.'),
    ('Preciso contratar tudo de uma vez?', 'Não. Você começa pelo que dói mais e acrescenta módulos no seu ritmo.'),
    ('A Oravel funciona junto com o meu sistema atual?', 'Sim. A Oravel complementa o sistema que a sua locadora já usa.'),
    ('Não encontrei a minha situação aqui. E agora?', 'Fale com a gente. Montamos a solução certa para a sua operação.'),
]
corpo = t.hero_pg('Soluções', 'A solução certa para a sua locadora, do seu jeito.',
                  'Escolha pelo tipo de equipamento, pela função de cada pessoa da equipe ou pelo tamanho da operação. O núcleo é o mesmo, e você começa pelo que mais precisa.')
corpo += t.secao('Por equipamento', 'Para cada tipo de equipamento', None, '<div class="grid4">' + ''.join(
    f'<a class="card" href="{u}"><h3>{n}</h3><p>Preventiva, ordens de serviço, histórico e inspeções para {n.lower()}.</p></a>' for u, n in EQ) + '</div>')
corpo += t.secao('Por função', 'Para cada pessoa da equipe', None, t.cartoes_lista([
    ('hs', 'Gestor de manutenção', ['Painel de ordens de serviço', 'Planos de preventiva e alertas', 'Carga da equipe']),
    ('es', 'Almoxarife', ['Estoque e alerta de mínimo', 'Reposição e recebimento', 'Almoxarifado volante']),
    ('co', 'Comercial', ['Equipamentos disponíveis', 'Avarias e liberação', 'Solicitações de locação']),
    ('os', 'Técnico em campo', ['Ordens do dia no celular', 'Checklist com fotos', 'Assinatura do cliente'])]), alt=True)
corpo += t.secao('Por operação', 'Para cada tamanho de operação', None, t.cartoes_lista([
    ('eq', 'Locadora que está crescendo', ['Sai da planilha com poucos equipamentos', 'Começa por um módulo', 'Cresce no seu ritmo']),
    ('pm', 'Locadora com oficina própria', ['Ordens de serviço e preventiva', 'Peças e custo por equipamento', 'Equipe e carga de trabalho']),
    ('lo', 'Locadora com frota variada', ['Vários tipos de equipamento', 'Logística e pátio', 'Cobrança automática'])]))
corpo += ('<section class="sec dark on-dark"><div class="wrap"><div class="sec-h"><div class="eyebrow">O mesmo núcleo para todos</div><h2 style="color:#fff">Os recursos que acompanham qualquer solução</h2></div>'
          '<div class="tags"><span>Ordens de serviço</span><span>Manutenção preventiva</span><span>Checklist com fotos</span><span>Assinatura digital</span><span>Histórico individual</span><span>App de campo</span><span>Alertas de vencimento</span><span>Perfis de acesso</span></div></div></section>')
corpo += t.secao('Perguntas', 'Perguntas frequentes', None, t.faq(FAQ_S))
corpo += t.cta_final('Vamos montar a solução certa para a sua locadora')
salvar('/solucoes/', 'Soluções para locadoras de equipamentos | Oravel',
       'Guindastes, munks, plataformas, empilhadeiras, geradores, compressores e mais: veja como a Oravel ajuda por equipamento, por função e por tipo de operação.', corpo, t.faq_ld(FAQ_S, 'Soluções | Oravel'))

# ------------------------------------------------------------------ Contato (contato.php)
orig = CONTATO_ORIG.read_text(encoding='utf-8')
bloco_php = orig[:orig.find('?>') + 2]
opcoes = ''.join(f'<{tag} class="card"{href}><h3>{ti}</h3><p>{tx}</p></{tag}>' for tag, href, ti, tx in [
    ('a', ' href="#form"', 'Agendar uma demonstração', 'Conte a sua operação e veja a Oravel com o cenário real da sua locadora.'),
    ('a', f' href="{t.WHATS}" target="_blank" rel="noopener"', 'Já sou cliente', f'Fale com o suporte pelo WhatsApp {t.FONE} ou por e-mail.'),
    ('a', f' href="mailto:{t.EMAIL}"', 'Parcerias', 'Integradores, consultorias e revendas: escreva para a gente.'),
    ('a', ' href="/recursos/"', 'Só quero conhecer', 'Veja os recursos da plataforma e, se fizer sentido, teste por 30 dias.')])
confianca = ''.join(f'<li>{x}</li>' for x in ['Uma equipe que entende de locação, manutenção e campo', 'Resposta em horário comercial', 'Implantação e treinamento inclusos nos 30 dias de teste', 'Os dados da sua empresa são seus'])
formulario = f"""<section class="sec alt" id="form"><div class="wrap split"><div class="split-t"><div class="eyebrow">Fale com a gente</div><h2>Conte um pouco da sua operação</h2>
<p>Retornamos em horário comercial, por e-mail ou WhatsApp.</p><ul class="ticks">{confianca}</ul></div>
<div><?php if ($status === 'success'): ?><div class="fmsg ok">✓ Mensagem enviada. Nosso time vai te responder por e-mail em breve.</div>
<?php elseif ($status === 'captcha_error'): ?><div class="fmsg err">Não conseguimos confirmar que você não é um robô. Tenta marcar a verificação de novo antes de enviar.</div>
<?php elseif ($status === 'error'): ?><div class="fmsg err">Não deu pra enviar agora. Tenta de novo ou escreve direto pra <a href="mailto:<?= $contact_mail ?>" style="text-decoration:underline"><?= $contact_mail ?></a>.</div>
<?php endif; ?>
<form class="form" method="post" action="/contato.php#form"><input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
<?php if ($origemKey !== ''): ?><input type="hidden" name="origem" value="<?= htmlspecialchars($origemKey) ?>"><?php endif; ?>
<div class="r2"><div><label for="name">Nome *</label><input type="text" id="name" name="name" required></div><div><label for="company">Empresa</label><input type="text" id="company" name="company"></div></div>
<div class="r2"><div><label for="email">E-mail *</label><input type="email" id="email" name="email" required></div><div><label for="phone">Telefone / WhatsApp</label><input type="tel" id="phone" name="phone"></div></div>
<div><label for="message">Como podemos ajudar? *</label><textarea id="message" name="message" required rows="5" style="width:100%;font:inherit;font-size:16px;padding:12px;border:1px solid #c3c9d3;border-radius:10px" placeholder="Ex: hoje controlamos tudo em planilha e queremos avaliar um sistema..."><?= htmlspecialchars($origemMsg) ?></textarea></div>
<div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>"></div>
<button type="submit" class="btn btn-p" style="width:100%">Enviar mensagem</button><p class="fine">Ao enviar, você concorda em ser contatado pelo time da Oravel, conforme a <a href="/politica-de-privacidade/" style="color:inherit;text-decoration:underline">Política de Privacidade</a>.</p></form></div></div></section>"""
info = (f'<section class="sec"><div class="wrap"><div class="sec-h"><div class="eyebrow">Onde estamos</div><h2>Outras formas de falar com a Oravel</h2></div><div class="grid3">'
        f'<a class="card" href="{t.WHATS}" target="_blank" rel="noopener"><h3>WhatsApp</h3><p>{t.FONE}</p></a><a class="card" href="mailto:{t.EMAIL}"><h3>E-mail</h3><p>{t.EMAIL}</p></a>'
        '<div class="card"><h3>Endereço</h3><p>Rua Pais Leme, 215, conj. 1713, Pinheiros, São Paulo/SP, CEP 05424-150<br>Oravel Desenvolvimento de Software Ltda · CNPJ 65.707.953/0001-30</p></div></div></div></section>')
hero = t.hero_pg('Contato', 'Fale com a Oravel.', 'Agende uma demonstração, tire dúvidas ou fale com o suporte. Respondemos em horário comercial.', ctas=False)
corpo = hero + t.secao('Como podemos ajudar', 'Escolha o seu caso', None, '<div class="opt">' + opcoes + '</div>') + formulario + info
pag = t.pagina_ref('Contato | Oravel', 'Fale com o time da Oravel: agende uma demonstração, tire dúvidas ou fale com o suporte.', 'https://oravel.com.br/contato.php', corpo, None,
                   '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>\n')
pag = pag.replace('<title>Contato | Oravel</title>', '<title><?= $page_title ?></title>', 1)
# o bloco PHP original (envio, CAPTCHA, CRM) fica intacto; só a apresentação muda
(RAIZ / 'contato.novo.php').write_text(bloco_php + '\n' + pag, encoding='utf-8')
print('ok contato.novo.php (segredos ficam só neste arquivo local ignorado pelo git)')
