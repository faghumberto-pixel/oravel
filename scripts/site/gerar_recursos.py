#!/usr/bin/env python3
"""Gera a página Recursos (abas + blocos com mockup). Uso: python3 gerar_recursos.py <public_html>"""
import sys
from pathlib import Path

import tema as t

RAIZ = Path(sys.argv[1])
B = t.bloco_feat
secoes = [
    ('ordens', 'Ordens de serviço', B('ordens', 'os', 'Ordens de serviço', 'Ordens de serviço que toda a equipe consegue acompanhar.',
        'Cada serviço fica registrado numa lista com status claro. O gestor enxerga o que está em andamento, o técnico vê o que vem a seguir, e cada hora e cada peça entram no histórico do equipamento.',
        [('Status claros', 'Da abertura à conclusão'), ('Custo somado', 'Peças, mão de obra e deslocamento por ordem'), ('Por equipamento', 'Cada ordem no histórico individual')],
        [('os', 'Painel de lista e detalhe', 'A lista e a ordem selecionada lado a lado, para priorizar sem perder o contexto.', ['Status e responsável sempre à vista', 'Visão em quadro do pátio (kanban)', 'Filtros por status, técnico e equipamento']),
         ('pm', 'Tempo, peças e mão de obra', 'O técnico registra o tempo do serviço e as peças usadas, e o custo total é calculado para você.', ['Cronômetro do serviço no celular', 'Peças registradas na própria ordem', 'Custo total somado automaticamente']),
         ('al', 'Pendências e retrabalho', 'Abra uma pendência a partir da ordem e acompanhe até a resolução.', ['Pendência com descrição e responsável', 'Resolução registrada com observação', 'Retrabalho aparece nos relatórios'])],
        t.mk_os())),
    ('preventiva', 'Manutenção preventiva', B('preventiva', 'pm', 'Manutenção preventiva', 'Manutenção preventiva que não depende de memória.',
        'Defina o plano uma vez, por horímetro, por tempo ou por ciclo, e a Oravel avisa quando cada equipamento precisa de atenção.',
        [('Horímetro, tempo ou ciclo', 'Formas de definir o intervalo'), ('Alertas', 'Aviso antes de a revisão vencer'), ('Por equipamento', 'Cada máquina com o seu plano')],
        [('pm', 'Planos por equipamento', 'Cada equipamento recebe o intervalo certo para o seu tipo de uso.', ['Intervalo por horímetro, dias ou ciclos', 'Cobertura dos equipamentos com plano', 'Alocação de técnicos às revisões']),
         ('al', 'Alertas de vencimento', 'Veja o que vence antes de virar parada.', ['Aviso antes de a revisão vencer', 'Painel das revisões a vencer', 'Histórico das revisões já feitas']),
         ('ck', 'Checklist anexo', 'Cada revisão chega com o roteiro correto para ser preenchido.', ['Checklist do serviço com fotos', 'Assinatura de quem executou e de quem recebeu', 'Horímetro registrado na execução'])],
        t.mk_pm(), rev=True)),
    ('inspecoes', 'Inspeções e checklists', B('inspecoes', 'ck', 'Inspeções e checklists', 'Inspeções e checklists que ficam comprovados.',
        'Fotos, assinatura e laudos ficam guardados junto do equipamento, para quando o cliente, o auditor ou a seguradora pedir.',
        [('Fotos', 'Antes e depois, direto do celular'), ('Assinatura digital', 'Do técnico e do cliente'), ('NR-13', 'Inspeções, laudos e alertas de vencimento')],
        [('ck', 'Checklist com fotos e assinatura', 'Entrega e devolução comprovadas, com o estado do equipamento registrado.', ['Itens por tipo de equipamento', 'Fotos de cada etapa', 'Assinatura digital do cliente']),
         ('al', 'Inspeções NR-13', 'Datas, laudos e responsável técnico organizados por equipamento.', ['Laudo anexado à inspeção', 'Alertas de vencimento', 'Painel de conformidade']),
         ('av', 'Avarias com dossiê', 'Cada dano registrado, revisado e documentado.', ['Fotos, gravidade e causa', 'Revisão do supervisor e do comercial', 'Assinatura do cliente'])],
        t.mk_checklist())),
    ('ativos', 'Ativos e equipamentos', B('ativos', 'eq', 'Ativos e equipamentos', 'Cada equipamento com a sua ficha completa.',
        'Cadastro, fotos, documentos e histórico em um só lugar, com etiqueta de QR code para abrir a ficha na hora.',
        [('3 fotos', 'Do equipamento, no cadastro'), ('QR code', 'Etiqueta para abrir a ficha'), ('Documentos', 'Arquivos privados, com link seguro')],
        [('hs', 'Histórico individual', 'Tudo o que já aconteceu com o equipamento, em uma linha do tempo.', ['Serviços, avarias e inspeções', 'Custo acumulado por equipamento', 'Horímetro e leituras']),
         ('es', 'Pátio e localização', 'Saiba onde cada equipamento está e a que contrato pertence.', ['Planta do pátio com os equipamentos', 'Local de instalação vindo do contrato', 'Categorias e grupos']),
         ('co', 'Documentos do equipamento', 'Manuais, laudos e certificados guardados com segurança.', ['Arquivos privados', 'Link com prazo de validade', 'Tipos de documento por equipamento'])],
        t.mk_ativo(), rev=True)),
    ('pecas', 'Peças e compras', B('pecas', 'es', 'Peças e compras', 'A peça certa na prateleira, quando você precisar.',
        'Acompanhe o estoque, veja o que cada equipamento consome e transforme faltas em pedidos de compra sem correria.',
        [('Mínimo por item', 'Alerta antes de faltar'), ('Reposição', 'Requisição que segue para compra'), ('Recebimento', 'Conferido contra o pedido')],
        [('es', 'Estoque e almoxarifados', 'Mais de um almoxarifado, inclusive volante, com transferência entre eles.', ['Saldo por almoxarifado', 'Almoxarifado em veículos e técnicos', 'Inventário e contagem']),
         ('co', 'Requisição e compras', 'Da falta ao pedido, em poucos passos.', ['Requisição de reposição', 'Cotação com fornecedores', 'Pedido de compra']),
         ('os', 'Peças na ordem de serviço', 'Cada peça usada fica ligada ao equipamento certo.', ['Peças registradas na OS', 'Custo por equipamento', 'Recebimento conferido'])],
        t.mk_pecas())),
    ('campo', 'App de campo', B('campo', 'os', 'App de campo', 'Um app para o celular que o técnico realmente usa.',
        'Instale pelo navegador, sem loja de aplicativos, e tenha as ordens do dia, o checklist e a assinatura na palma da mão.',
        [('Sem loja', 'Instala pelo navegador'), ('Câmera', 'Fotos direto do serviço'), ('Perfis', 'Cada um vê só o que pode')],
        [('os', 'Ordens do dia', 'O técnico vê o que precisa fazer hoje e registra o andamento.', ['Lista de ordens do dia', 'Mobilização e desmobilização', 'Cronômetro do serviço']),
         ('ck', 'Checklist e fotos', 'Roteiro do serviço preenchido na hora, com fotos.', ['Fotos com data', 'Itens de segurança', 'Observações do técnico']),
         ('hs', 'Assinatura digital', 'O cliente assina no próprio celular do técnico.', ['Assinatura na entrega e na devolução', 'Registro na ordem', 'Comprovante guardado'])],
        t.mk_celular(), rev=True)),
    ('relatorios', 'Relatórios e análises', B('relatorios', 'hs', 'Relatórios e análises', 'Números que você leva para a reunião.',
        'Os indicadores de manutenção que importam, calculados a partir do que a equipe já registra, sem planilha.',
        [('Disponibilidade', 'Da frota'), ('Preventivas', 'Em dia ou vencidas'), ('Retrabalho', 'Equipamentos que voltaram à oficina')],
        [('hs', 'Indicadores de manutenção', 'Disponibilidade, preventivas e desempenho em uma tela.', ['Desempenho por técnico', 'Preventivas em dia', 'Tempo de serviço']),
         ('co', 'Custos por equipamento', 'Quanto cada máquina consome em peças e mão de obra.', ['Custo por ordem de serviço', 'Custo acumulado', 'Comparação entre equipamentos']),
         ('av', 'Análise de falhas', 'Onde os problemas se repetem e por quê.', ['Retrabalho por equipamento', 'Causas mais comuns', 'Filtro por período'])],
        t.mk_relatorios())),
    ('equipe', 'Equipe e colaboração', B('equipe', 'eq', 'Equipe e colaboração', 'Todo mundo na mesma página.',
        'Veja a carga de cada técnico, distribua o trabalho e dê a cada pessoa só o acesso de que ela precisa.',
        [('Carga', 'Quem está cheio e quem está livre'), ('Agenda', 'Alocação por técnico'), ('Perfis', 'Permissões por função')],
        [('eq', 'Carga de trabalho', 'Compare os técnicos lado a lado e equilibre a equipe.', ['Sobrecarregado, equilibrado ou ocioso', 'Visão por semana', 'Redistribuição do trabalho']),
         ('os', 'Agenda e alocação', 'Cada técnico alocado às ordens certas, sem choque.', ['Agenda da equipe', 'Alocação de técnicos às revisões', 'Ordens do dia']),
         ('ck', 'Perfis de acesso', 'Gestor, técnico e comercial enxergam o que o perfil permite.', ['Perfis por função', 'Permissões por módulo', 'Acesso ao celular com o mesmo login'])],
        t.mk_equipe(), rev=True)),
    ('logistica', 'Logística', B('logistica', 'lo', 'Logística', 'Entregas e retiradas, do pedido à chegada ao pátio.',
        'Cada movimentação de equipamento fica registrada, com checklist na saída e conferência na chegada.',
        [('Pedidos', 'Com status até a conclusão'), ('Saída', 'Com checklist e fotos'), ('Chegada', 'Conferida antes de liberar')],
        [('lo', 'Solicitações de retirada e entrega', 'Todo pedido registrado e acompanhado.', ['Status do pedido', 'Agendamento', 'Responsável pela execução']),
         ('ck', 'Saída e chegada', 'O equipamento sai e volta com o estado comprovado.', ['Checklist de saída', 'Conferência na chegada ao pátio', 'Avaria bloqueia o equipamento']),
         ('co', 'Frete e custo', 'Acompanhe quanto cada transporte custa.', ['Transportadoras cadastradas', 'Registro de fretes', 'Custo por movimentação'])],
        t.mk_logistica())),
    ('modulos', 'Módulos opcionais', B('modulos', 'co', 'Módulos opcionais', 'Ligue o que a sua empresa precisa.',
        'Comece pelo que dói mais e acrescente módulos no seu ritmo, sem trocar o sistema que você já usa.',
        [('Locação', 'Contratos e medições'), ('Cobrança', 'Automática, da medição ao pagamento'), ('Pessoas', 'Ponto, faltas e EPI')],
        [('lo', 'Locação', 'Contratos, medições e disponibilidade da frota.', ['Contratos e medições', 'Franquia de horas e excedente', 'Solicitações de locação']),
         ('co', 'Cobrança automática', 'Da medição ao aviso e ao pagamento identificado.', ['Contas a receber', 'Boleto e link de pagamento', 'Avisos automáticos']),
         ('eq', 'Departamento pessoal', 'Colaboradores, ponto no celular e EPI com conformidade.', ['Faltas e atestados', 'Ponto eletrônico', 'Entrega de EPI'])],
        t.mk_painel(), rev=True)),
    ('seguranca', 'Segurança e administração', B('seguranca', 'ck', 'Segurança e administração', 'Feito para inspirar confiança nos seus registros.',
        'Seus dados ficam separados dos de outras empresas, os arquivos são privados e o sistema registra quem fez o quê.',
        [('Isolado', 'Dados separados por empresa'), ('Link seguro', 'Arquivos com prazo de validade'), ('Registro', 'De quem alterou o quê')],
        [('ck', 'Acesso seguro', 'Login individual e perfis de acesso por função.', ['Login com senha protegida', 'Perfis e permissões', 'Autenticação em duas etapas']),
         ('es', 'Arquivos privados', 'Fotos e documentos só abrem com link autorizado.', ['Link com validade', 'Armazenamento por empresa', 'Sem acesso público']),
         ('hs', 'Registro de alterações', 'Histórico do que foi alterado em equipamentos e avarias.', ['Quem alterou', 'O que mudou', 'Quando'])],
        t.mk_seguranca())),
]

abas = ''.join(f'<a href="#{i}">{n}</a>' for i, n, _ in secoes)
hero = ('<section class="hero"><div class="wrap" style="grid-template-columns:1fr"><div style="max-width:780px"><div class="eyebrow">Recursos</div>'
        '<h1>Tudo o que a manutenção da sua locadora precisa.</h1>'
        '<p class="sub">Da ordem de serviço ao relatório, passando pelo celular do técnico, pelo estoque e pela logística: veja o que a Oravel faz pela sua operação.</p>'
        f'<div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar demonstração</a><a class="btn btn-o" href="{t.WHATS_TESTE}" target="_blank" rel="noopener">Testar 30 dias</a></div></div></div></section>')
js = ("<script>(function(){var l=[].slice.call(document.querySelectorAll('.subnav a')),s=l.map(function(a){return document.querySelector(a.getAttribute('href'))});"
      "if(!('IntersectionObserver' in window))return;var o=new IntersectionObserver(function(e){e.forEach(function(x){if(x.isIntersecting){l.forEach(function(a){a.classList.toggle('on',a.getAttribute('href')==='#'+x.target.id)})}})},{rootMargin:'-40% 0px -55% 0px'});"
      "s.forEach(function(x){if(x)o.observe(x)})})();</script>")
corpo = hero + f'<nav class="subnav" aria-label="Recursos"><div class="wrap">{abas}</div></nav>' + ''.join(h for _, _, h in secoes) + t.cta_final('Veja esses recursos na sua operação') + js
out = RAIZ / 'recursos'
out.mkdir(parents=True, exist_ok=True)
ld = {'@context': 'https://schema.org', '@type': 'WebPage', 'name': 'Recursos da Oravel', 'url': 'https://oravel.com.br/recursos/'}
(out / 'index.html').write_text(t.pagina_rec('Recursos: manutenção, estoque, equipes e logística para locadoras | Oravel',
                                              'Ordens de serviço, manutenção preventiva, checklist com fotos e assinatura, estoque, app de campo, relatórios e logística para locadoras de equipamentos.',
                                              'https://oravel.com.br/recursos/', corpo, ld), encoding='utf-8')
print('ok')
