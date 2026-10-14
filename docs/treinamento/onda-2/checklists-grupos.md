# Onda 2.3 — Treinamento: Grupos de Checklist e Checklist de Inspeção

Material de apoio das aulas "Grupos de Checklist" e "Checklist de Inspeção" e do guia "Configurar preventiva". Fecha a Onda 2 (Operação central).

## 1. Conferência contra o sistema (feita em 14/10/2026)

Fonte conferida: `ChecklistGroupResource` (+ `TemplateItemsRelationManager`), `ChecklistGroup`, `MaintenanceOrderChecklist`, `MaintenanceOrderChecklistSnapshotObserver`, `AssetResource\ChecklistItemsRelationManager`, `HandlesChecklistItems`, `MaintenanceChecklistMobile`, `MaintenanceOrderFieldWizard` (etapa 2), `AssetCategoryResource`, `ChecklistTemplateResource`, aba PMP da OS.

| # | O que as aulas diziam | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | As duas aulas tinham o texto duplicado (versão antiga colada embaixo) | — | Removida a duplicata |
| 2 | Grupo "define o checklist e o plano de preventiva" | O grupo guarda o checklist básico; os planos são cadastrados em PMP → Planos Preventivos escolhendo o grupo | Reescrito |
| 3 | Botões "Criar checklist group" / "Criar asset category" | Rótulo é **Criar** | Corrigido |
| 4 | Categoria do Ativo "é só texto descritivo" (aula de Grupos) | A categoria define as abas técnicas do cadastro (achado já corrigido na aula de Ativos, Onda 1.1) | Corrigido |
| 5 | Itens extras do ativo só citados de passagem | Aba "Itens Extras do Manual (deste ativo)" no ativo, somada ao checklist na criação da OS | Seção nova + 2 perguntas |
| 6 | Não dizia quando a cópia acontece | Na criação da OS, para todo tipo de OS; ativo sem grupo recebe só os extras | Seção nova + 3 perguntas |
| 7 | "Pelo celular: Finalizar libera em 100%" | Isso é da tela simples "checklist digital", que **não tem link em nenhum lugar** (só abre pelo endereço). O fluxo real é o Modo Campo, etapa 2, com ✓ ✗ N/A | Seção nova + 4 perguntas |
| 8 | Foto e observação "opcionais" | Opcionais, **exceto Não Conforme no Modo Campo, que exige as duas** | Corrigido (pergunta antiga trocada) |
| 9 | Não citava a aba PMP | Planos vencidos ou que vencem no mês viram itens `pmp` na criação da OS | Seção nova + 2 perguntas |
| 10 | Não citava Importar Template PMP no checklist | Também copia itens de checklist por família | Seção nova + pergunta |
| 11 | Guia de preventiva: "contando pelas horas", caminho "Manutenção → Planos Preventivos" | Horas, dias ou ciclos; menu é **PMP** → Planos Preventivos; opções crítico e OS automática | Guia reescrito + 2 perguntas |

Provas: Grupos de 8 para 18; Checklist de Inspeção de 8 para 18; guia de 7 para 9.

### Achados no sistema (não corrigidos — decisão sua)

1. **Item Não Conforme trava a barra de progresso.** `is_completed` só é verdadeiro para Conforme e N/A; na tela simples, "Finalizar" só libera com 100%, então uma OS com defeito nunca finaliza ali. No Modo Campo a etapa 2 não bloqueia o avanço, mas o progresso mostrado nunca chega a 100%.
2. **Não Conforme não gera nada.** Não abre avaria nem pendência; depende do técnico registrar. Vale decidir se deve oferecer um atalho "registrar avaria deste item".
3. **Itens PMP aparecem duas vezes na OS.** O repetidor da aba "Vistoria / Checklist" usa a relação `checklists` sem filtrar o tipo, então os itens `pmp` também aparecem ali, além da aba PMP.
4. **Tela "checklist digital" órfã** (`MaintenanceChecklistMobile`, rota `maintenance-orders.checklist-mobile`): sem link, sem os três status e com regra diferente da do Modo Campo. Remover ou religar.
5. **Imagens:** `grupos-lista.jpg`, `grupo-edit.jpg`, `categorias.jpg`, `os-checklist.jpg` não foram conferidas; faltam prints da aba Itens Extras do manual e da aba PMP da OS.

## 2. Roteiro de aula (30 a 40 min)

1. Por que grupos: tipo de equipamento, checklist e preventiva num só lugar. 5 min
2. Criar o grupo e o checklist básico (Item, Categoria/Seção, Instruções). 8 min
3. Vincular ativos e acrescentar itens extras do manual a um ativo. 6 min
4. Abrir uma OS e ver a cópia; editar o modelo e provar que a OS antiga não muda. 8 min
5. Preencher no Modo Campo: Conforme, Não Conforme (observação + foto), N/A. 8 min
6. Aba PMP e a relação com os planos preventivos. 5 min

## 3. Exercício prático (empresa "Oravel", dados fictícios)

**Parte A — grupo.** Criar "Compressores de Ar" com o objetivo "Evitar falhas de partida e superaquecimento" e 5 itens, em 2 seções (Motor e Pneumática).
**Parte B — ativos.** Vincular 2 compressores ao grupo. Ao segundo, acrescentar 1 item extra do manual.
**Parte C — cópia.** Abrir uma OS Corretiva no segundo ativo. Esperado: 6 itens. Abrir outra no primeiro: 5 itens.
**Parte D — modelo × cópia.** Apagar 1 item do grupo e abrir nova OS: 4 itens (+ extras). Conferir que a OS da parte C continua com todos.
**Parte E — Modo Campo.** Marcar um item Não Conforme sem foto (esperado: recusa); anexar foto e observação e repetir. Marcar um N/A. Conferir o progresso.
**Parte F — PMP.** Criar um plano de grupo vencido, abrir uma OS e achar o item na aba PMP.
**Parte G — pergunta final.** Concluir a OS com itens em branco. O sistema deixa? (Sim.) O que a equipe precisa fazer?

## 4. Guia rápido (1 página)

**Grupo:** Nome + Objetivo; itens em "Checklist Básico do Grupo" (ao editar).
**Cópia:** na **criação** da OS, qualquer tipo; grupo + itens extras do ativo (+ itens PMP vencidos ou vencendo no mês). Nunca muda OS antiga.
**Status:** Conforme e N/A = concluído; Não Conforme = não.
**Modo Campo:** Não Conforme exige observação e foto.
**Não existe:** bloqueio de conclusão por checklist, nem abertura automática de avaria.
**Não confundir:** Categoria do Ativo (abas técnicas) × Grupo (checklist e planos); cadastro "Checklists" solto.

## 5. Capturas e vídeos a produzir

Capturas: lista de Grupos; edição do grupo com Checklist Básico; aba Itens Extras do Manual no ativo; aba Vistoria/Checklist da OS; Modo Campo etapa 2 com Não Conforme e a recusa; aba PMP da OS; Importar Template PMP.
Vídeos (3 a 5 min): (1) montar um grupo e vincular ativos; (2) da OS ao checklist no celular; (3) modelo × cópia.
