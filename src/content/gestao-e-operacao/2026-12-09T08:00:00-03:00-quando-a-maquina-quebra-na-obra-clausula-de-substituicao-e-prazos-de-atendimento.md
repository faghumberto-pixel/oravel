---
image: "/assets/img/quando-a-maquina-quebra-na-obra-clausula-de-substituicao-e-prazos-de-atendimento.jpg"
title: "Quando a máquina quebra na obra: cláusula de substituição e prazos de atendimento"
date: 2026-12-09T08:00:00-03:00
theme: "gestao-e-operacao"
summary: "Para o cliente, máquina parada é obra parada. Um modelo de cláusula com prazo de resposta, prazo de substituição por equipamento equivalente e regras de custo, adaptado de modelos de SLA de outros setores."
source: "Modelos de SLA de órgãos públicos (SP e RJ); guia de gestão de contratos de manutenção (Produttivo)"
readingTime: "5 min"
---

A quebra acontece na obra do cliente, geralmente no pior momento. O que decide a reação dele não é só a falha, e sim a rapidez e a clareza da resposta da locadora. Um prazo combinado em contrato transforma uma discussão em um procedimento.

Não encontramos, nas fontes lidas, modelos públicos de SLA específicos para locação de máquinas de obra. O que existe são modelos de contratos de TI e de órgãos públicos, que tratam de equipamentos em manutenção. Usamos esses modelos como base de estrutura, e **os números de prazo abaixo são exemplos a calibrar com a sua oficina e com o seu jurídico**, não padrões do setor.

## O que os modelos de SLA ensinam

- Um documento de órgão público de São Paulo prevê que, se o fornecedor não consertar o equipamento no prazo, ele deve **trocar por outro igual ou superior**, que permanece até o original voltar a funcionar ([Prefeitura de São Paulo](https://biblioteca.tecnologia.prefeitura.sp.gov.br/books/ot-003-servicos-de-impressao-e-digitalizacao/page/quais-sao-as-nossas-recomendacoes-k9o/export/pdf)).
- O mesmo documento diferencia prazo de **primeira resposta** e prazo de **encerramento**.
- Uma tabela de SLA do governo do Rio de Janeiro fixa prazo, em dias úteis, para **substituir equipamento em manutenção** por outro equivalente em perfeito estado ([Proderj](https://www.rj.gov.br/proderj/sites/default/files/arquivos-andamento/Acordo%20de%20Nivel%20de%20Servico_Anexo%20IX_0.pdf)). Os prazos variam por região.
- Um guia de gestão de contratos de manutenção cita como métricas o **tempo de resposta** e o **tempo de resolução**, e observa que os prazos variam conforme a criticidade do equipamento ([Produttivo](https://www.produttivo.com.br/blog/gestao-de-contratos-de-manutencao/)).

## Os quatro prazos que a cláusula precisa definir

| Prazo | O que mede | Exemplo de estrutura (calibrar) |
|-------|------------|----------------------------------|
| Comunicação | Do aviso do cliente até o registro do chamado | Canal único, horário de funcionamento definido |
| Primeira resposta | Do chamado até o contato técnico | Em horas, por categoria de máquina |
| Diagnóstico | Do contato até saber se conserta no local | Em horas ou dias |
| Substituição | Do diagnóstico até a máquina equivalente na obra | Em dias, por distância e disponibilidade |

A criticidade muda o prazo: uma plataforma que sustenta o cronograma de uma fachada pede resposta mais rápida do que um gerador reserva.

## Cláusulas que evitam disputa

1. **Equivalência:** a máquina substituta deve ter capacidade igual ou superior, com a mesma configuração mínima (implementos, cabine, acessórios).
2. **Suspensão da cobrança:** definir se a diária é suspensa a partir do aviso, do diagnóstico ou do fim do prazo de resposta.
3. **Causa da falha:** se for desgaste normal, o custo é da locadora; se for mau uso, a regra de [vistoria e dano](/gestao-e-operacao/2026-11-19T08:00:00-03:00-checklist-vistoria-entrega-devolucao-evitar-disputa/) se aplica. O registro da saída é a prova.
4. **Transporte:** quem paga o frete da troca, ida e volta. Veja as regras de [transporte e AET](/gestao-e-operacao/2026-12-01T08:00:00-03:00-transporte-de-maquinas-na-locacao-carga-indivisivel-aet-e-quem-responde-pelo-frete/).
5. **Limite de responsabilidade:** o que a locadora responde por atraso da obra, se responde. Isso precisa de revisão jurídica.
6. **Exceções:** áreas de difícil acesso, feriados, condição de clima e acesso bloqueado pelo cliente.

## Custo de um prazo curto

Segundo um texto de outro setor (infraestrutura de dados), SLAs premium de resposta rápida, 24 horas por dia, custam bem mais do que os de horário comercial. A lógica vale para a locação: **atendimento mais rápido exige técnico de plantão, máquina reserva e peça em estoque**. Por isso o prazo prometido deve caber na estrutura real. Veja o dimensionamento em [Estoque de peças da oficina](/gestao-e-operacao/2026-11-28T08:00:00-03:00-estoque-de-pecas-da-oficina-curva-abc-e-criticidade-na-locadora/) e o impacto do tempo parado em [O custo escondido da frota parada](/gestao-e-operacao/2026-11-03T08:50:00-03:00-o-custo-escondido-da-frota-parada/).

## Como aplicar na sua locadora

1. Meça os tempos reais dos últimos 20 chamados: da ligação do cliente até a máquina de volta à operação.
2. Classifique a frota em três níveis de criticidade e defina o prazo de cada um com base no que a oficina consegue cumprir.
3. Separe uma ou duas máquinas de reserva nas categorias de maior demanda, e calcule o custo de mantê-las paradas.
4. Redija a cláusula com os quatro prazos e as seis regras acima, e peça ao advogado que a revise.
5. Registre cada chamado (hora do aviso, resposta, solução) e apresente o resultado ao cliente no fechamento do mês.

## Fontes

- [Prefeitura de São Paulo: recomendações de SLA para serviços de impressão](https://biblioteca.tecnologia.prefeitura.sp.gov.br/books/ot-003-servicos-de-impressao-e-digitalizacao/page/quais-sao-as-nossas-recomendacoes-k9o/export/pdf)
- [Proderj (RJ): acordo de nível de serviço, anexo IX](https://www.rj.gov.br/proderj/sites/default/files/arquivos-andamento/Acordo%20de%20Nivel%20de%20Servico_Anexo%20IX_0.pdf)
- [Produttivo: gestão de contratos de manutenção](https://www.produttivo.com.br/blog/gestao-de-contratos-de-manutencao/)
- [Introl: SLA de 4 horas vs 24 horas](https://introl.com/pt/blog/4-hour-vs-24-hour-sla-remote-hands-ai-operations)
