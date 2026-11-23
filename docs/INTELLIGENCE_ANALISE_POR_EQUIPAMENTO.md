# Oravel Intelligence — Análise por equipamento (desenho)

Status: desenho aprovado em 22/11/2026. **F1 implementada** na branch `feat/intelligence-equipamentos-f1` (dados, importação, indicadores, classificação e modelo de planilha; sem telas), ainda sem deploy. **F2 implementada** na mesma branch (telas Equipamentos e Equipamento, Pareto, bolhas valor × receita, tabela com busca/filtros/Excel, relatório para WhatsApp e impressão, frota de exemplo na empresa demo). **F3 implementada** (achados com nomes no Diagnóstico, ações nomeadas no Plano, equipamentos que mudaram de situação na Comparação interna, linhas por equipamento nos relatórios e as duas tabelas liberadas ao Assistente de IA). **F4 implementada** (botão "Exportar para o Intelligence" na tela de Ativos do ERP, em `feat/intelligence-exportar-do-erp`). F5 (site): não iniciada. Escrito para fechar o que o Intelligence ainda **não** entrega: hoje ele analisa a empresa como um todo; este desenho acrescenta o nível do equipamento.

### Decisões da F1 (onde o código se afastou ou detalhou o desenho)
- Receita, custo, valor e OS do mês da aba geram os campos da empresa; **locados e indisponíveis** são o equivalente médio no mês (soma dos dias ÷ dias do mês), sempre limitados ao total.
- Equipamento **sem nenhum dado avaliável** recebe a classe `sem_dados` (não estava no desenho).
- A classe `risco_parada` exige ao menos 1 corretiva; sem mês anterior vale só a indisponibilidade acima de 25%.
- Reincidência só vira sinal com pelo menos 2 corretivas no mês (1 de 1 seria "100%": ruído).
- Três indicadores novos da empresa (reincidência, subutilizados e valor em trocar/vender) entram só quando a aba existe; as metas deles são referência inicial, como as demais.
- A aba "Equipamentos" só é lida se o contrato tiver a chave `tabela_intelligence_equipamentos`; sem ela é ignorada com aviso.

## 1. O que muda para o gestor

Hoje: "Sua disponibilidade subiu de 78% para 91%." Depois desta fase, o Intelligence também diz **quais equipamentos** explicam isso e **o que fazer com cada grupo**:

| Pergunta | O que passa a responder (exemplos ilustrativos) |
|---|---|
| Minha frota está saudável? | "12 equipamentos têm utilização abaixo de 40% e estão disponíveis: potencial de oferta ou venda." |
| Onde estou perdendo dinheiro? | "Estes 9 equipamentos representam 18% do valor investido e 4% da receita." |
| Minha manutenção está eficiente? | "32% das suas corretivas são reincidência; 5 equipamentos respondem por 60% delas." |
| O que devo vender ou reformar? | "Considerar trocar ou vender: patrimônios A, B e C (custo acumulado acima de 60% do valor)." |
| Por que a disponibilidade caiu? | "14 equipamentos concentram 61% dos dias parados no mês." |

Todas as frases são calculadas dos dados enviados, com a mesma estrutura de hoje: **o que está acontecendo / por que importa / o que fazer**. O "por que" passa a ser causal de verdade (quais equipamentos), não só o impacto do indicador.

## 2. Princípios (herdados do que já foi decidido)

1. **Snapshot imutável.** Os equipamentos fazem parte do snapshot do mês: nada é sobrescrito, o histórico de cada equipamento se forma sozinho.
2. **Uma importação = um snapshot.** A planilha atual ganha uma **segunda aba "Equipamentos"** (opcional). Quem não tiver os dados continua enviando só a aba "Dados".
3. **Poucos campos.** Só o que alimenta as perguntas acima (seção 3).
4. **Mesmas regras do ERP.** Limiares de "revisar / trocar ou vender" (40% e 60% do valor), conceito de retrabalho e dias locado vêm do que o ERP já usa (`config/custo_manutencao.php`, `CustoManutencaoService`). Cliente com os dois produtos não vê números diferentes.
5. **Sem redundância.** Para quem tem o ERP, a planilha de equipamentos é **gerada pelo ERP** (seção 8); o Intelligence não cria um segundo cadastro de ativos.
6. **Isolamento por empresa**, como o resto: `tenant_id`, `BelongsToTenant`, `HasSaaSMetadata`, Policy própria.

## 3. Entrada de dados: aba "Equipamentos" (uma linha por equipamento)

Período dos números = o mês da "Data de Referência" da aba "Dados".

| Coluna | Obrig. | Para quê |
|---|---|---|
| Nº Patrimônio | sim | Identifica o equipamento ao longo dos meses |
| Nome / Modelo | sim | Exibição |
| Categoria | não | Agrupar e, no futuro, comparar com parecidas |
| Ano de fabricação | não | Idade |
| Valor do equipamento (R$) | não* | Base de retorno e da regra de trocar/vender |
| Receita no mês (R$) | não* | Receita por ativo |
| Custo de manutenção no mês (R$) | não* | Custo por ativo |
| Custo de manutenção acumulado (R$) | não* | Razão custo ÷ valor (regra do ERP) |
| OS corretivas no mês | não | Frequência de quebra |
| OS preventivas no mês | não | Cobertura de preventiva |
| Corretivas reincidentes no mês | não | Reincidência (o mesmo defeito voltando) |
| Dias locado no mês | não | Utilização |
| Dias indisponível no mês | não | Disponibilidade |

\* Sem o campo, o indicador que depende dele simplesmente não é calculado (regra atual: nunca inventar).

**Efeito colateral útil:** quando a aba "Equipamentos" existe, vários campos da aba "Dados" passam a ser **calculados** (total de equipamentos, locados, indisponíveis, valor da frota, receita, custo de manutenção, OS corretivas e preventivas). O cliente preenche menos, não mais. A tela mostra "calculado a partir dos equipamentos".

**Validações:** patrimônio único; dias locado + indisponível ≤ dias do mês; reincidentes ≤ corretivas; valores não negativos; aviso (não bloqueio) se a soma dos equipamentos diverge dos totais digitados na aba "Dados"; limite de 5.000 linhas por envio (mesmo padrão do importador de clientes).

## 4. Modelo de dados

Tabelas novas (todas com `tenant_id`; imutáveis como os snapshots; criadas por migration):

- `intelligence_equipamentos` — uma linha por equipamento por snapshot: `snapshot_id`, `patrimonio`, `nome`, `categoria`, `ano_fabricacao`, `valor`, `receita`, `custo_mes`, `custo_acumulado`, `os_corretivas`, `os_preventivas`, `reincidencias`, `dias_locado`, `dias_indisponivel`. `unique(snapshot_id, patrimonio)`.
- `intelligence_equipamento_analises` — o resultado calculado, **gravado** (não recalculado na leitura, para o histórico não mudar quando uma regra mudar): `utilizacao_pct`, `disponibilidade_pct`, `retorno_anual_pct`, `razao_custo_valor`, `custo_sobre_receita_pct`, `reincidencia_pct`, `idade_anos`, `classificacao`, `motivo` (texto), `versao_regras`.

Registro SaaS (obrigatório para todo módulo novo): chave `tabela_intelligence_equipamentos`, slug de permissão, Policy própria, entrada em `ContratoResource::gruposPadrao()` e tela com chave `menu_intelligence_equipamentos`; `php artisan tenant:audit-saas-metadata` sem lacunas. A migration de backfill liga a tela **só** nos contratos que já têm o Intelligence (mesmo padrão da `2027_01_09`).

## 5. Indicadores por equipamento (definições)

- **Utilização** = dias locado ÷ dias do mês.
- **Disponibilidade** = 1 − dias indisponível ÷ dias do mês.
- **Retorno anualizado sobre o valor** = (receita − custo do mês) × (365 ÷ dias do mês) ÷ valor.
- **Custo ÷ valor (acumulado)** = custo acumulado ÷ valor. Mesma razão do ERP.
- **Custo ÷ receita** = custo do mês ÷ receita do mês.
- **Reincidência** = reincidentes ÷ corretivas do mês.
- **Idade** = ano da referência − ano de fabricação.

Indicadores da empresa que passam a existir (somando os equipamentos): participação dos N piores no custo (Pareto), participação no valor investido contra participação na receita, % de corretivas reincidentes, equipamentos com utilização baixa.

## 6. Classificação (transparente e ajustável)

Cada equipamento recebe **uma** classificação, na ordem abaixo, com o **motivo escrito** (por que caiu ali):

1. **Considerar trocar ou vender** — custo ÷ valor ≥ 60% (regra do ERP), ou retorno negativo por 2 meses seguidos com utilização < 40%.
2. **Revisar** — custo ÷ valor ≥ 40%, ou reincidência ≥ 30%, ou custo ÷ receita ≥ 30%.
3. **Subutilizado** — utilização < 40% com disponibilidade ≥ 80% (bom equipamento parado sem contrato: ofertar ou vender).
4. **Em risco de parada** — indisponibilidade alta (> 25%) e corretivas subindo contra o mês anterior.
5. **Saudável** — nenhum dos anteriores.

Os limiares ficam em `config/intelligence.php` (os de 40% e 60% apontam para os do ERP). **São referências iniciais, como as metas atuais:** precisam de revisão de quem conhece locação antes de um cliente real ver "venda este equipamento". O motivo sempre mostra o número que disparou a regra.

Como o histórico é por patrimônio, a classificação ganha **tendência** (piorando, estável, melhorando) comparando com o snapshot anterior.

## 7. Telas e menus

Novo menu **Equipamentos** no grupo Intelligence (e a análise entra nas telas que já existem):

- **Equipamentos** (nova): cartões (quantos em cada classificação; valor imobilizado nos "trocar/vender"), gráfico de **Pareto** (barras do custo por equipamento + linha acumulada), **bolhas valor × receita** (tamanho = custo; fora da diagonal = problema), tabela filtrável por categoria e classificação, com busca e exportação em Excel.
- **Equipamento** (detalhe): linha do tempo mês a mês (utilização, receita, custo, reincidência), classificação com o motivo, ações sugeridas.
- **Diagnóstico**: novos pontos de atenção do tipo "5 equipamentos concentram 60% das reincidências".
- **Plano de evolução**: ações com nome, por exemplo "avaliar venda ou reforma dos patrimônios A, B e C" dentro do prazo de 30/60/90 dias.
- **Comparação interna**: equipamentos que **mudaram de classificação** entre as duas datas.
- **Assistente de IA**: libera as duas tabelas novas na lista que ele enxerga (continua só consulta e só da própria empresa) e passa a responder "quais equipamentos…".
- **Relatório (WhatsApp/impressão)**: novo tipo "equipamentos", mesma página pública mínima, para levar a lista de revisão para a reunião.

Wireframe da tela Equipamentos:

```
 Equipamentos                                              [WhatsApp] [Imprimir] [Excel]
 ┌ Trocar/vender ┐ ┌ Revisar ┐ ┌ Subutilizados ┐ ┌ Em risco ┐ ┌ Saudáveis ┐
 │   3  R$1,2 mi │ │   9     │ │      12       │ │    4     │ │    92     │
 └───────────────┘ └─────────┘ └───────────────┘ └──────────┘ └───────────┘
 ┌ Pareto do custo ─────────────────┐ ┌ Valor × receita (bolha = custo) ──┐
 │ ▇▆▅▄▃▂▂▁▁  ──── 61% nos 14 piores│ │      ○                 ●          │
 └──────────────────────────────────┘ └───────────────────────────────────┘
 Busca [____]  Categoria [Todas ▾]  Situação [Todas ▾]
 Patrimônio  Equipamento        Utiliz. Retorno  Custo/valor  Reinc.  Situação
 EMP-0042    Empilhadeira 2,5t   28%    -4%       67% ▲        3/5    ● Trocar/vender
 GER-0007    Gerador 150 kVA     35%    +2%       44%          1/4    ● Revisar
```

## 8. Como os dados chegam

1. **v1 — planilha**: modelo com as duas abas (geração do modelo e importador estendidos, mesmo padrão `AssetImportColumns`/`Catalogo`). Serve a quem não usa o ERP.
2. **v1.5 — "Exportar para o Intelligence" no ERP** (implementado; receita pela medição aprovada do contrato no mês, senão mensal fixo proporcional aos dias ou diária × dias, e em branco para contrato por hora ou franquia sem medição): botão na tela de ativos que gera a planilha de Equipamentos já preenchida (custo, retrabalho e dias locado vêm do `CustoManutencaoService` e dos contratos). Zero digitação para quem tem os dois produtos. Mesmo contrato/permissão de hoje.
3. **v2 — integração automática** (opcional, por contrato): o ERP envia o snapshot do mês sozinho. Só depois de v1.5 estar em uso.

## 9. Fases (tamanho relativo, sem datas)

| Fase | Entrega | Tamanho |
|---|---|---|
| F1 | Modelo de dados, importação da aba "Equipamentos", indicadores, classificação, testes | G |
| F2 | Telas Equipamentos e Equipamento, gráficos, exportação em Excel | G |
| F3 | Integração com Diagnóstico, Plano, Comparação, Assistente de IA e relatório | M |
| F4 | Exportador do ERP (v1.5) | M |
| F5 | Atualizar o site: a seção "Perguntas que o Intelligence responde" e o aviso "não lista equipamentos individuais" **só mudam depois da F2/F3 estarem no ar** | P |

## 10. Riscos e decisões em aberto

1. **Qualidade do dado do cliente.** Sem patrimônio padronizado, o histórico por equipamento quebra. Mitigação: patrimônio obrigatório, aviso de patrimônio novo/sumido entre meses, normalização (sem espaços/caixa).
2. **Definição de reincidência.** O ERP usa a marcação `is_rework` da OS. Para quem não usa o ERP, o cliente informa o número. Decidir se mantemos "o mesmo defeito em até N dias" como regra oficial e a escrevemos na aba de instruções.
3. **Valor do equipamento.** Aquisição, contábil ou de mercado? O ERP usa o de aquisição para a razão custo ÷ valor; sugerido manter, e documentar.
4. **Limiares.** Os de 40% e 60% já existem no ERP; os demais (40% de utilização, 30% de reincidência, etc.) são palpites meus a validar com especialista.
5. **Volume e desempenho.** Milhares de linhas por mês: importar em fila, calcular em lote, paginar tabela e Pareto no servidor.
6. **Apresentação ao cliente.** "Considerar vender" é uma recomendação forte: a tela precisa mostrar o motivo e o número, e dizer que é referência, não decisão.
7. **Site e promessas.** Nada do que está neste documento pode aparecer no site antes de entrar no ar.

## 11. O que preciso de você para começar a F1

1. Aprovar as colunas da seção 3 (dá para cortar mais).
2. Confirmar o valor de referência do equipamento (aquisição) e a definição de reincidência.
3. Dizer se a F4 (exportar do ERP) deve vir logo depois da F1, antes das telas, para ter dado real cedo.
4. Indicar quem valida os limiares da classificação.
