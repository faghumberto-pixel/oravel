"""
Páginas de processos (manutenção, gestão de equipes, gestão de estoques, logística, cobrança
automática): cenas com foto de fundo + recortes ilustrativos de interface (dados fictícios).
Só promete o que existe no sistema; o texto fala do resultado, não de como funciona por dentro.
"""
import equip_art as ea
import equip_media as em
from equip_art import N, O, S2, S4, S5, W, card, check, chip, circ, line, rect, signature, tick_row, txt, wrench

COR = {'ok': '#16a34a', 'warn': '#f59e0b', 'bad': '#dc2626', 'org': O, 'navy': N}


def cena(foto, corpo, legenda, bg='a'):
    return em.base(foto, corpo, legenda, bg)


def S_lista(foto, titulo, linhas, rodape, legenda, lado='dir', topo=None):
    x = 262 if lado == 'dir' else 18
    h = 56 + len(linhas) * 42
    g = card(x, 22, 200, h) + txt(x + 16, 44, titulo, 12, N, 800)
    for i, (a, lab, k) in enumerate(linhas):
        y = 56 + i * 42
        g += rect(x + 12, y, 176, 34, '#f8fafc', 9, '#e4e7ec', 1) + circ(x + 28, y + 17, 5.5, COR.get(k, S2))
        g += txt(x + 42, y + 14, a, 10.5, N, 700) + rect(x + 42, y + 21, 56, 4, S5, 2)
        g += chip(x + 188 - (18 + len(lab) * 6.1), y + 5, lab, k if k in ('ok', 'warn', 'bad', 'org') else 'org')[0]
    if topo:
        g += chip(24 if lado == 'dir' else 262, 24, topo, 'navy')[0]
    g += chip(24 if lado == 'dir' else 262, 254, rodape, 'org', True)[0]
    return cena(foto, g, legenda)


def S_barras(foto, titulo, linhas, rodape, legenda, lado='dir'):
    x = 262 if lado == 'dir' else 18
    h = 56 + len(linhas) * 44
    g = card(x, 22, 200, h) + txt(x + 16, 44, titulo, 12, N, 800)
    for i, (nome, pct, lab, k) in enumerate(linhas):
        y = 58 + i * 44
        g += txt(x + 16, y + 10, nome, 10.5, N, 700) + rect(x + 16, y + 17, 168, 7, S5, 3.5) + rect(x + 16, y + 17, 168 * pct, 7, COR[k], 3.5)
        g += txt(x + 184, y + 10, lab, 9.5, COR[k], 800, 'end')
    g += chip(24 if lado == 'dir' else 262, 254, rodape, 'org', True)[0]
    return cena(foto, g, legenda, 'b')


def S_passos(foto, titulo, passos, rodape, legenda):
    g = card(18, 30, 444, 100) + txt(34, 54, titulo, 12, N, 800)
    n = len(passos)
    w = 400 / n
    for i, (lab, feito) in enumerate(passos):
        cx = 38 + i * w + w / 2 - 12
        g += (check(cx + 12, 90, 11) if feito else circ(cx + 12, 90, 11, W, '#cbd5e1', 2))
        g += txt(cx + 12, 118, lab, 10, N, 700, 'middle')
        if i < n - 1:
            g += line(cx + 28, 90, cx + w - 4, 90, '#cbd5e1' if not feito else '#16a34a', 2)
    g += chip(24, 252, rodape, 'org', True)[0]
    return cena(foto, g, legenda, 'c')


def S_tiles(foto, tiles, rodape, legenda):
    g = ''
    for i, (lab, k) in enumerate(tiles):
        x = 20 + i * 148
        bg = {'ok': ea.OK_BG, 'warn': ea.WARN_BG, 'bad': ea.BAD_BG, 'org': '#fff1e4'}[k]
        g += rect(x, 24, 138, 84, bg, 14) + circ(x + 30, 54, 12, COR[k]) + rect(x + 54, 46, 62, 8, COR[k], 4, op=.35) + rect(x + 54, 62, 40, 6, COR[k], 3, op=.25)
        g += txt(x + 20, 96, lab, 13, COR[k], 800)
    g += chip(24, 252, rodape, 'org', True)[0]
    return cena(foto, g, legenda, 'e')


def S_checklist(foto, titulo, itens, rodape, legenda, assinado=True):
    g = card(18, 18, 252, 36 + len(itens) * 36 + 12) + txt(34, 40, titulo, 12, N, 800) + chip(188, 24, 'Assinado' if assinado else 'Aberto', 'ok' if assinado else 'warn')[0]
    for i, it in enumerate(itens):
        g += tick_row(30, 54 + i * 36, 228, it, True, i == 1)
    g += signature(286, 214, 176, 54) + chip(24, 252, rodape, 'org', True)[0]
    return cena(foto, g, legenda, 'e')


def S_msg(foto, titulo, mensagem, status, rodape, legenda):
    g = card(22, 24, 300, 128) + txt(38, 46, titulo, 12, N, 800)
    g += rect(38, 58, 268, 54, '#dcfce7', 12) + txt(50, 80, mensagem, 11, '#14532d', 700) + rect(50, 90, 150, 4, '#86efac', 2) + rect(50, 99, 100, 4, '#86efac', 2)
    g += chip(38, 120, status, 'ok', True)[0]
    g += chip(24, 252, rodape, 'org', True)[0]
    return cena(foto, g, legenda, 'e')


def S_os(foto, linhas, rodape, legenda):
    g = card(262, 22, 200, 190) + txt(278, 44, 'Ordem de serviço', 12, N, 800)
    for i, lab in enumerate(linhas):
        y = 58 + i * 44
        last = i == len(linhas) - 1
        g += rect(274, y, 176, 36, '#fff1e4' if last else '#f8fafc', 9, ea.OL if last else '#e4e7ec', 1.2) + txt(288, y + 22, lab, 11.5, N, 800 if last else 600)
        g += rect(380, y + 14, 56, 7, ea.OD if last else S4, 3, op=.55)
    g += wrench(26, 24, 1.5, O) + chip(24, 252, rodape, 'org', True)[0]
    return cena(foto, g, legenda, 'a')


def P(n, k):
    return f'proc-{n}-{k}.jpg'


# ------------------------------------------------------------------ cenas por processo
def cenas_equipes():
    return [
        S_barras(P('equipes', 1), 'Agenda da equipe', [('Técnico A', .8, 'Em OS', 'org'), ('Técnico B', .55, 'Em OS', 'org'), ('Técnico C', .3, 'Livre', 'ok')],
                 'Cada técnico no lugar certo', 'Recorte ilustrativo: agenda da equipe com a alocação de cada técnico'),
        S_barras(P('equipes', 2), 'Carga da equipe', [('Técnico A', .95, 'Sobrecarregado', 'bad'), ('Técnico B', .6, 'Equilibrado', 'ok'), ('Técnico C', .1, 'Ocioso', 'warn')],
                 'Quem está cheio e quem está livre', 'Recorte ilustrativo: carga de trabalho de cada técnico', 'esq'),
        S_lista(P('equipes', 3), 'Minhas ordens do dia', [('Revisão', 'Em curso', 'org'), ('Mobilização', 'Hoje', 'warn'), ('Desmobilização', 'Hoje', 'warn')],
                'Na mão do técnico, no celular', 'Recorte ilustrativo: lista de ordens do dia no celular do técnico', topo='App do técnico'),
        S_barras(P('equipes', 4), 'Desempenho', [('OS concluídas', .8, 'Boa', 'ok'), ('Retrabalho', .2, 'Baixo', 'ok'), ('Tempo de serviço', .6, 'No prazo', 'org')],
                 'Decisão com base em fato', 'Recorte ilustrativo: indicadores de desempenho da equipe', 'esq'),
    ]


def cenas_cobranca():
    return [
        S_lista(P('cobranca', 1), 'Contrato', [('Mensal fixo', 'Contrato', 'org'), ('Por hora', 'Contrato', 'org'), ('Diária', 'Contrato', 'org'), ('Franquia + excedente', 'Contrato', 'org')],
                'Cada contrato, a sua regra', 'Recorte ilustrativo: formas de cobrança do contrato de locação'),
        S_lista(P('cobranca', 2), 'Contas a receber', [('Fatura de locação', 'A vencer', 'warn'), ('Fatura de locação', 'Paga', 'ok'), ('Fatura de locação', 'Vencida', 'bad')],
                'A cobrança nasce da locação', 'Recorte ilustrativo: contas a receber com o status de cada cobrança', 'esq'),
        S_passos(P('cobranca', 3), 'Da medição ao pagamento', [('Medição', True), ('Cobrança', True), ('Aviso', True), ('Pagamento', False)],
                 'Sem planilha no meio do caminho', 'Recorte ilustrativo: etapas da cobrança, da medição ao pagamento'),
        S_msg(P('cobranca', 4), 'Aviso automático', 'Lembrete: sua fatura vence em breve.', 'Pagamento identificado',
              'O cliente é avisado, você não precisa cobrar', 'Recorte ilustrativo: aviso automático de cobrança e pagamento identificado'),
    ]


def cenas_logistica():
    return [
        S_lista(P('logistica', 1), 'Retiradas e entregas', [('Retirada no cliente', 'Solicitado', 'warn'), ('Entrega na obra', 'Agendado', 'org'), ('Retirada concluída', 'Concluído', 'ok')],
                'Todo pedido com status', 'Recorte ilustrativo: solicitações de retirada e entrega com o status de cada uma'),
        S_checklist(P('logistica', 2), 'Checklist de saída', ['Estado do equipamento', 'Acessórios e documentos', 'Fotos da saída'],
                    'Saída comprovada', 'Recorte ilustrativo: checklist de saída com fotos e assinatura digital'),
        S_passos(P('logistica', 3), 'Do pátio ao cliente e de volta', [('Pátio', True), ('Transporte', True), ('Cliente', True), ('Retorno', False)],
                 'Você sabe onde cada equipamento está', 'Recorte ilustrativo: etapas do transporte do equipamento'),
        S_lista(P('logistica', 4), 'Chegada no pátio', [('Conferência na volta', 'Em andamento', 'org'), ('Equipamento sem avaria', 'Liberado', 'ok'), ('Equipamento com avaria', 'Quarentena', 'bad')],
                'A volta conferida, sem surpresa', 'Recorte ilustrativo: conferência do equipamento na chegada ao pátio', 'esq'),
    ]


def cenas_estoque():
    return [
        S_barras(P('estoque', 1), 'Níveis do almoxarifado', [('Filtros', .7, 'Em dia', 'ok'), ('Correias', .25, 'Abaixo do mínimo', 'bad'), ('Óleo lubrificante', .5, 'Em dia', 'ok')],
                 'A falta aparece antes de acontecer', 'Recorte ilustrativo: níveis de estoque com alerta de mínimo'),
        S_passos(P('estoque', 2), 'Reposição sem correria', [('Requisição', True), ('Cotação', True), ('Pedido', True), ('Recebimento', False)],
                 'Da falta ao pedido', 'Recorte ilustrativo: etapas da reposição de estoque, da requisição ao recebimento'),
        S_lista(P('estoque', 3), 'Recebimento', [('Pedido de compra', 'Conferido', 'ok'), ('Pedido de compra', 'Em conferência', 'org'), ('Pedido de compra', 'Pendente', 'warn')],
                'Entrada conferida', 'Recorte ilustrativo: recebimento de mercadorias conferido contra o pedido', 'esq'),
        S_os(P('estoque', 4), ['Peças usadas', 'Mão de obra', 'Custo total'], 'Cada peça no equipamento certo', 'Recorte ilustrativo: peças usadas em uma ordem de serviço e o custo total'),
    ]


def cenas_manutencao():
    return [
        S_barras(P('manutencao', 1), 'Plano preventivo', [('Revisão por horímetro', .82, 'Em breve', 'warn'), ('Revisão por tempo', .4, 'Em dia', 'ok'), ('Inspeção geral', .15, 'Em dia', 'ok')],
                 'A revisão chega antes da quebra', 'Recorte ilustrativo: plano de manutenção preventiva por horímetro e por tempo'),
        S_lista(P('manutencao', 2), 'Ordens de serviço', [('Revisão da lança', 'Em curso', 'org'), ('Troca de filtros', 'Aberta', 'warn'), ('Inspeção geral', 'Concluída', 'ok')],
                'Todas as ordens num só painel', 'Recorte ilustrativo: painel de ordens de serviço com o status de cada uma', 'esq'),
        S_checklist(P('manutencao', 3), 'Checklist do serviço', ['Antes do serviço', 'Peças trocadas', 'Teste final'],
                    'Serviço comprovado, com foto', 'Recorte ilustrativo: checklist do serviço com fotos e assinatura digital'),
        S_os(P('manutencao', 4), ['Peças', 'Mão de obra', 'Custo total'], 'Quanto custou cada serviço', 'Recorte ilustrativo: custo da ordem de serviço com peças e mão de obra'),
    ]


def topo(nome, chips, rotulo):
    return em.topo(f'proc-{nome}-capa.jpg', chips, rotulo)


HERO = {
    'equipes': lambda: topo('equipes', [(24, 24, 'Equipe alocada', 'ok'), (24, 332, 'Carga sob controle', 'org')], 'Foto de uma equipe de manutenção com tablets em campo'),
    'cobranca': lambda: topo('cobranca', [(24, 24, 'Pagamento identificado', 'ok'), (24, 332, 'Aviso automático', 'org')], 'Foto de uma equipe em campo com celular, ao lado de equipamentos'),
    'logistica': lambda: topo('logistica', [(24, 24, 'Saída comprovada', 'ok'), (24, 332, 'Retorno conferido', 'org')], 'Foto de uma carreta de transporte de equipamentos'),
    'estoque': lambda: topo('estoque', [(24, 24, 'Estoque em dia', 'ok'), (24, 332, 'Reposição sem correria', 'org')], 'Foto de um almoxarifado com empilhadeira e prateleiras'),
    'manutencao': lambda: topo('manutencao', [(24, 24, 'Serviço comprovado', 'ok'), (24, 332, 'Custo por equipamento', 'org')], 'Foto de um mecânico conferindo o motor de um equipamento'),
}
