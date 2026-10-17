"""Fonte única do site: os 6 pacotes (Soluções), o que cada um inclui e as telas (mockups) de cada um."""

BASE_COMUM = 'Usuários e perfis de acesso, painel inicial, configurações da empresa e app do colaborador no celular'

PACOTES = [
    {
        'codigo': 'locadora', 'nome': 'ERP Locadoras', 'pagina': '/erp-cmms/', 'venda_online': False,
        'frase': 'O ERP completo para locadoras de equipamentos.',
        'descricao': 'Contratos, medições, manutenção, avarias, estoque, logística, financeiro e vendas em uma base só.',
        'inclui': [
            ('Ativos e locação', 'Ativos e frota, contratos, medições, franquias de horas, solicitações de locação, avarias com dossiê'),
            ('Manutenção', 'Ordens de serviço, preventiva (PMP), checklists, kanban do pátio, horímetro'),
            ('Estoque e logística', 'Materiais e peças, almoxarifado, logística, frota e rastreamento'),
            ('Financeiro e comercial', 'Contas a receber e a pagar, cobrança, propostas, orçamentos, CRM de vendas'),
            ('Pessoas', 'Colaboradores, departamento pessoal, EPI'),
        ],
        'mockups': [
            {'tipo': 'tabela', 'url': 'contratos', 'titulo': 'Contratos de locação', 'kpis': [['Ativos', '38'], ['A vencer', '5'], ['Medições', '12']],
             'cabecalho': ['Contrato', 'Cliente', 'Equipamento', 'Situação'],
             'linhas': [['CT-0412', 'Construtora Alfa', 'Gerador 150kVA', ['Ativo', 'ok']], ['CT-0415', 'Obras Beta', 'Plataforma 12m', ['Ativo', 'ok']], ['CT-0398', 'Gama Eventos', 'Empilhadeira 3t', ['A vencer', 'warn']], ['CT-0377', 'Delta Log', 'Compressor 20pcm', ['Ativo', 'ok']]],
             'legenda': 'Contratos e medições'},
            {'tipo': 'painel', 'url': 'painel', 'titulo': 'Painel da locadora', 'kpis': [['Disponíveis', '62', 'ok'], ['Locados', '148', 'info'], ['Em manutenção', '9', 'warn']],
             'barras': [['Mai', 78], ['Jun', 84], ['Jul', 91], ['Ago', 88], ['Set', 97], ['Out', 104]], 'legenda': 'Faturamento mensal (R$ mil)', 'legenda_titulo': 'Visão geral'},
            {'tipo': 'ficha', 'url': 'avarias', 'titulo': 'Avaria · Dossiê do equipamento', 'etiqueta': ['Em análise', 'warn'],
             'campos': [['Equipamento', 'Plataforma JLG 450AJ'], ['Cliente', 'Obras Beta'], ['Contrato', 'CT-0415'], ['Tipo', 'Dano em lança'], ['Fotos', '6 anexadas'], ['Responsável', 'Cliente']],
             'linha_tempo': [['Hoje 09:12', 'Avaria registrada com 6 fotos'], ['Ontem', 'Devolução com checklist'], ['Há 12 dias', 'Entrega assinada pelo cliente']], 'legenda': 'Avarias e dossiê'},
            {'tipo': 'tabela', 'url': 'contas-a-receber', 'titulo': 'Contas a receber', 'kpis': [['A receber', 'R$ 184 mil'], ['Vencidas', 'R$ 12 mil'], ['Recebido mês', 'R$ 96 mil']],
             'cabecalho': ['Cliente', 'Vencimento', 'Valor', 'Status'],
             'linhas': [['Construtora Alfa', '10/11', 'R$ 18.400', ['Aberta', 'info']], ['Obras Beta', '05/11', 'R$ 9.800', ['Vencida', 'bad']], ['Gama Eventos', '12/11', 'R$ 4.200', ['Aberta', 'info']], ['Delta Log', '01/11', 'R$ 11.900', ['Paga', 'ok']]],
             'legenda': 'Financeiro'},
        ],
    },
    {
        'codigo': 'manutencao', 'nome': 'Manutenção', 'pagina': '/software-manutencao-industrial/',
        'frase': 'Controle de equipamentos, ordens de serviço e preventiva.',
        'descricao': 'Ativos, ordens de serviço, manutenção preventiva com checklist e o histórico de cada equipamento.',
        'inclui': [
            ('Ativos', 'Cadastro, categorias, grupos de checklist, mapa de equipamentos, planta do pátio, especificações técnicas'),
            ('Ordens de serviço', 'OS, kanban do pátio, checklists, reservas urgentes, histórico de paradas'),
            ('Preventiva (PMP)', 'Planos por horas, dias ou ciclos, cobertura, alocação de técnicos, consulta por cliente'),
            ('Indicadores', 'Horímetro, ciclo de bateria, criticidade, matriz ABC, análise por IA'),
            ('Itens agregados e clientes', 'Insumos, acessórios, mão de obra e cadastro de clientes'),
        ],
        'mockups': [
            {'tipo': 'kanban', 'url': 'kanban-patio', 'titulo': 'Kanban do pátio', 'colunas': [
                ['Aberta', [['OS-2041 Gerador', 'Falha na partida', 'bad'], ['OS-2044 Empilhadeira', 'Revisão 500h', 'warn']]],
                ['Em execução', [['OS-2038 Plataforma', 'Troca de cilindro', 'info'], ['OS-2036 Compressor', 'Filtro e óleo', 'info']]],
                ['Aguardando peça', [['OS-2029 Munck', 'Mangueira hidráulica', 'warn']]],
                ['Concluída', [['OS-2025 Gerador', 'Preventiva 250h', 'ok'], ['OS-2022 Torre de luz', 'Bateria', 'ok']]]], 'legenda': 'Kanban do pátio'},
            {'tipo': 'tabela', 'url': 'planos-preventivos', 'titulo': 'Planos preventivos (PMP)', 'kpis': [['Vencidos', '4'], ['A vencer', '11'], ['No prazo', '86']],
             'cabecalho': ['Equipamento', 'Plano', 'Intervalo', 'Situação'],
             'linhas': [['Gerador 150kVA', 'Troca de óleo', '250 h', ['Vencido', 'bad']], ['Empilhadeira 3t', 'Revisão geral', '500 h', ['A vencer', 'warn']], ['Plataforma 12m', 'Bateria', '90 dias', ['No prazo', 'ok']], ['Compressor', 'Filtros', '400 h', ['No prazo', 'ok']]],
             'legenda': 'Preventiva por horas, dias ou ciclos'},
            {'tipo': 'celular', 'url': 'os/checklist', 'titulo': 'Checklist da OS', 'itens': [['Nível de óleo', 'ok'], ['Filtro de ar', 'ok'], ['Mangueiras', 'warn'], ['Bateria', 'ok'], ['Fotos antes/depois', 'mudo']], 'rodape': 'Concluir OS', 'legenda': 'Checklist no celular'},
            {'tipo': 'ficha', 'url': 'ativos/gerador-150', 'titulo': 'Ativo · Gerador 150kVA', 'etiqueta': ['Operando', 'ok'],
             'campos': [['Patrimônio', 'GER-0150'], ['Horímetro', '4.812 h'], ['Última preventiva', '4.600 h'], ['Próxima', '4.850 h'], ['Local', 'Pátio Campinas']],
             'linha_tempo': [['Hoje', 'Horímetro atualizado: 4.812 h'], ['Há 3 dias', 'OS-2025 concluída'], ['Há 1 mês', 'Preventiva 250h executada']], 'legenda': 'Histórico de cada equipamento'},
            {'tipo': 'painel', 'url': 'painel-pmp', 'titulo': 'Painel de manutenção', 'kpis': [['OS abertas', '23', 'info'], ['Atrasadas', '3', 'bad'], ['Disponibilidade', '96%', 'ok']],
             'barras': [['Mai', 31], ['Jun', 28], ['Jul', 26], ['Ago', 29], ['Set', 24], ['Out', 23]], 'legenda': 'Ordens de serviço abertas por mês'},
        ],
    },
    {
        'codigo': 'servicos', 'nome': 'Serviços', 'pagina': '/software-gestao-de-servicos/',
        'frase': 'Venda, execute e cobre serviços em uma plataforma só.',
        'descricao': 'Clientes, orçamentos, propostas, contratos de serviço com assinatura, ordens de serviço e financeiro.',
        'inclui': [
            ('Comercial', 'Clientes, orçamentos, propostas comerciais, contratos de serviço e assinatura eletrônica'),
            ('Execução', 'Ordens de serviço, ativos do cliente, checklists, preventiva'),
            ('Financeiro', 'Contas a receber e a pagar, fluxo de caixa, planos de cobrança, conciliação bancária'),
            ('Relacionamento', 'Reclamações, sugestões e elogios dos clientes'),
            ('Montagem industrial', 'Frentes de serviço, horas por técnico e entrada e saída de equipamentos em obra'),
        ],
        'mockups': [
            {'tipo': 'documento', 'url': 'propostas/142', 'titulo': 'Proposta comercial #142', 'selo': ['Enviada', 'info'],
             'linhas': [['Montagem de tubulação industrial', 'R$ 18.500'], ['Mão de obra especializada (80 h)', 'R$ 9.600'], ['Locação de plataforma (10 dias)', 'R$ 4.200'], ['Deslocamento', 'R$ 800']], 'total': 'Total R$ 33.100', 'legenda': 'Proposta com aceite online'},
            {'tipo': 'documento', 'url': 'contratos/assinatura', 'titulo': 'Contrato de serviço', 'selo': ['Assinado', 'ok'],
             'linhas': [['Contratante', 'Indústria Alfa Ltda'], ['Objeto', 'Manutenção mensal'], ['Vigência', '12 meses'], ['Reajuste', 'IPCA']], 'total': 'R$ 6.400 / mês', 'legenda': 'Contrato com assinatura eletrônica'},
            {'tipo': 'kanban', 'url': 'ordens-de-servico', 'titulo': 'Ordens de serviço', 'colunas': [
                ['Agendadas', [['OS-318 Alfa', 'Visita técnica', 'info']]], ['Em campo', [['OS-315 Beta', 'Montagem bloco B', 'warn'], ['OS-312 Gama', 'Instalação', 'warn']]],
                ['Concluídas', [['OS-309 Delta', 'Laudo emitido', 'ok'], ['OS-307 Alfa', 'Manutenção mensal', 'ok']]]], 'legenda': 'Execução em campo'},
            {'tipo': 'tabela', 'url': 'contas-a-receber', 'titulo': 'Contas a receber', 'kpis': [['A receber', 'R$ 92 mil'], ['Vencidas', 'R$ 6 mil'], ['Recebido mês', 'R$ 58 mil']],
             'cabecalho': ['Cliente', 'Serviço', 'Valor', 'Status'],
             'linhas': [['Indústria Alfa', 'Manutenção mensal', 'R$ 6.400', ['Aberta', 'info']], ['Obras Beta', 'Montagem B', 'R$ 18.500', ['Paga', 'ok']], ['Gama Ltda', 'Instalação', 'R$ 4.800', ['Vencida', 'bad']]], 'legenda': 'Financeiro'},
            {'tipo': 'painel', 'url': 'painel-servicos', 'titulo': 'Painel de serviços', 'kpis': [['Em campo', '14', 'info'], ['Concluídas', '86', 'ok'], ['Margem', '31%', 'ok']],
             'barras': [['Mai', 52], ['Jun', 61], ['Jul', 58], ['Ago', 70], ['Set', 74], ['Out', 83]], 'legenda': 'Serviços concluídos por mês'},
        ],
    },
    {
        'codigo': 'logistica', 'nome': 'Logística', 'pagina': '/software-gestao-frota-logistica/',
        'frase': 'Frota, rotas e transporte sob controle.',
        'descricao': 'Agenda de motoristas, saídas de veículos, rastreamento, abastecimento e custo por trajeto.',
        'inclui': [
            ('Logística', 'Programação, fretes, pátio, rotas, checklists de saída e retorno'),
            ('Frota', 'Veículos, motoristas, abastecimento, manutenção da frota e custos'),
            ('Rastreamento', 'Posição dos veículos em mapa'),
            ('Equipe', 'Colaboradores e app do motorista no celular'),
        ],
        'mockups': [
            {'tipo': 'kanban', 'url': 'programacao-logistica', 'titulo': 'Programação logística', 'colunas': [
                ['A programar', [['Entrega #881', 'Campinas → Limeira', 'warn']]], ['Em rota', [['Carreta ABC-1D23', 'Previsão 15:40', 'info'], ['Munck XYZ-9F87', 'Obra Beta', 'info']]],
                ['Entregue', [['Entrega #877', 'Assinada', 'ok'], ['Entrega #874', 'Assinada', 'ok']]]], 'legenda': 'Programação de saídas'},
            {'tipo': 'mapa', 'url': 'rastreamento', 'titulo': 'Rastreamento ao vivo', 'pontos': [[80, 120, 'ok'], [160, 190, 'info'], [230, 140, 'warn'], [120, 240, 'ok']],
             'lista': [['ABC-1D23 · Carreta', 'Em rota', 'info'], ['XYZ-9F87 · Munck', 'Parado na obra', 'warn'], ['QWE-4R56 · Caminhão', 'Em rota', 'info'], ['RTY-7U89 · Utilitário', 'Pátio', 'ok']], 'legenda': 'Rastreamento'},
            {'tipo': 'tabela', 'url': 'frota/veiculos', 'titulo': 'Frota de veículos', 'kpis': [['Veículos', '27'], ['Em manutenção', '3'], ['Km no mês', '41.200']],
             'cabecalho': ['Placa', 'Tipo', 'Motorista', 'Situação'],
             'linhas': [['ABC-1D23', 'Carreta', 'Carlos M.', ['Em rota', 'info']], ['XYZ-9F87', 'Munck', 'Rafael S.', ['Obra', 'warn']], ['QWE-4R56', 'Caminhão', 'João P.', ['Em rota', 'info']], ['RTY-7U89', 'Utilitário', 'Marcos L.', ['Pátio', 'ok']]], 'legenda': 'Gestão de frota'},
            {'tipo': 'celular', 'url': 'motorista/saida', 'titulo': 'Checklist de saída', 'itens': [['Pneus e freios', 'ok'], ['Luzes', 'ok'], ['Amarração da carga', 'warn'], ['Documentos', 'ok'], ['Foto da carga', 'mudo']], 'rodape': 'Liberar saída', 'legenda': 'App do motorista'},
            {'tipo': 'painel', 'url': 'custos-frota', 'titulo': 'Custo por trajeto', 'kpis': [['Custo/km', 'R$ 3,12', 'info'], ['Combustível', 'R$ 28 mil', 'warn'], ['Entregas no prazo', '94%', 'ok']],
             'barras': [['Mai', 3.6], ['Jun', 3.5], ['Jul', 3.4], ['Ago', 3.3], ['Set', 3.2], ['Out', 3.1]], 'legenda': 'Custo por km (R$)'},
        ],
    },
    {
        'codigo': 'estoque', 'nome': 'Estoque', 'pagina': '/wms/',
        'frase': 'Materiais e peças com controle de verdade.',
        'descricao': 'Almoxarifado, movimentações, inventário, compras e reposição, inclusive em veículos e técnicos.',
        'inclui': [
            ('Materiais e peças', 'Cadastro, saldos, movimentações de entrada e saída'),
            ('Almoxarifado', 'Depósitos, almoxarifado volante em veículos e técnicos, transferências'),
            ('Compras e reposição', 'Pedidos de compra, requisições de peças, alertas de estoque mínimo'),
            ('Inventário', 'Contagens e ajustes com histórico'),
        ],
        'mockups': [
            {'tipo': 'tabela', 'url': 'almoxarifado', 'titulo': 'Almoxarifado', 'kpis': [['Itens', '1.284'], ['Abaixo do mínimo', '17'], ['Giro do mês', '2,4x']],
             'cabecalho': ['Código', 'Material', 'Saldo', 'Situação'],
             'linhas': [['FLT-204', 'Filtro de óleo', '38 un', ['OK', 'ok']], ['MNG-118', 'Mangueira 1/2"', '6 un', ['Baixo', 'warn']], ['BAT-060', 'Bateria 60Ah', '2 un', ['Crítico', 'bad']], ['OLE-15W', 'Óleo 15W40 (20L)', '14 un', ['OK', 'ok']]], 'legenda': 'Saldos e alertas'},
            {'tipo': 'tabela', 'url': 'movimentacoes', 'titulo': 'Movimentações', 'cabecalho': ['Data', 'Material', 'Tipo', 'Destino'],
             'linhas': [['16/10', 'Filtro de óleo', ['Saída', 'warn'], 'OS-2041'], ['16/10', 'Bateria 60Ah', ['Entrada', 'ok'], 'Depósito Central'], ['15/10', 'Mangueira 1/2"', ['Transf.', 'info'], 'Veículo ABC-1D23'], ['15/10', 'Óleo 15W40', ['Saída', 'warn'], 'OS-2038']], 'legenda': 'Entradas, saídas e transferências'},
            {'tipo': 'celular', 'url': 'coletor', 'titulo': 'Coletor de estoque', 'itens': [['Filtro de óleo · 38', 'ok'], ['Mangueira 1/2" · 6', 'warn'], ['Bateria 60Ah · 2', 'bad'], ['Óleo 15W40 · 14', 'ok']], 'rodape': 'Finalizar contagem', 'legenda': 'Inventário pelo celular'},
            {'tipo': 'painel', 'url': 'estoque/painel', 'titulo': 'Painel de estoque', 'kpis': [['Valor em estoque', 'R$ 312 mil', 'info'], ['Rupturas', '2', 'bad'], ['Acurácia', '98%', 'ok']],
             'barras': [['Mai', 280], ['Jun', 291], ['Jul', 297], ['Ago', 305], ['Set', 309], ['Out', 312]], 'legenda': 'Valor em estoque (R$ mil)'},
        ],
    },
    {
        'codigo': 'vendas', 'nome': 'Vendas', 'pagina': '/crm/',
        'frase': 'CRM de vendas que funciona sozinho.',
        'descricao': 'Leads, funil configurável, contas, catálogo, visitas, metas e propostas com aceite online.',
        'inclui': [
            ('Funil de vendas', 'Funis e etapas configuráveis, Kanban, motivos de perda, origens, campos personalizados'),
            ('Clientes e catálogo', 'Contas (clientes), contatos, catálogo de produtos e serviços'),
            ('Equipe de campo', 'Visitas, roteiro do dia, veículos, despesas de visita, agenda e mapa'),
            ('Metas e propostas', 'Metas por vendedor, propostas com PDF, envio por e-mail ou WhatsApp e aceite online'),
            ('Integrações', 'Importação de planilha, API e extensão para o WhatsApp Web'),
        ],
        'mockups': [
            {'tipo': 'kanban', 'url': 'funil-de-vendas', 'titulo': 'Funil de vendas', 'colunas': [
                ['Novo', [['Metalúrgica Alfa', 'R$ 48 mil', 'info'], ['Obras Beta', 'R$ 22 mil', 'info']]], ['Proposta', [['Gama Eventos', 'R$ 15 mil', 'warn']]],
                ['Negociação', [['Delta Log', 'R$ 90 mil', 'warn']]], ['Ganho', [['Epsilon', 'R$ 36 mil', 'ok']]]], 'legenda': 'Funil Kanban'},
            {'tipo': 'ficha', 'url': 'leads/ficha', 'titulo': 'Ficha do lead · Delta Log', 'etiqueta': ['Negociação', 'warn'],
             'campos': [['Contato', 'Maria Souza'], ['Telefone', '(19) 99999-0000'], ['Valor', 'R$ 90.000'], ['Previsão', '20/11'], ['Origem', 'WhatsApp'], ['Responsável', 'Carlos']],
             'linha_tempo': [['Hoje', 'Ligação: pediu desconto'], ['Ontem', 'Proposta #31 enviada'], ['Há 5 dias', 'Visita realizada']], 'legenda': 'Histórico do relacionamento'},
            {'tipo': 'documento', 'url': 'crm-proposta/aceite', 'titulo': 'Proposta #31 · Delta Log', 'selo': ['Aceita', 'ok'],
             'linhas': [['Locação de 2 geradores', 'R$ 60.000'], ['Manutenção preventiva', 'R$ 24.000'], ['Frete e instalação', 'R$ 6.000'], ['Desconto', '- R$ 0']], 'total': 'Total R$ 90.000', 'legenda': 'Proposta com aceite online'},
            {'tipo': 'painel', 'url': 'metas', 'titulo': 'Metas de vendas', 'kpis': [['Meta do mês', 'R$ 250 mil', 'info'], ['Realizado', 'R$ 187 mil', 'ok'], ['Atingimento', '75%', 'warn']],
             'barras': [['Carlos', 82], ['Ana', 64], ['Rafael', 51], ['Bia', 38]], 'legenda': 'Vendas por vendedor (R$ mil)'},
            {'tipo': 'mapa', 'url': 'visitas/mapa', 'titulo': 'Visitas e roteiro do dia', 'pontos': [[70, 110, 'ok'], [150, 160, 'info'], [220, 120, 'warn'], [110, 230, 'info']],
             'lista': [['09:00 Metalúrgica Alfa', 'Realizada', 'ok'], ['11:00 Obras Beta', 'Em andamento', 'info'], ['14:30 Gama Eventos', 'Agendada', 'warn'], ['16:00 Delta Log', 'Agendada', 'warn']], 'legenda': 'Roteiro'},
        ],
    },
]

POR_CODIGO = {p['codigo']: p for p in PACOTES}

# Página -> pacote ao qual ela pertence (para o botão Assinar e o destaque no menu).
PAGINA_PACOTE = {p['pagina']: p['codigo'] for p in PACOTES}
PAGINA_PACOTE['/software-montagem-industrial/'] = 'servicos'   # vertical de Serviços
PAGINA_PACOTE['/gestao-locadoras/'] = 'locadora'


def venda_online(codigo):
    """ERP Locadoras não tem preço de tabela: o site manda falar com um especialista em vez de assinar."""
    return POR_CODIGO[codigo].get('venda_online', True)

# Páginas antigas de módulos avulsos: saem do ar e redirecionam para o pacote equivalente.
REDIRECTS = {
    'solucoes/crm': '/crm/', 'solucoes/frota': '/software-gestao-frota-logistica/', 'solucoes/wms': '/wms/',
    'solucoes/os': '/software-gestao-de-servicos/', 'solucoes/recebimentos': '/software-gestao-de-servicos/',
    'solucoes/rh': '/solucoes/', 'solucoes/analytics': '/solucoes/', 'segmentos': '/solucoes/',
    'solucoes\\.php': '/solucoes/', 'sobre-nos(\\.html)?': '/sobre/',
}

# Comparativo da página /planos/: (linha, {pacote: bool}). Sem valores.
# App instalável próprio de cada solução (espelha config/pacotes.php do sistema): nome, cor e a tela em que o app abre.
APPS = {
    'locadora': ('Oravel Locadoras', '#0f172a', 'Painel da locadora', [('Ativos locados', 'info'), ('Contratos a vencer', 'warn'), ('Avarias em análise', 'bad'), ('Medições do mês', 'ok')]),
    'vendas': ('Oravel Vendas', '#db2777', 'Funil de vendas', [('Novos contatos', 'info'), ('Proposta enviada', 'warn'), ('Visita amanhã', 'info'), ('Fechado no mês', 'ok')]),
    'manutencao': ('Oravel Manutenção', '#ea580c', 'Ordens de serviço', [('OS-2041 Gerador', 'bad'), ('OS-2044 Empilhadeira', 'warn'), ('OS-2038 Plataforma', 'info'), ('OS-2025 Preventiva', 'ok')]),
    'servicos': ('Oravel Serviços', '#2563eb', 'Ordens de serviço', [('OS-310 Instalação', 'info'), ('OS-308 Visita técnica', 'warn'), ('OS-301 Montagem', 'ok'), ('Orçamento 118', 'mudo')]),
    'estoque': ('Oravel Estoque', '#7c3aed', 'Almoxarifado', [('Filtro de óleo', 'warn'), ('Correia dentada', 'ok'), ('Bateria 12V', 'bad'), ('Recebimento NF 4471', 'info')]),
    'logistica': ('Oravel Logística', '#0d9488', 'Programação', [('Entrega Construtora Alfa', 'info'), ('Retirada Obras Beta', 'warn'), ('Veículo VAN-02', 'ok'), ('Coleta Delta Log', 'info')]),
}

MATRIZ = [
    ('Cadastro de ativos e equipamentos', {'locadora', 'manutencao', 'servicos'}),
    ('Ordens de serviço e checklists', {'locadora', 'manutencao', 'servicos'}),
    ('Manutenção preventiva (PMP) e horímetro', {'locadora', 'manutencao'}),
    ('Contratos de locação, medições e franquias', {'locadora'}),
    ('Avarias com dossiê do equipamento', {'locadora'}),
    ('Clientes', {'locadora', 'manutencao', 'servicos'}),
    ('Orçamentos, propostas e contratos de serviço', {'locadora', 'servicos'}),
    ('Financeiro: contas a receber e a pagar, cobrança', {'locadora', 'servicos'}),
    ('Estoque: materiais, peças e almoxarifado', {'locadora', 'estoque'}),
    ('Logística, frota e rastreamento', {'locadora', 'logistica'}),
    ('CRM de vendas: funil, contas, visitas e metas', {'locadora', 'vendas'}),
    ('App instalável próprio da solução (celular)', {'locadora', 'manutencao', 'servicos', 'logistica', 'estoque', 'vendas'}),
    ('Academia Oravel: treinamento do seu pacote', {'locadora', 'manutencao', 'servicos', 'logistica', 'estoque', 'vendas'}),
    ('Usuários, perfis de acesso e app do colaborador', {'locadora', 'manutencao', 'servicos', 'logistica', 'estoque', 'vendas'}),
]
