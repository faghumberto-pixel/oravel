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
  Só entram ativos (não arquivados) do tenant. A tela da OS usa isso na C3.

## Próximas fases
- **C3:** execução mobile por QR (câmera, voz, assinatura) e regras do item crítico (aptidão, OS corretiva, downtime).
- **C4:** histórico, relatório e exportação (OpenSpout) e migração da Frota.
