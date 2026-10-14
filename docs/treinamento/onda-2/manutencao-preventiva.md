# Onda 2.2 — Treinamento: Manutenção Preventiva (PMP)

Material de apoio da aula "Manutenção Preventiva". Onda 2 = Operação central.

## 1. Conferência da aula contra o sistema (feita em 14/10/2026)

Fonte conferida: `MaintenancePlanResource` (+ `PlanStatus`, stats, lista), `MaintenancePlan`, `PreventiveMaintenanceExecution` (+ Resource), `PreventiveMaintenanceMobile`, `CheckMaintenanceDueAlerts`, páginas do grupo PMP.

| # | O que a aula dizia | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | Dispara só por horas | Horas, dias e ciclos de bateria; vence pelo que chegar primeiro | Corrigido + 2 perguntas |
| 2 | Botão "Criar maintenance plan" | Botão é **Criar** (rótulo em inglês antigo na legenda); também Importar Template PMP, Imprimir, Exportar | Corrigido |
| 3 | Não citava a OS automática | Opção "Gerar O.S. automaticamente quando vencer" (padrão desligada) abre OS com origem PMP Automática | Seção nova + 3 perguntas |
| 4 | Não citava o bloqueio | Item crítico vencido muda o ativo para Manutenção (não pode ser locado) e reverte sozinho | Seção nova + pergunta |
| 5 | Não citava o alerta | Verificação diária, aviso a quem é responsável pelo evento, sem repetir por 7 dias | Seção nova + 2 perguntas |
| 6 | Não citava Importar Template PMP | Cópia pontual do catálogo por família de equipamento para um Grupo | Seção nova + pergunta |
| 7 | "Cada ativo mantém seu histórico" só no texto repetido | Correto; faltava a regra de nome igual (plano do ativo vence o do grupo) e a coluna Origem | Seção nova + 2 perguntas |
| 8 | A Vencer sem definição | Previsão cai no mês corrente (horas pela média diária de uso) | Explicado + pergunta |
| 9 | Texto duplicado (versão antiga colada embaixo) | — | Removida a duplicata |
| 10 | Não citava as demais telas do grupo PMP | Dashboard PMP (5 colunas), Cobertura de PMP, Consulta por Cliente, Alocação de Técnicos, Kanban Preventivas | Tabela nova + 2 perguntas |
| 11 | Legenda da imagem do Kanban "Análise de Preventivas" | Item de menu é **Kanban Preventivas** | Corrigido |

Prova: de 9 para 24 perguntas.

### Achados no sistema (não corrigidos — decisão sua)

1. **Plano só de ativo não aparece para marcar.** A tela do celular e o formulário de execução manual listam apenas planos de Grupo (`checklist_group_id`). Plano individual entra nos alertas e na tela de planos, mas o técnico não consegue registrar a execução. A aula avisa o limite; o certo é corrigir.
2. **Previsão da próxima execução ignora dias e ciclos.** `next_due_horimetro = horímetro + interval_hours`; com plano só por dias, `interval_hours` é nulo e a "próxima previsão" fica igual ao horímetro executado. Também há risco de erro no formulário manual (`number_format` com intervalo nulo).
3. **Plano desativado continua alertando.** A verificação diária (`CheckMaintenanceDueAlerts`) não filtra `is_active`; só os cartões e a tela do celular filtram. Um plano desligado ainda avisa, abre OS automática e pode bloquear ativo crítico.
4. **Imagens:** `planos-lista.jpg`, `planos-novo.jpg`, `m-preventiva.jpg` e `preventiva-kanban.jpg` não foram conferidas; faltam prints do Importar Template, da Cobertura de PMP e do Dashboard PMP.
5. **Guias relacionados** (`guias__configurar-preventiva`) repetem o conteúdo antigo ("só por horas"); revisar na 2.3 ou antes.

## 2. Roteiro de aula (35 a 45 min)

1. Conceito: por que horas, dias e ciclos; o cálculo do vencimento. 8 min
2. Criar um plano por Ativo e um por Grupo; nome igual no ativo. 8 min
3. Importar Template PMP para um Grupo. 5 min
4. Executar pelo celular a partir de uma OS: horímetro, marcar, observação e foto. 10 min
5. Alerta diário, OS automática e bloqueio por item crítico. 8 min
6. Telas do grupo PMP: Dashboard, Cobertura, Consulta por Cliente, Kanban. 6 min

## 3. Exercício prático (empresa "Oravel", dados fictícios)

Pré-requisito: um Grupo de Checklist "Compressor" com 2 ativos (horímetros 1.000 h e 1.200 h) e uma OS Preventiva aberta no primeiro.

**Parte A — plano por Grupo.** Criar "Troca de óleo", 250 h, grupo Compressor. Qual o status do plano nos cartões e por quê? (Ninguém executou: a contagem parte de zero, então ambos já estão vencidos.)
**Parte B — execução.** Na OS do primeiro ativo, abrir Preventiva (Celular), marcar "Troca de óleo" com horímetro 1.010. Esperado: previsão 1.260 h; o horímetro do ativo sobe para 1.010 se o atual for menor. Desmarcar e conferir que o registro some.
**Parte C — dias.** Criar plano "Inspeção" só com 30 dias. Conferir que a previsão de horas não aparece.
**Parte D — nome igual.** Criar, só para o segundo ativo, um plano "Troca de óleo" de 500 h. Esperado: só o do ativo vale para ele.
**Parte E — crítico.** Marcar um item como crítico vencido, rodar `php artisan maintenance:check-due-alerts` em base de teste e conferir que o ativo foi para Manutenção. Executar o item e rodar de novo: o ativo volta.
**Parte F — OS automática.** Ligar "Gerar O.S. automaticamente" e conferir a OS com origem "PMP Automática".

## 4. Guia rápido (1 página)

**Vence pelo que chegar primeiro:** horas, dias ou ciclos de bateria.
**Ativo ou Grupo:** nunca os dois; grupo = histórico separado por ativo; mesmo nome no ativo vence o do grupo.
**Template:** Importar Template PMP copia itens por família; não duplica.
**Diário:** aviso a quem é responsável (7 dias sem repetir); OS só nasce sozinha se a opção estiver ligada; item crítico vencido põe o ativo em Manutenção.
**Celular:** a partir da OS; só planos de Grupo; informe o horímetro; marcar grava, desmarcar apaga.
**Telas:** Planos, Dashboard, Cobertura, Consulta por Cliente, Alocação, Kanban.

## 5. Capturas e vídeos a produzir

Capturas: lista de planos com cartões; formulário do plano (3 intervalos e as opções); Importar Template PMP; tela do celular com progresso; Cobertura de PMP; Dashboard PMP; aviso de ativo bloqueado.
Vídeos (3 a 5 min): (1) criar plano e executar no celular; (2) importar template; (3) alerta, OS automática e bloqueio.
