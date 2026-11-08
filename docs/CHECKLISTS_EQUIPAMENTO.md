# Checklists por grupo de equipamento

Checklists padrão por tipo de equipamento (gerador, compressor, plataforma elevatória, guindaste, munk, veículo leve, caminhão e
empilhadeira), editáveis por empresa. Entrega em fases; cada uma para e espera OK antes da seguinte.

## Decisões (08/11/2026)
- **Tabelas novas com prefixo `ck_`.** O nome `checklist_templates` já é do módulo "Checklists" antigo (id numérico, 23 registros em PROD,
  menu Manutenção › Cadastros). Ele **não é tocado**. O novo módulo entra no mesmo menu como **"Checklists de equipamento"**, ao lado do antigo;
  trocar um pelo outro depende de decidir o que fazer com os 23 registros antigos, e fica para depois.
- **O que ativa o checklist na OS é o grupo de checklist do ativo** (`assets.checklist_group_id`, o campo "Grupo de checklist"), como já
  acontece com o checklist base e os planos de preventiva. Por isso `ck_templates.checklist_group_id` liga o template a esse grupo (C2).
- **Item crítico ↔ aptidão do G1:** `ck_items.catalogo_requisito_id` liga o item ao requisito equivalente do catálogo. Na C3, reprovar um
  item crítico grava um cumprimento reprovado nesse requisito, e o motor atual calcula o inapto e abre a OS pelo gatilho que já existe.
  Item crítico sem requisito equivalente ganha um requisito novo do tenant (C3).
- **Frota (C4):** migra para este motor, com cópia dos dados atuais e sem apagar as tabelas antigas até validar. C1 a C3 não tocam na Frota.
- Só Pint e testes (o repositório não tem phpstan).

## C1 — o que existe
- Tabelas: `ck_templates`, `ck_sections`, `ck_items`, `ck_executions`, `ck_execution_answers`, todas com `tenant_id`, UUID e soft delete
  onde há edição. Execuções guardam **snapshot** (nome/versão do template; texto, tipo e criticidade de cada item): editar o template
  nunca altera o que já foi executado.
- Models `Ck*` (só `CkTemplate` e `CkExecution` são módulos SaaS: `tabela_ck_templates` e `tabela_ck_execucoes`, **desligados por padrão**
  nos contratos) e policies `CkTemplatePolicy`/`CkExecutionPolicy` (corpo vazio, tudo no `AbstractPolicy`). Permissões nascem pelo
  `RoleResource` ao abrir "Perfis de Acesso"; não existe `permissions:sync`.
- Templates padrão em `database/seeders/checklists/*.yaml` (um por grupo, 18 a 24 itens). **Revisar com o responsável técnico antes de uso
  em produção:** normas citadas (NR-10/11/12/13/18/35, CTB, ANTT) são hipóteses, e itens com referência entram com `validar_norma = true`.
- `php artisan checklists:seed {--tenant=} {--group=}` e gancho no `TenantProvisioner` (só com a chave do contrato ligada). Idempotente: o
  que o tenant já tem (inclusive editado ou arquivado) não é recriado nem sobrescrito. Restaurar o padrão é ação explícita da C2.

## C2 — o que existe
- **Tela** Manutenção › Cadastros › "Checklists de equipamento" (`CkTemplateResource`, slug `checklists-equipamento`): lista com filtros por grupo de
  equipamento, grupo de checklist, tipo, origem, ativo e arquivados; criar e editar com **seções e itens aninhados e reordenáveis**.
- **Ações:** duplicar (cópia independente, customizada, versão 1, mantém o vínculo com requisitos e grupos), restaurar padrão (só para os que vieram
  do padrão, com confirmação e justificativa, sobe a versão e fica no registro), ativar/desativar, arquivar e desarquivar, e "Instalar checklists padrão".
- **Versão:** editar o conteúdo sobe a versão e marca como editado (o `checklists:seed` não sobrescreve mais); salvar sem mudar nada não gasta versão.
  O repeater regrava `ordem` a partir de 1, então a comparação usa a sequência e não o número.
- **Quem pode o quê** vem das permissões dos perfis (`ler/criar/editar/excluir_checklist_equipamento`; admin do tenant tem bypass), e a chave do
  contrato precisa estar ligada. Não há papel fixo no código: "só admin e gerente editam" se obtém dando essas permissões só a esses perfis.
- **Qual checklist vale para o ativo/OS** (`ChecklistAplicavel`): template ligado a um grupo de checklist vale para os ativos desse grupo; template sem
  grupo de checklist vale para os ativos do mesmo grupo de equipamento (assim os padrões já servem antes de a empresa montar grupos de checklist).
  Só entram ativos (não arquivados) do tenant. **A tela da OS passou a usar isso só depois** (correção pós-C4): ver "Checklists na OS".

## C3 — o que existe
- **Tela de execução** (`ChecklistExecucaoMobile`, rota `/admin/checklists/ativo/{assetId}`), no mesmo desenho da tela de checklist da Frota. Chega
  pelo QR do ativo: o botão **"Checklist do equipamento"** aparece no Dossiê do ativo (destino do QR) quando há checklist aplicável (`ChecklistAplicavel`)
  e a pessoa pode executar. OK / Problema / Não se aplica, número, texto, seleção e foto; câmera nos itens que pedem; **voz** nas observações;
  assinatura no fim; leitura de horímetro/km opcional.
- **Resultado** (`ChecklistExecucaoService`): item **crítico** não conforme = **reprovado**; só itens não críticos não conformes = **aprovado com
  ressalva**, e essas respostas são as **pendências** (`CkAnswer::pendencias()`); senão aprovado. Texto, tipo e criticidade vão para a resposta (snapshot).
- **Item crítico × aptidão (G1):** com a chave do catálogo ligada, cada item ligado a um requisito grava um cumprimento (conforme = OK; crítico
  não conforme = reprovado). Reprovado deixa o ativo **Inapto**, abre a **OS corretiva** pelo gatilho do G1 (com **downtime** pela regra da própria OS)
  e a OS recebe as **fotos** do item (coleção `checklist_evidencias`). Um novo checklist conforme grava OK e tira o ativo de Inapto.
  Item crítico **sem** requisito equivalente ganha um requisito do tenant (`RequisitoDoItem`), com `sem_registro = ignorar`: só vale para o ativo
  depois de reprovado, então os outros ativos do grupo não mudam de status.
- **Sem a chave do catálogo:** nada muda na aptidão; a tela **oferece** "Abrir OS corretiva agora" (já com itens, observações e fotos). Idempotente.
- Falha na integração com a aptidão é registrada em log e **nunca derruba** o checklist.
- **Chaves:** o módulo usa duas chaves do contrato (`tabela_ck_templates` para editar e `tabela_ck_execucoes` para executar), ambas desligadas por padrão.

## C4 — o que existe
- **Relatório** Relatórios › Históricos & Logs › **"Checklists de equipamento"** (`CkExecutionResource`, somente leitura): filtros por **período, grupo de
  equipamento, executor, resultado, checklist e origem**, busca, e detalhe com as respostas, as fotos e a assinatura. **Exportar Excel** (OpenSpout, como no
  resto do projeto) leva o que está na tela: aba "Execuções" e aba "Respostas". Acesso pela chave `tabela_ck_execucoes` e a permissão `ler_execucao_checklist`.
- **Histórico no ativo:** aba **"Checklists executados"** no cadastro do ativo (somente leitura; some sem permissão).
- **Fotos** ficam em disco privado e só saem pela rota `ck.resposta.foto`, para quem pode ver a execução e do próprio tenant (outro tenant = 404; caminho fora da
  pasta do tenant = 404).
- **Frota:** o checklist de saída/retorno da Frota **segue exatamente como está** (odômetro, pareamento, bloqueio, bateria, pneu, liberação). O que muda é que
  cada checklist gravado (e cada liberação) é **espelhado** no motor novo (`FrotaParaChecklists`), então o histórico e o relatório são unificados, com a origem
  "Frota". O espelho é idempotente (`legado_tipo`/`legado_id`) e, se falhar, vai para o log e **nunca derruba** o fluxo da Frota. Fotos da Frota não são copiadas.
- **Cópia do que já existe** (PROD hoje): `php artisan checklists:migrar-frota {--tenant=} {--dry-run}` copia os checklists já gravados e os **modelos** da Frota.
  Os modelos entram **inativos e sem grupo**: não se aplicam a nenhum ativo, só existem para o dia da troca. Nada da Frota é alterado ou apagado.

## Checklists na OS (correção pós-C4)
- A C3 entregou a regra (`ChecklistAplicavel`) e a execução pelo Dossiê/QR, mas **não ligou a tela da OS**. Corrigido: ao abrir a OS aparece a seção
  **"Checklists do equipamento"** com os modelos que valem para o ativo, **de onde vem o vínculo** (grupo de checklist ou grupo de equipamento), itens e críticos,
  o botão **Executar** (abre a execução já no modelo escolhido, `?checklist=<id>`) e os **últimos checklists** do equipamento. Painel: aba "Vistoria / Checklist"
  da OS (vale também na criação, assim que o ativo é escolhido). App do colaborador: tela da OS. A seção some sem permissão ou sem a chave do contrato.
- Executar a partir da OS **não amarra** a execução à OS (o `ordem_servico_id` da execução continua sendo a OS aberta por item crítico reprovado).

## O checklist no atendimento da OS (correções pós-C4)
O pedido de fundo: **o técnico, ao atender a OS, preenche também o checklist do equipamento, e isso fica registrado contra aquela OS.**
- **Registro contra a OS:** a execução guarda `os_id` (a OS do atendimento; diferente de `ordem_servico_id`, a OS corretiva aberta por item crítico reprovado), só se a OS é do mesmo
  tenant e do mesmo equipamento. Aparece no relatório (coluna "OS do atendimento"), na exportação e na seção da OS (feito/pendente).
- **Modo Campo (celular do técnico):** a etapa 2 "Vistoria / Checklist" mostra o checklist do equipamento com **Preencher**; a OS **só conclui** depois de feitos os checklists que valem
  para o equipamento (decisão: obrigatório no Modo Campo). Só segura quem pode executar checklist (senão a OS ficaria sem saída) e só em contratos com as duas chaves ligadas.
  Painel, app do colaborador e a página da folha em papel **avisam** que há checklist pendente, sem bloquear.
- **Desktop:** o botão Executar do painel abre uma tela larga (`ck.execucao.desktop`), com a mesma lógica da de celular; o QR e o app do colaborador seguem abrindo a de celular
  (`ck.execucao.mobile`). Ambas aceitam `?os=<id>` (registra contra a OS e volta para ela) e `?checklist=<id>` (já abre no modelo escolhido).
- **Impressão:** a folha impressa da OS ("Imprimir folha para o mecânico") traz o checklist em **folhas A4 extras** (20 linhas por folha, título da seção repetido na continuação,
  caixas OK / N/C / N/A, itens críticos marcados, assinatura na última folha), e a impressão do dossiê da OS também. Vale com a chave do contrato ligada, sem exigir a permissão de
  checklist de quem imprime. A folha 1 da OS (que a IA lê) não mudou.
- **Leitura do papel pela IA:** na página "Atualizar com a folha preenchida" da OS há a seção **"Checklist em papel"**: escolher o checklist, fotografar a folha, a IA lê as marcações, a pessoa
  **confere item por item** (itens em dúvida vêm marcados e em branco), e só então **registra**. Checklist de várias folhas: lê uma por vez e as leituras se somam. O registro passa pelas
  mesmas regras da tela (item crítico não conforme reprova, deixa o ativo inapto e abre a OS corretiva), com a **foto da folha como evidência** no lugar da assinatura e da foto por item,
  origem "Papel (IA)" e a nota "lida pela IA e conferida por <nome>". A IA nunca adivinha: marca ilegível, duas caixas ou incerteza viram "dúvida" e ficam em branco.
- **Limites:** a leitura do papel não foi testada com a IA de verdade nem com folhas reais (os testes simulam a resposta da IA); vale validar com algumas folhas preenchidas à mão antes de confiar.

## Fora da C4, por decisão (a fazer só depois de validar a cópia em PROD)
- **Trocar a Frota** para ler os modelos do motor novo (hoje ela usa os modelos dela) e só então pensar em aposentar as tabelas `frota_*`.
- Dar baixa em pendências (hoje elas só ficam registradas e aparecem no relatório).
