---
image: "/assets/img/antifurto-de-maquinas-rastreador-bloqueio-remoto-e-o-problema-do-jammer.jpg"
title: "Antifurto de máquinas: rastreador, bloqueio remoto e o problema do jammer"
date: 2026-12-17T08:00:00-03:00
theme: "tecnologia"
summary: "Máquina de obra passa dias parada no mesmo lugar, o que muda a forma de rastrear. Rastreador com sensor de movimento, bloqueio remoto e a ameaça dos bloqueadores de sinal, com o que as fontes afirmam e o que é alegação de fornecedor."
source: "Teltonika; Geotab; Tracker (via Motonline); Mundo Logística"
readingTime: "5 min"
---

A máquina entregue em uma obra fica fora das vistas da locadora por semanas. Se ela some, o prejuízo vai além do valor do equipamento: há a receita perdida, o seguro e a relação com o cliente. Rastreador e bloqueio remoto são as respostas mais citadas. Este texto reúne o que as fontes dizem sobre como funcionam e quais são os limites.

As fontes são, em sua maioria, páginas e notícias de **fornecedores de rastreamento**, e por isso muitas afirmações são alegações comerciais. Não encontramos estatísticas independentes de furto de máquinas em canteiros no Brasil, nem dados de eficácia desses sistemas. Trate o que segue como mapa de funções a perguntar ao fornecedor, e não como avaliação de produto. Para o contexto de telemetria, veja [Telemetria e rastreamento](/tecnologia/2026-11-10T16:20:00-03:00-telemetria-e-rastreamento-o-que-as-fontes-do-setor-descem/).

## Por que máquina de obra é um caso diferente

Uma fonte do setor aponta que máquinas de obra ficam muito tempo paradas em um mesmo local, o que dificulta o rastreamento convencional, pensado para veículos em deslocamento. Fabricantes afirmam oferecer posicionamento por satélite preciso em baixa velocidade e em terreno fora de estrada ([Teltonika](https://www.teltonika-gps.com/pt/use-cases/agriculture-construction-%26-mining/basic-track-%26-trace-for-heavy-machinery)).

Os sensores de movimento aparecem como complemento: segundo a Teltonika, a detecção de movimento ajuda gestores de frota de máquinas pesadas a detectar possíveis roubos. A mesma lógica permite saber quando a máquina está ligada e não trabalha, o que ajuda a identificar uso indevido ou ociosidade.

## Camadas de proteção citadas

| Camada | O que faz | Observação |
|--------|-----------|------------|
| Rastreador GNSS | Mostra a posição da máquina | Depende de sinal de satélite |
| Sensor de movimento | Alerta se a máquina se move fora do horário ou da área | Útil para máquina parada |
| Cerca virtual | Avisa quando a máquina sai de uma área definida | Exige cadastrar a obra |
| Bloqueio remoto (imobilizador) | Impede a partida à distância | Risco de bloqueio indevido |
| Rastreador por radiofrequência | Localiza em locais de difícil acesso | Alegação do fornecedor |

## Bloqueio remoto: o cuidado com o falso bloqueio

Um fornecedor brasileiro descreve um imobilizador com desbloqueio remoto por aplicativo, e com desbloqueio automático após um tempo configurável (duas horas, no material consultado) ([Motonline](https://motonline.com.br/noticia/inibidores-de-sinais-nao-interferem-nos-rastreadores-da-tracker/)). O ponto relevante para a locadora: bloquear uma máquina em operação pode causar acidente e parar a obra do cliente. O bloqueio exige procedimento escrito e, de preferência, só com a máquina parada.

## O problema do jammer

A [Geotab](https://www.geotab.com/pt-br/blog/como-bloqueadores-de-gps-afetam-o-monitoramento-de-frotas/) explica que bloqueadores de sinal GPS emitem interferência em um raio de poucos metros (5 a 10 metros, segundo o texto) e podem ocultar a localização do veículo. A mesma fonte afirma que o uso desses bloqueadores é ilegal em vários países, incluindo o Brasil, onde a Anatel realiza operações de apreensão.

A Tracker, por sua vez, afirma que seus rastreadores por radiofrequência não sofrem esse tipo de interferência. É uma declaração comercial, sem verificação independente nas fontes lidas. A pergunta útil ao fornecedor é: **o que o sistema faz quando perde o sinal?** Alertas de perda de comunicação são um sinal prático de interferência.

## Perguntas para o fornecedor

1. O rastreador funciona bem em máquina parada por semanas? Qual a duração da bateria interna?
2. Há alerta de movimento, de saída de área e de perda de sinal?
3. O bloqueio remoto exige confirmação? Há trava para máquina em movimento?
4. Quem acessa a localização, e por quanto tempo ela fica guardada? Veja [LGPD na locadora](/tecnologia/2026-12-07T08:00:00-03:00-lgpd-na-locadora-o-que-fazer-com-cadastro-de-clientes-fotos-de-vistoria-e-localizacao/).
5. O que acontece se o rastreador for removido ou o cabo for cortado?
6. Qual o custo por máquina, por mês, e o custo de instalação?
7. O dado integra com o sistema de gestão, ou é um portal à parte? Veja [Sistema de gestão para locadora](/tecnologia/2026-12-04T08:00:00-03:00-sistema-de-gestao-para-locadora-o-que-exigir-na-demonstracao-antes-de-contratar/).

## O contrato e o seguro

O rastreador precisa estar previsto no contrato: o cliente deve saber que a máquina é monitorada e que a localização é usada para segurança, manutenção e faturamento. Verifique também se a apólice exige rastreador para cobrir furto, como em [Seguro de equipamentos](/gestao-e-operacao/2026-11-30T11:00:00-03:00-seguro-de-equipamentos-na-locadora-o-que-perguntar-ao-corretor-antes-de-renovar/).

## Como aplicar na sua locadora

1. Liste as dez máquinas de maior valor e verifique quais já têm rastreador e de que tipo.
2. Peça a dois fornecedores a demonstração de máquina parada, com alerta de movimento e de perda de sinal.
3. Escreva o **procedimento de bloqueio**: quem decide, em que condições e como confirmar que a máquina está parada.
4. Inclua no contrato uma cláusula de monitoramento e outra sobre remoção ou adulteração do equipamento.
5. Registre por seis meses os alertas recebidos e os falsos alarmes, para ajustar as regras.

## Fontes

- [Teltonika: rastreamento e monitoramento básico para máquinas pesadas](https://www.teltonika-gps.com/pt/use-cases/agriculture-construction-%26-mining/basic-track-%26-trace-for-heavy-machinery)
- [Geotab: como bloqueadores de GPS afetam o monitoramento de frotas](https://www.geotab.com/pt-br/blog/como-bloqueadores-de-gps-afetam-o-monitoramento-de-frotas/)
- [Motonline: inibidores de sinais e rastreadores da Tracker](https://motonline.com.br/noticia/inibidores-de-sinais-nao-interferem-nos-rastreadores-da-tracker/)
- [Teltonika: manutenção oportuna de veículos pesados](https://www.teltonika-gps.com/pt/use-cases/agriculture/heavy-duty-vehicles-timely-maintenance-with-fmx250)
