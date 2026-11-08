# Checklists por grupo de equipamento

Checklists padrão por tipo de equipamento (gerador, compressor, plataforma elevatória, guindaste, munk, veículo leve, caminhão e
empilhadeira), editáveis por empresa. Entrega em fases; cada uma para e espera OK antes da seguinte.

## Decisões (08/11/2026)
- **Tabelas novas com prefixo `ck_`.** O nome `checklist_templates` já é do módulo "Checklists" antigo (id numérico, 23 registros em PROD,
  menu Manutenção › Cadastros). Ele **não é tocado**; na C2 o menu aponta para o novo e o antigo é aposentado depois, com os dados migrados.
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

## Próximas fases
- **C2:** CRUD Filament (repeater de seções e itens, duplicar, restaurar padrão, ativar/desativar).
- **C3:** execução mobile por QR (câmera, voz, assinatura) e regras do item crítico (aptidão, OS corretiva, downtime).
- **C4:** histórico, relatório e exportação (OpenSpout) e migração da Frota.
