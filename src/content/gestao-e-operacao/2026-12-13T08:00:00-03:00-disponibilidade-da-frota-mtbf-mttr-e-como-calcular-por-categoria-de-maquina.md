---
image: "/assets/img/disponibilidade-da-frota-mtbf-mttr-e-como-calcular-por-categoria-de-maquina.jpg"
title: "Disponibilidade da frota: MTBF, MTTR e como calcular por categoria de máquina"
date: 2026-12-13T08:00:00-03:00
theme: "gestao-e-operacao"
summary: "Utilização mede quanto a máquina trabalhou; disponibilidade mede quanto ela estava pronta para trabalhar. Fórmulas de MTBF, MTTR e disponibilidade, com um exemplo numérico e um passo a passo para a oficina da locadora."
source: "Tractian (indicadores de manutenção, MTTR, MTBF e disponibilidade)"
readingTime: "5 min"
---

Uma máquina com utilização baixa pode estar parada por falta de cliente ou por falta de condição de sair. O primeiro caso é comercial; o segundo é de oficina. Para separar os dois, a locadora precisa de um indicador de **disponibilidade**: quanto do tempo a máquina estava pronta para ser alugada.

Os conceitos abaixo vêm de material de gestão de manutenção industrial, principalmente da [Tractian](https://tractian.com/blog/indicadores-de-manutencao). As fontes não tratam de frota de locação especificamente; a adaptação por categoria é uma sugestão deste texto.

## Três indicadores, três perguntas

| Indicador | Pergunta | Fórmula (segundo a Tractian) |
|-----------|----------|------------------------------|
| **MTBF** (tempo médio entre falhas) | De quanto em quanto tempo a máquina falha? | Tempo total de operação ÷ número de falhas |
| **MTTR** (tempo médio de reparo) | Quanto tempo leva para consertar? | Tempo total de reparo ÷ número de reparos |
| **Disponibilidade** | Que parte do tempo ela está pronta? | MTBF ÷ (MTBF + MTTR) |

A Tractian descreve ainda a disponibilidade física como a medida mais básica, calculada como (tempo total − tempo parado) ÷ tempo total. Como a descrição em português tem a fórmula escrita de forma confusa, confira a definição original antes de usá-la em relatório.

## Exemplo numérico

A [Tractian](https://tractian.com/blog/indicador-de-manutencao-mttr-mean-time-to-repair) dá um exemplo de MTTR: 60 horas de reparo distribuídas em 12 intervenções corretivas no mês resultam em MTTR de 5 horas. A página alerta para a definição de tempo de reparo: o cálculo correto inclui todo o intervalo entre a parada do ativo e o retorno em condição operacional.

Para a disponibilidade, o [glossário da Tractian](https://tractian.com/glossario/mean-time-to-repair) traz este exemplo, com números de ilustração (**não são dados de frota**):

- MTBF de 500 h e MTTR de 5 h: disponibilidade de cerca de 99,0%.
- Se o MTTR cair para 2 h, a disponibilidade sobe para cerca de 99,6%.

O exemplo sugere um ponto prático: **reduzir o tempo de reparo costuma ser mais barato do que aumentar o tempo entre falhas**. Para a locadora, é a diferença entre ter peça e técnico disponíveis ou esperar a compra. Veja [Estoque de peças da oficina](/gestao-e-operacao/2026-11-28T08:00:00-03:00-estoque-de-pecas-da-oficina-curva-abc-e-criticidade-na-locadora/).

## Como adaptar para a locadora

A locadora pode calcular, por categoria de máquina (retroescavadeira, plataforma, gerador):

1. **Horas de operação:** horímetro ou dias alugados do período.
2. **Número de falhas:** chamados de quebra em campo ou na oficina. Defina o que conta como falha (parou de trabalhar, não apenas manutenção programada).
3. **Tempo de reparo:** da abertura da ordem de serviço até a máquina liberada, em horas.
4. **MTBF e MTTR** por categoria, com os valores acumulados de 6 a 12 meses.
5. **Disponibilidade** pela fórmula acima.

Para itens que se trocam em vez de consertar (consumíveis, por exemplo), o MTTR perde sentido. Nesses casos, acompanhe a vida útil do item em vez do tempo de reparo.

## Disponibilidade e utilização juntas

| Cenário | Disponibilidade | Utilização | Leitura |
|---------|-----------------|------------|---------|
| A | Alta | Alta | Frota bem aproveitada |
| B | Alta | Baixa | Problema comercial: preço, carteira, oferta |
| C | Baixa | Alta | Risco: demanda ocupa máquina que falha. Veja [Quando a máquina quebra na obra](/gestao-e-operacao/2026-12-09T08:00:00-03:00-quando-a-maquina-quebra-na-obra-clausula-de-substituicao-e-prazos-de-atendimento/) |
| D | Baixa | Baixa | Problema de oficina e de comercial; avaliar venda ([quando vender](/gestao-e-operacao/2026-11-18T08:00:00-03:00-quando-vender-equipamento-criterios-idade-custo-utilizacao/)) |

A utilização está detalhada em [Utilização: o indicador que mais importa](/tecnologia/2026-11-06T14:05:00-03:00-utilizacao-o-indicador-que-mais-importa/).

## O que olhar quando a disponibilidade cai

- **MTTR alto:** falta de peça, falta de técnico, fila na oficina. A [preventiva](/gestao-e-operacao/2026-11-09T14:00:00-03:00-preventiva-ou-corretiva-qual-compensa/) costuma reduzir falhas, mas depende de planejamento.
- **MTBF baixo:** uso intenso, manutenção atrasada, máquina velha, mau uso do cliente. Os registros de [vistoria](/gestao-e-operacao/2026-11-19T08:00:00-03:00-checklist-vistoria-entrega-devolucao-evitar-disputa/) ajudam a separar.
- **Concentração:** poucas máquinas respondem por boa parte das falhas. A curva ABC de falhas mostra quais.

## Como aplicar na sua locadora

1. Escolha as três categorias com mais receita e levante os chamados de quebra dos últimos 6 meses.
2. Calcule MTTR e MTBF de cada categoria, com a planilha da oficina.
3. Calcule a disponibilidade e compare com a utilização, usando a tabela de quatro cenários.
4. Defina uma meta de MTTR para a categoria pior colocada e identifique o principal atraso (peça, técnico ou fila).
5. Repita o cálculo todo trimestre e acrescente ao [painel mensal](/comunidade/2026-11-18T09:10:00-03:00-painel-mensal-da-locadora-oito-indicadores-e-formulas-modelo-para-copiar/).

## Fontes

- [Tractian: indicadores de manutenção](https://tractian.com/blog/indicadores-de-manutencao)
- [Tractian: MTTR (tempo médio de reparo)](https://tractian.com/blog/indicador-de-manutencao-mttr-mean-time-to-repair)
- [Tractian: disponibilidade, definição, cálculo e importância](https://tractian.com/blog/o-que-e-disponibilidade-definicao-calculo-e-importancia-na-manutencao-industrial)
- [Tractian: glossário, disponibilidade como métrica de manutenção](https://tractian.com/glossario/availability-maintenance-metric)
- [Tractian: glossário, tempo médio para reparo (MTTR) e disponibilidade](https://tractian.com/glossario/mean-time-to-repair)
