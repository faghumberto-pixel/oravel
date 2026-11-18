---
image: "/assets/img/estoque-de-pecas-da-oficina-curva-abc-e-criticidade-na-locadora.jpg"
title: "Estoque de peças da oficina: como cruzar curva ABC e criticidade na locadora"
date: 2026-11-28T08:00:00-03:00
theme: "gestao-e-operacao"
summary: "Curva ABC mostra onde está o dinheiro do almoxarifado, mas não diz o que para uma máquina. Cruzar valor, criticidade e prazo de compra define o que manter em estoque e o que pedir sob demanda."
source: "Tractian (curva ABC e inventário de manutenção); estudos acadêmicos sobre sobressalentes (Unicamp, USP)"
readingTime: "5 min"
---

Estoque de peças tem dois custos opostos. Peça demais prende capital e ocupa espaço. Peça de menos deixa a máquina parada na oficina enquanto o cliente espera. A locadora que decide esse equilíbrio item a item, e não por hábito, perde menos dias de frota parada.

## O que a curva ABC resolve e o que ela não resolve

A curva ABC ordena os itens pelo valor consumido: poucos itens (classe A) concentram a maior parte do gasto, e muitos itens baratos (classe C) respondem por pouco. Ela serve para saber onde o controle deve ser mais rigoroso.

O limite é conhecido: a classificação é financeira. Segundo a [Tractian](https://tractian.com/blog/como-utilizar-a-curva-abc-para-uma-gestao-de-estoque-eficiente), um item classe C pode ser crítico quando aplicado em um ativo classe A. Um sensor ou uma mangueira hidráulica custa pouco, mas, sem ele, a máquina de maior receita não sai do pátio.

## Duas perguntas, não uma

Para cada peça, responda separadamente:

1. **Quanto dinheiro ela movimenta?** (valor consumido nos últimos 12 meses, o eixo da curva ABC)
2. **O que acontece se faltar?** (criticidade: para a máquina, para o contrato, para a segurança)

Um terceiro dado completa a decisão: o **prazo de compra**. Peça crítica com entrega em dois dias pode ficar sem estoque. Peça crítica importada, com semanas de espera, dificilmente.

## Matriz de decisão

| Valor consumido | Criticidade | Prazo de compra | Política sugerida |
|-----------------|-------------|-----------------|-------------------|
| Baixo (C) | Alta | Longo | Manter estoque mínimo fixo, mesmo sem giro |
| Baixo (C) | Alta | Curto | Estoque pequeno, reposição automática |
| Baixo (C) | Baixa | Qualquer | Comprar por demanda ou kit de consumo |
| Alto (A) | Alta | Longo | Estoque de segurança calculado e revisão mensal |
| Alto (A) | Alta | Curto | Ponto de reposição enxuto, sem excesso |
| Alto (A) | Baixa | Qualquer | Não estocar; pedir sob demanda ou usar fornecedor local |

A tabela é um ponto de partida. Os limites de "alto", "baixo", "curto" e "longo" precisam ser definidos com os dados da própria oficina.

## Como montar com os dados que a locadora já tem

1. **Extraia o consumo.** Some, por código de peça, o que saiu do almoxarifado nos últimos 12 meses (ordens de serviço ou planilha de saída).
2. **Monte a curva ABC.** Ordene por valor, calcule o percentual acumulado e marque A, B e C.
3. **Classifique a criticidade.** Com o mecânico-chefe, marque cada peça em três níveis: para a máquina, atrasa; para a máquina, impede a saída; afeta a segurança do operador.
4. **Anote o prazo real de compra** do fornecedor habitual, em dias úteis.
5. **Aplique a matriz** e defina estoque mínimo e ponto de reposição para os itens que valem estocar.
6. **Revise todo trimestre.** A frota muda, e o consumo muda junto.

## Peças sem giro

A Tractian cita que almoxarifados de manutenção de plantas industriais brasileiras costumam ter de 15% a 25% de itens sem movimento em 12 meses. A fonte não detalha a metodologia, então use o número só como referência. O exercício útil é outro: liste as peças da sua oficina sem saída no último ano e decida, uma a uma, se é estoque de segurança de item crítico ou sobra de máquina que já foi vendida.

## Ligação com o resto da gestão

- O custo de ter a máquina parada está em [O custo escondido da frota parada](/gestao-e-operacao/2026-11-03T08:50:00-03:00-o-custo-escondido-da-frota-parada/).
- A escolha entre preventiva e corretiva muda a previsibilidade de consumo: veja [Preventiva ou corretiva: qual compensa](/gestao-e-operacao/2026-11-09T14:00:00-03:00-preventiva-ou-corretiva-qual-compensa/).

## Como aplicar na sua locadora

1. Escolha as **cinco máquinas de maior receita** e liste as peças cuja falta as paralisa.
2. Confira quais dessas peças estão hoje no almoxarifado e quais dependem de compra.
3. Para cada peça sem estoque e com prazo longo, calcule o custo de um dia parado da máquina e compare com o custo de manter uma unidade guardada.
4. Defina estoque mínimo para essas peças e registre quem autoriza a reposição.

## Fontes

- [Tractian: como utilizar a curva ABC para uma gestão de estoque eficiente](https://tractian.com/blog/como-utilizar-a-curva-abc-para-uma-gestao-de-estoque-eficiente)
- [Tractian: inventário de manutenção (glossário)](https://tractian.com/glossario/maintenance-inventory)
- [Unicamp (FEC): TCC sobre gestão de sobressalentes com ABC e XYZ](https://lalt.fec.unicamp.br/tccs_fec_0600/turma21/jussara_izahc/tcc.pdf)
