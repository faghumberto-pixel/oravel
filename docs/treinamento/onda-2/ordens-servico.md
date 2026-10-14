# Onda 2.1 — Treinamento: Ordens de Serviço

Material de apoio da aula "Ordens de Serviço" da Academia Oravel. Onda 2 = Operação central (2.1 Ordens de Serviço, 2.2 Manutenção Preventiva, 2.3 Checklists/Grupos).

## 1. Conferência da aula contra o sistema (feita em 14/10/2026)

Fonte conferida: `MaintenanceOrderResource`, `Create/EditMaintenanceOrder`, `MaintenanceOrder` (model), `MaintenanceOrderMaterialObserver`, `MaterialConsumptionService`.

| # | O que a aula dizia | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | Aula tinha o texto inteiro duplicado (versão antiga colada embaixo da nova) | — | Removida a duplicata |
| 2 | 4 tipos de operação | 7: Check-in, Check-out, Preventiva, Corretiva, Avaria, Troca, Emergência, cada um com seção própria | Tabela nova + 3 perguntas |
| 3 | Número `OS-AAAAMM-NNNNN` (5 dígitos) | A tela de criação gera `OS-AAAAMM-NNNN` (4 dígitos, reinicia por mês). O formato de 5 dígitos (início em 10000) só nasce do gancho do model, em OS criadas por outros caminhos | Aula e pergunta 1 corrigidas — ver observação abaixo |
| 4 | "Concluir fecha tempo e custo" | Concluir só totaliza tempo, grava fim e fecha a parada do ativo. Custo não é fechado | Corrigido |
| 5 | Não dizia que Concluir não confere nada | Não exige checklist, foto nem assinatura | Aviso na aula + pergunta |
| 6 | Aba Custos ausente | Existe; mão de obra e logística só admin edita; material e total são calculados | Seção nova + 2 perguntas |
| 7 | Campo "Tipo de Serviço" | Rótulo é **Natureza do Serviço** | Corrigido |
| 8 | "Mais Ações" = Reprogramar, Transferir, Cancelar | Também Dossiê Operacional, Preventiva (Celular), Imprimir Preventiva, checklist de Mobilização/Desmobilização e Excluir | Corrigido; pergunta 5 (motivo) corrigida: Transferir também exige |
| 9 | Não citava | Preencher via Foto (IA), Prazo Fatal, Retrabalho, bloqueio por Locação urgente com senha de supervisor, SLA da Emergência, técnicos ordenados por carga, filtros e origem "PMP Automática", cartões da lista | Seções novas + perguntas |

Prova: de 10 para 33 perguntas.

### Observações para decisão (não são da aula)

- **Duas regras de numeração.** `CreateMaintenanceOrder::mutateFormDataBeforeCreate` calcula a sequência pelos 4 últimos dígitos e ordena por texto; o gancho do model usa 5 dígitos a partir de 10000. Se um tenant tiver OS dos dois tipos no mesmo mês, a página pode repetir ou embaralhar a sequência. Vale unificar no model.
- **Status fora do formulário.** O campo Status da OS lista só Aberto, Pendente, Em Andamento, Concluída e Cancelada, mas os botões gravam também `Pausada` e `Reprogramado`.
- **Concluir sem trava.** Decidir se Concluir deve exigir checklist/assinatura (hoje a aula só avisa).
- **Imagens.** As 7 imagens `os-*.jpg` não foram conferidas; faltam prints das novas seções.

## 2. Roteiro de aula (30 a 40 min)

1. Lista de OS: cartões, filtros, marcas (Retrabalho, PMP Automática). 5 min
2. Abrir uma corretiva completa: ativo, técnico (ordem por carga), horímetro, problema, categoria de falha. 8 min
3. Executar: Iniciar, Pausar, Continuar; apontamentos, checklist, fotos antes/depois, materiais (baixa de estoque), assinaturas, Concluir. 12 min
4. Variações: Avaria, Troca, Emergência (SLA), Prazo Fatal, Retrabalho. 8 min
5. Mais Ações: Reprogramar, Transferir, Cancelar; Log de Auditoria. 5 min

## 3. Exercício prático (empresa "Oravel", dados fictícios)

Pré-requisito: um ativo com Grupo de Checklist, um material com estoque e dois técnicos.

**Parte A — corretiva.** Criar OS Corretiva, Categoria de Falha Hidráulico. Anotar o número gerado e qual técnico vem primeiro na lista e por quê.
**Parte B — execução.** Iniciar, pausar, continuar. Lançar 2 unidades de um material; conferir a queda do estoque e o Material (R$) na aba Custos. Concluir. Perguntas: o que a aba Apontamentos mostra em Início e Fim? O ativo continua parado?
**Parte C — emergência.** Abrir uma Emergência num ativo de criticidade urgente. Esperado: SLA sugerido de 60 minutos.
**Parte D — avaria e troca.** Abrir uma OS de Avaria (severidade Grave) e conferir o registro de avaria criado. Abrir uma de Troca com urgência Crítico.
**Parte E — gestão.** Reprogramar uma OS, transferir outra (conferir a notificação do novo técnico), cancelar uma terceira. Abrir o Log de Auditoria e achar as três mudanças.
**Parte F — permissões.** Entrar como técnico: conferir que Mão de Obra e Logística estão bloqueadas.

## 4. Guia rápido (1 página)

**Para abrir:** ativo, tipo, técnico, horímetro e problema relatado são obrigatórios.
**Número:** `OS-AAAAMM-NNNN`, automático.
**Botões:** Iniciar → (Pausar ↔ Continuar) → Concluir. Concluir não confere checklist, fotos nem assinaturas.
**Mais Ações:** Reprogramar, Transferir e Cancelar pedem motivo. Cancele em vez de excluir.
**Material:** baixa estoque e soma no custo. Mão de obra e logística: só admin.
**Reservado por Locação urgente:** criar OS pede a senha do Supervisor.
**Foto (IA):** só ajuda a preencher; revise antes de salvar.

## 5. Capturas e vídeos a produzir

Capturas: lista com cartões e filtros; criação com seção Corretiva (Categoria de Falha); seção Emergência (SLA); aviso de ativo reservado; botões da OS em andamento; aba Custos como admin e como técnico; Mais Ações aberto; Log de Auditoria.
Vídeos (3 a 5 min): (1) corretiva do começo ao fim; (2) emergência e SLA; (3) reprogramar, transferir e cancelar.
