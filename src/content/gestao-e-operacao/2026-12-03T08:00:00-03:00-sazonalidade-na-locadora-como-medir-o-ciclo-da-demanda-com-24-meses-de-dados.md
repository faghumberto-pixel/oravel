---
title: "Sazonalidade na locadora: como medir o ciclo da demanda com 24 meses de dados"
date: 2026-12-03T08:00:00-03:00
theme: "gestao-e-operacao"
summary: "Chuva, férias e ciclos de obra mudam a demanda ao longo do ano, mas o padrão varia por região e por categoria de máquina. Um método simples para medir o ciclo da própria locadora e planejar manutenção, compra e preço."
source: "Climatempo (chuvas de verão e obras); método próprio com planilha"
readingTime: "5 min"
---

Todo locador sabe que há meses mais fracos e mais fortes. O que muda a decisão é saber **quanto** e **em quais categorias**. Sem número, a sazonalidade vira desculpa para um mês ruim. Com número, vira planejamento: quando concentrar manutenção preventiva, quando negociar compra, quando oferecer desconto por prazo.

Não encontramos, nas fontes lidas, uma série pública que quantifique a queda mensal de demanda de locação de máquinas por causa da chuva ou de outro fator. Uma matéria da [Climatempo](https://www.climatempo.com.br/noticia/2022/12/30/impactos-na-construcao-devido-ocorrencia-de-chuva-em-janeiron-8865) lembra que as chuvas intensas são comuns no verão, entre dezembro e março, e podem pausar obras e danificar equipamentos, mas ela não traz números de atividade. Por isso este texto propõe um método para medir o padrão com os seus dados, e não um padrão de mercado.

## O que costuma criar ciclo (hipóteses para testar)

- **Chuva:** pode reduzir a frente de trabalho em terraplenagem e fundação.
- **Calendário:** feriados prolongados e fim de ano mudam a produtividade de canteiros.
- **Ciclo de obra:** fases diferentes usam máquinas diferentes (terraplenagem no começo, elevação e acabamento depois).
- **Segmento do cliente:** agro, indústria e obra pública têm calendários próprios. Para o agro, veja [Locação para o agro](/vendas/2026-11-18T06:50:00-03:00-locacao-para-o-agro-o-que-muda-no-atendimento-na-entrega-e-no-contrato/).

Essas são hipóteses, e não resultados: a sua frota pode mostrar outro padrão.

## O método em seis passos

1. **Junte 24 meses** de dados por categoria de máquina: dias alugados e receita de locação por mês.
2. **Calcule a utilização mensal** de cada categoria, como em [Utilização: o indicador que mais importa](/tecnologia/2026-11-06T14:05:00-03:00-utilizacao-o-indicador-que-mais-importa/).
3. **Calcule a média dos 12 meses** de cada ano.
4. **Divida cada mês pela média do ano.** Um mês com 0,85 está 15% abaixo da média; com 1,10, está 10% acima.
5. **Compare os dois anos.** Se um mês é fraco nos dois, é padrão. Se só um ano foi fraco, foi acontecimento pontual (uma obra grande que acabou, por exemplo).
6. **Separe por categoria.** O índice da plataforma elevatória e o da retroescavadeira raramente são iguais.

Com poucos meses de histórico, use o que existe e deixe claro que o índice ainda é provisório.

## Tabela para montar

| Mês | Utilização ano 1 | Utilização ano 2 | Índice ano 1 | Índice ano 2 | Padrão (média dos índices) |
|-----|------------------|------------------|--------------|--------------|----------------------------|
| Jan | | | | | |
| Fev | | | | | |
| ... | | | | | |
| Dez | | | | | |

## O que fazer com o resultado

- **Manutenção:** programe a preventiva das categorias mais pesadas nos meses de índice baixo, para a máquina não ficar parada quando a demanda sobe. Veja [Preventiva ou corretiva](/gestao-e-operacao/2026-11-09T14:00:00-03:00-preventiva-ou-corretiva-qual-compensa/).
- **Preço:** use o [rate control](/vendas/2026-11-04T17:15:00-03:00-rate-control-nao-dar-desconto-sem-criterio/) para dar desconto por prazo nos meses fracos, e não por pressão do balcão.
- **Compra e venda:** evite comprar no pico de demanda se a categoria tem ciclo forte, e considere vender máquinas ociosas antes do período fraco ([quando vender](/gestao-e-operacao/2026-11-18T08:00:00-03:00-quando-vender-equipamento-criterios-idade-custo-utilizacao/)).
- **Caixa:** projete o fluxo mensal com o índice, e não com a média simples, para não ser surpreendido pelos meses baixos.
- **Equipe:** concentre férias da oficina e do balcão nos meses de menor demanda.

## Como aplicar na sua locadora

1. Exporte do ERP ou da planilha os dias alugados por mês e categoria dos últimos 24 meses.
2. Monte a tabela acima para as três categorias de maior receita.
3. Marque os meses com índice abaixo de 0,90 nos dois anos: são seus meses de baixa.
4. Reprograme a preventiva e as férias da oficina para esses meses.
5. Refaça o cálculo todo ano e acrescente a coluna do ano novo.

## Fontes

- [Climatempo: impactos na construção devido à chuva em janeiro](https://www.climatempo.com.br/noticia/2022/12/30/impactos-na-construcao-devido-ocorrencia-de-chuva-em-janeiron-8865)
