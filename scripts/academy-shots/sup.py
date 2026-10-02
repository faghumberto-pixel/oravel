import A
from A import *
A.LESSON_DIR = './visuals'

AUTO = flow([
    ('Peça usada numa OS', 'O técnico lança o material como usado (na OS ou na preventiva).'),
    ('Estoque desce sozinho', 'Ninguém edita o saldo na mão.', 'green'),
    ('Ficou abaixo do mínimo?', 'O sistema confere a cada baixa.', 'amber'),
    ('Solicitação criada', 'Nasce pendente, com a OS, o material e a quantidade sugerida.', 'purple'),
])

# ============ BEM-VINDO (entrada da Academia) ============
lesson('/index.html',
  p('A <strong>Oravel Academy</strong> é o seu guia completo do sistema: o que cada área faz, como cada módulo funciona, para quem é e quando usar.'),
  h2('Como estudar aqui'),
  flow([('Escolha um módulo', 'Todos estão no menu lateral. Não existe ordem obrigatória: faça só os que você precisa, quando quiser.'),
        ('Leia e veja as telas', 'Cada aula mostra a tela real do sistema, com números explicando cada parte.', 'amber'),
        ('Faça a prova', 'Responda e entregue. O gabarito aparece na hora, e cada acerto vale pontos.', 'purple'),
        ('Receba o certificado', 'Conclua o curso e atinja a nota mínima para baixar o seu certificado.', 'green')]),
  h2('O que você encontra'),
  cols([
      ('Painel Admin', 'O painel do dia a dia: dashboard, quadro do pátio e agenda dos técnicos.', 'blue'),
      ('Ativos e Frota', 'Equipamentos, grupos de checklist e o dossiê por QR Code.', 'blue'),
      ('Manutenção', 'Ordens de serviço, preventiva, checklists, mobilização, avarias e substituição.', 'amber'),
      ('Comercial', 'Contratos, clientes, propostas, orçamentos e CRM.', 'green'),
      ('Logística', 'Frota leve, motoristas e chegadas no pátio.', 'purple'),
      ('Suprimentos', 'Materiais, fornecedores e o ciclo de compras.', 'red')]),
  tip('Seus pontos e o ranking da empresa aparecem no início. Quanto mais módulos você estuda, mais pontos e mais certificados.', '⭐ Dica'),
)

# ============ MATERIAIS E PEÇAS ============
lesson('/modulos/materiais.html',
  p('Pense no catálogo de <strong>Materiais e Peças</strong> como o seu <strong>almoxarifado digital</strong>: cada peça tem estoque mínimo, fornecedor e custo — e o sistema avisa (e até pede a compra) <strong>antes de faltar</strong>.'),
  menu('Materiais e Peças → Gestão de Estoque → Materiais/Peças'),
  h2('Onde encontrar'),
  fig('sup-menu-estoque', 'Menu lateral: Materiais e Peças → Gestão de Estoque', [
      '<strong>Materiais/Peças</strong> — o catálogo, tela desta aula.',
      '<strong>Histórico de Movimentação</strong> — toda entrada e saída de estoque.',
      '<strong>Análise de Estoque</strong> — apoio para decidir o que repor.'], narrow=True),
  h2('A tela do catálogo'),
  fig('sup-materiais', 'Lista de Materiais/Peças', [
      '<strong>Criar Material/Peças</strong> — cadastra uma peça nova.',
      '<strong>Cartões de resumo</strong> — Total de Materiais, Valor em Estoque, <span class="mk-chip red">Abaixo do Mínimo</span> (risco de ruptura) e <span class="mk-chip amber">Sem Fornecedor Homologado</span>.',
      '<strong>Imprimir e Exportar</strong> — levam só o que está filtrado na tela.',
      '<strong>Taxa de Estoque Saudável</strong> — o ponteiro no verde é o que você quer.',
      '<strong>Qtd. Atual</strong> — compare sempre com o estoque mínimo da peça.']),
  h2('Como ler os cartões'),
  kpis([('Total de Materiais', '15', 'blue'), ('Valor em Estoque', 'R$ 95.497', 'green'), ('Abaixo do Mínimo', '3', 'red'), ('Sem Fornecedor Homologado', '3', 'amber')]),
  ul(['<span class="mk-chip red">Vermelho</span> pede ação: são peças que podem faltar na próxima OS.',
      '<span class="mk-chip amber">Amarelo</span> é pendência de cadastro: falta homologar um fornecedor.']),
  h2('Cadastrando uma peça'),
  fig('sup-material-novo', 'Formulário: Informações Básicas, Compatibilidade e Fornecedor', [
      '<strong>SKU / Código</strong> — único e obrigatório.',
      '<strong>Grupos de Ativo Compatíveis</strong> — diz quais equipamentos usam a peça; é daqui que saem as <em>sugestões na Manutenção Preventiva</em>.',
      '<strong>Fornecedor Homologado</strong> — escolha na lista ou cadastre na hora pelo botão <strong>+</strong>.']),
  fig('sup-material-estoque', 'Mais abaixo no formulário: estoque e rastreabilidade', [
      '<strong>Estoque Mínimo</strong> — abaixo dele o sistema pede a compra.',
      '<strong>Estoque Máximo</strong> — a quantidade sugerida cobre pelo menos até aqui.',
      '<strong>Exige número de série ao aplicar</strong> — o técnico fica obrigado a informar o nº de série antes de salvar o uso na OS.',
      '<strong>Garantia (dias)</strong> — se a mesma peça (mesmo nº de série) falhar de novo dentro do prazo, o gestor é alertado.']),
  h2('O estoque se movimenta sozinho'),
  AUTO,
  tip('O <strong>Estoque Atual</strong> do material não é digitado no cadastro: ele é a <strong>soma do saldo de cada filial</strong>. Desce quando a peça é usada, sobe quando uma compra é recebida, e as correções são feitas por filial (em <em>Saldo de Materiais por Filial</em>) ou com um <em>Inventário</em>.', '💡 Como o saldo funciona'),
  h2('Checklist antes de salvar uma peça'),
  check([('SKU preenchido e único', 'ok'), ('Estoque mínimo e máximo definidos', 'ok'), ('Grupos de ativo compatíveis marcados', 'ok'), ('Fornecedor homologado vinculado', 'ok'), ('Peças com garantia: “exige nº de série” ligado', 'ok')]),
  h2('Detalhes do módulo'),
)

# ============ FORNECEDORES ============
lesson('/modulos/fornecedores.html',
  p('<strong>Fornecedores</strong> é a ficha de quem vende peças e materiais para a sua operação. Cada <em>Material</em> pode apontar para um fornecedor homologado, e a ficha guarda os critérios de conformidade exigidos <strong>antes</strong> de fechar com alguém novo.'),
  menu('Materiais e Peças → Gestão de Compras → Fornecedores'),
  h2('Onde encontrar'),
  fig('sup-menu-compras', 'Menu lateral: Materiais e Peças → Gestão de Compras', [
      'Requisições de Compra', 'Ordens de Compra', 'Recebimentos de Compra', 'Solicitações de peças',
      '<strong>Fornecedores</strong> — a tela desta aula.'], narrow=True),
  h2('A lista de fornecedores'),
  fig('sup-fornecedores', 'Lista de Fornecedores', [
      '<strong>Criar Fornecedor</strong> — abre a ficha de cadastro.',
      '<strong>Indicadores</strong> — Total de Fornecedores, <span class="mk-chip green">Compliance Completo</span>, Materiais Vinculados e <span class="mk-chip amber">Pendentes de Certidão</span>.',
      '<strong>Imprimir</strong> — versão para papel com os filtros aplicados.',
      '<strong>Taxa de Compliance Completo</strong> — quantos fornecedores estão com os três critérios em dia.']),
  h2('A ficha do fornecedor'),
  fig('sup-fornecedor-novo', 'Cadastro de Fornecedor', [
      '<strong>Identificação</strong> — razão social, CNPJ/CPF, e-mail e telefone/WhatsApp.',
      '<strong>Dados bancários</strong> — conta ou chave PIX para pagamento.',
      '<strong>Critérios de Homologação</strong> — liga/desliga: regularidade CEIS/CNEP, fora da Lista de Trabalho Escravo e termo LGPD assinado.',
      '<strong>Arquivos e Certidões</strong> — cartão CNPJ, contrato social, CND Federal, CRF FGTS e CNDT, guardados como link/referência.']),
  h2('Quando um fornecedor está “completo”'),
  check([('Regularidade CEIS/CNEP conferida', 'ok'), ('Fora da Lista de Trabalho Escravo', 'ok'), ('Termo de consentimento LGPD assinado', 'ok'), ('Certidões em dia (CND Federal, CRF FGTS, CNDT)', 'wait')]),
  note('warn', '⚠️ É um checklist de apoio', 'Os campos são preenchidos <strong>manualmente</strong>: o sistema não consulta órgãos externos nem bloqueia compra por falta de certidão. Quem decide é o time de compras.'),
  h2('Detalhes do módulo'),
)

# ============ SOLICITAÇÃO DE PEÇAS ============
lesson('/modulos/solicitacao-pecas.html',
  p('A <strong>Solicitação de Peças</strong> é a <strong>fila de compras da oficina</strong>. Você não cria pedido aqui: ele <strong>nasce sozinho</strong> quando uma peça usada numa Ordem de Serviço deixa o estoque abaixo do mínimo. O time de compras só acompanha e atualiza o andamento.'),
  menu('Materiais e Peças → Gestão de Compras → Solicitações de peças'),
  h2('Como uma solicitação nasce'),
  AUTO,
  note('bad', '🚫 Não existe botão “Nova Solicitação”', 'É de propósito: a fila mostra só o que foi realmente consumido, sem pedidos manuais soltos.'),
  h2('A tela da fila'),
  fig('sup-pecas', 'Solicitações de Peças', [
      '<strong>Cartões</strong> — Total de Solicitações, <span class="mk-chip amber">Pendentes</span>, Compradas/Pedidas e Custo em Peças no Mês (soma do que já foi entregue).',
      '<strong>Situação</strong> — em que ponto a compra está (veja os três estados abaixo).',
      '<strong>Atualizar</strong> — avança o status da logística (é o único campo editável).',
      '<strong>Converter em Requisição</strong> — leva a solicitação para o ciclo formal de compras (cotação, ordem de compra e recebimento).',
      '<strong>Imprimir</strong> — versão para papel; este módulo não exporta para Excel.']),
  h2('Os três estados da solicitação'),
  seq(('Pendente', 'amber'), ('Peça Comprada/Pedida', 'blue'), ('Entregue', 'green')),
  table(['Status', 'O que significa', 'Quem age'], [
      [chip('Pendente', 'amber'), 'Recém-criada, aguardando ação de compras.', 'Compras'],
      [chip('Peça Comprada/Pedida', 'blue'), 'Já foi encomendada ao fornecedor.', 'Compras'],
      [chip('Entregue', 'green'), 'A peça já chegou ao técnico.', 'Logística / Compras']]),
  tip('<strong>Pendente é urgente de verdade:</strong> o cartão fica destacado porque há uma peça faltando para alguma OS.', '💡 Dica'),
  h2('Detalhes do módulo'),
)

# ============ CICLO DE COMPRAS ============
lesson('/modulos/ciclo-compras.html',
  p('O <strong>Ciclo de Compras</strong> é o caminho completo de uma peça: alguém <strong>pede</strong>, você <strong>cota</strong> com fornecedores, gera a <strong>Ordem de Compra</strong> e, quando a peça chega, o <strong>Recebimento</strong> atualiza o estoque sozinho.'),
  menu('Materiais e Peças → Gestão de Compras'),
  h2('Onde encontrar'),
  fig('sup-menu-compras', 'Menu lateral: as telas do ciclo, na ordem em que o fluxo acontece', [
      '<strong>Requisições de Compra</strong> — o pedido, ponto de partida.',
      '<strong>Ordens de Compra</strong> — o documento de compra com o fornecedor.',
      '<strong>Recebimentos de Compra</strong> — a chegada da peça.',
      '<strong>Solicitações de peças</strong> — a fila automática que também pode virar requisição.',
      '<strong>Fornecedores</strong> — quem vende.'], narrow=True),
  h2('As etapas do ciclo'),
  flow([('Requisição', 'Alguém pede a peça — vinda de uma OS ou aberta à mão.'),
        ('Cotação', 'Compras registra os preços de um ou mais fornecedores.', 'amber'),
        ('Ordem de Compra', 'Gerada da cotação escolhida (ou avulsa, sem requisição).', 'purple'),
        ('Recebimento', 'A peça chega: confirma contra a ordem, total ou parcial.', 'green'),
        ('Estoque', 'O saldo sobe sozinho com a quantidade recebida.', 'green')]),
  h2('1. Requisição de Compra'),
  fig('sup-requisicoes', 'Lista de Requisições de Compra', [
      '<strong>Nova Requisição</strong> — abre o pedido.',
      '<strong>Prioridade</strong> — <span class="mk-chip gray">Normal</span> <span class="mk-chip amber">Urgente</span> <span class="mk-chip red">Crítico</span>.',
      '<strong>Status</strong> — mostra em que etapa o pedido está (veja a linha do tempo abaixo).']),
  p('O pedido percorre estes estados, do começo ao fim:'),
  vflow([('Rascunho', 'Ainda sendo montado.', ''),
         ('Aguardando aprovação', 'Esperando alguém aprovar (fica registrado quem aprovou e quando).', 'amber'),
         ('Aprovada ou Recusada', 'Se recusada, vai com o motivo.', 'red'),
         ('Cotada', 'Já tem propostas de fornecedores; uma é marcada como a vencedora.', 'purple'),
         ('A caminho', 'Ordem de compra emitida, peça em trânsito.', ''),
         ('Entregue', 'Chegou e foi recebida.', 'green')]),
  fig('sup-req-cotada', 'Uma requisição já cotada', [
      '<strong>Prioridade</strong> do pedido.',
      '<strong>Status</strong> — aqui, <span class="mk-chip purple">Cotada</span>.',
      '<strong>Itens da Requisição</strong> — o que está sendo pedido, com quantidade e custo.']),
  h2('2. Ordem de Compra'),
  fig('sup-oc', 'Lista de Ordens de Compra', [
      '<strong>Nova Ordem de Compra (Avulsa)</strong> — compra direta, sem requisição prévia (reposição geral ou compra avulsa).',
      '<strong>Cartões</strong> — total de ordens, valor total, abertas (aguardando) e recebidas.',
      '<strong>Status</strong> — <span class="mk-chip amber">Aberta</span> <span class="mk-chip blue">Parcialmente recebida</span> <span class="mk-chip green">Recebida</span> <span class="mk-chip red">Cancelada</span>.']),
  fig('sup-oc-edit', 'Dentro de uma Ordem de Compra', [
      '<strong>Registrar Recebimento</strong> — quando a peça chegar, é por aqui.',
      '<strong>Cancelar Ordem de Compra</strong> — só se a compra não vai acontecer.',
      '<strong>Itens da Ordem</strong> — SKU, material, quantidade pedida, preço e quanto já foi recebido.',
      '<strong>Pendente</strong> — o que ainda falta chegar.']),
  h2('3. Recebimento'),
  fig('sup-recebimento', 'Lista de Recebimentos de Compra', [
      '<strong>Novo Recebimento</strong> — confirma a chegada de uma entrega.',
      '<strong>Nota Fiscal</strong> — guarde o número da NF de cada entrega.']),
  fig('sup-recebimento-novo', 'Registrando um recebimento', [
      '<strong>Ordem de Compra</strong> — escolha a ordem que está chegando.',
      '<strong>Número da Nota Fiscal</strong> da entrega.']),
  note('tip', '📦 Entrega parcial é normal', 'Se o fornecedor entregar em várias remessas, registre um recebimento para cada uma. A ordem vira <em>Parcialmente recebida</em> e passa a <em>Recebida</em> quando tudo chegar. Ao confirmar, a quantidade recebida é somada automaticamente ao estoque. Para peças que exigem nº de série, o sistema não deixa receber sem informar.'),
  h2('Detalhes do módulo'),
)

# ============ PROCESSO DE SUPRIMENTOS ============
lesson('/suprimentos/processo-suprimentos.html',
  p('Esta aula junta as peças: <strong>catálogo</strong>, <strong>fornecedores</strong>, <strong>compras</strong> e <strong>estoque</strong> funcionando como um só fluxo — da peça usada na oficina até o saldo atualizado no almoxarifado.'),
  h2('O fluxo completo'),
  flow([('Peça é usada', 'Na OS ou na preventiva; o estoque desce automático.'),
        ('Alerta de mínimo', 'Abaixo do mínimo nasce a Solicitação de Peças.', 'amber'),
        ('Requisição e cotação', 'Compras pede aprovação e cota com fornecedores.', 'purple'),
        ('Ordem de Compra', 'Documento de compra com o fornecedor vencedor.', ''),
        ('Recebimento', 'A peça chega; o estoque sobe e fica registrado.', 'green')]),
  h2('Cada área tem um papel'),
  cols([
      ('Materiais e Peças', 'O catálogo: SKU, estoque mínimo e máximo, custo, garantia e fornecedor homologado. É onde o alerta de reposição é calculado.', 'blue'),
      ('Fornecedores', 'Quem vende, com dados de pagamento e checklist de conformidade (CEIS/CNEP, LGPD, certidões).', 'purple'),
      ('Compras', 'Solicitação de Peças (automática), Requisição, Cotação, Ordem de Compra e Recebimento.', 'amber'),
      ('Estoque', 'Histórico de Movimentação (toda entrada e saída), saldo por filial e Inventário (contagem física).', 'green')]),
  h2('Solicitação de Peças × Requisição de Compra'),
  p('São dois nomes parecidos para dois momentos diferentes — vale não confundir:'),
  table(['', 'Solicitação de Peças', 'Requisição de Compra'], [
      ['<strong>Como nasce</strong>', 'Sozinha, quando o consumo deixa o estoque abaixo do mínimo.', 'À mão, ou convertida a partir de uma Solicitação.'],
      ['<strong>O que você faz</strong>', 'Acompanha e atualiza o status (Pendente, Comprada/Pedida, Entregue).', 'Aprova, cota com fornecedores e gera a Ordem de Compra.'],
      ['<strong>Para onde vai</strong>', 'Pode ser convertida em Requisição.', 'Segue até a Ordem de Compra e o Recebimento.']]),
  h2('Como o estoque desce, sobe e se corrige'),
  cols([
      ('Desce', 'Quando uma peça é lançada como usada numa Ordem de Serviço ou numa execução de preventiva.', 'red'),
      ('Sobe', 'Quando uma compra é recebida (Recebimento de Compra).', 'green'),
      ('Corrige', 'Pelo Inventário (a contagem física vira ajuste quando diverge do saldo) ou ajustando o saldo de uma filial em <em>Saldo de Materiais por Filial</em>.', 'amber')]),
  note('tip', '💡 O extrato do almoxarifado', 'No <strong>Histórico de Movimentação</strong> cada entrada, saída e ajuste fica registrada, com o saldo que sobrou.'),
  h2('Onde fica cada tela'),
  fig('sup-menu-compras', 'Materiais e Peças → Gestão de Compras', [
      '<strong>Requisições de Compra</strong>', '<strong>Ordens de Compra</strong>', '<strong>Recebimentos de Compra</strong>', '<strong>Solicitações de peças</strong>', '<strong>Fornecedores</strong>'], narrow=True),
  fig('sup-menu-estoque', 'Materiais e Peças → Gestão de Estoque', [
      '<strong>Materiais/Peças</strong> — o catálogo.', '<strong>Histórico de Movimentação</strong> — o extrato de estoque.', '<strong>Análise de Estoque</strong> — apoio à reposição.'], narrow=True),
  h2('Quem faz o quê'),
  table(['Papel', 'O que faz no ciclo'], [
      ['Técnico ou supervisor', 'Usa as peças na OS e abre a Requisição quando precisa de algo.'],
      ['Compras', 'Aprova, cota, gera a Ordem de Compra e atualiza a fila de solicitações.'],
      ['Almoxarifado', 'Confirma o Recebimento e faz o Inventário.']]),
)
