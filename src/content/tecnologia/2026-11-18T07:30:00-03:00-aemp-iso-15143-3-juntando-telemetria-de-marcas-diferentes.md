---
image: "/assets/img/aemp-iso-15143-3-juntando-telemetria-de-marcas-diferentes.jpg"
title: "AEMP 2.0 e ISO 15143-3: como juntar telemetria de marcas diferentes (e o que esperar)"
date: 2026-11-18T07:30:00-03:00
theme: "tecnologia"
summary: "Existe um padrão para que os dados de telemetria de fabricantes diferentes cheguem ao mesmo painel. O que ele entrega, onde os próprios fornecedores admitem limitações e quando uma planilha ainda basta."
source: "Proemion, AutoPi e Trackunit (documentação e white paper de fornecedores de telemática)"
readingTime: "6 min"
---

Uma locadora com frota de várias marcas costuma ter um portal de telemetria por fabricante. Para saber onde está cada máquina e quantas horas trabalhou, alguém abre três ou quatro sistemas. O padrão AEMP 2.0, hoje descrito como ISO 15143-3, existe justamente para reduzir isso. Este texto explica o que ele é, com um aviso importante: as fontes que conseguimos ler são todas de fornecedores de telemática, ou seja, têm interesse comercial no assunto. Não localizamos aqui o texto da própria norma ISO, e por isso não o citamos.

## O que o padrão faz

O white paper da Proemion descreve o AEMP como um esquema de comunicação para enviar dados de estado de máquinas móveis do servidor do provedor de telemática para aplicativos de terceiros pela internet, originalmente conhecido como ISO 15143-3 e também chamado de AEMP 2.0. É uma troca de dados entre servidores, e não um equipamento novo. Segundo o texto, ela permite reaproveitar o hardware de telemetria já instalado, sem investir em aparelhos adicionais.

A documentação da Trackunit descreve uma interface REST que devolve os dados em XML (padrão) ou JSON.

## Que dados ele traz

Segundo a tabela da Proemion, o padrão cobre:

- **Identificação:** número de série ou PIN, fabricante, modelo.
- **Dados de operação:** localização, combustível usado e restante, horas de operação, horas em marcha lenta, nível de ureia (DEF) restante e fator médio de carga do motor.
- **Alertas:** códigos de falha e de erro.

A AutoPi, outra fornecedora, lista um conjunto básico de cinco itens (localização, horas operadas, combustível total consumido, combustível nas últimas 24 horas e distância percorrida) e uma lista de indicadores opcionais. Repare que as duas fontes não coincidem exatamente sobre o que é básico, o que já mostra a importância de verificar o que cada marca realmente envia.

## Onde ele não entrega tudo

A própria Proemion, que vende uma solução para frotas mistas, escreve que a quantidade de dados disponibilizada por esse padrão é limitada. Atribui isso a fabricantes que implementam o padrão de forma ligeiramente diferente e a um esquema com poucos sinais. O texto também afirma que há muita inconsistência entre fabricantes e que um fabricante pode fornecer certos sinais para alguns modelos e outros sinais para outros. Esse é um relato do fornecedor, sem dados ou método divulgados.

Outro ponto é a frequência. A Proemion dá o exemplo de um sinal de nível de combustível enviado apenas a cada 24 horas e recomenda, para gestão de combustível, atualização no máximo a cada 15 minutos. Segundo o texto, os fabricantes têm intervalos muito diferentes para cada sinal. A Trackunit informa que a própria interface dela tem limite de uma requisição a cada 15 minutos e páginas de 100 itens.

Em resumo: o padrão ajuda a juntar os dados, mas não garante que todo campo venha preenchido, atualizado e igual entre marcas.

## Quando faz sentido, e quando não

- **Faz sentido** se a frota tem várias marcas com telemetria nativa, uma equipe que realmente usa os dados e um processo para agir sobre eles, como alertas de manutenção ou controle de saída.
- **Pode não fazer sentido ainda** se a frota é pequena, tem poucas marcas ou ninguém confere os dados hoje. Nesse caso, a planilha com o horímetro anotado na saída e na devolução resolve o essencial, e integração só adiciona custo.

## Como aplicar na sua locadora

Antes de contratar ou integrar qualquer coisa, faça perguntas objetivas a cada fabricante e a cada provedor. Elas derivam das limitações acima:

1. **Quais campos da lista cada modelo da minha frota envia de fato?** Peça uma lista por modelo, e não a lista geral do padrão.
2. **Com que frequência cada campo é atualizado?** Intervalos diferentes mudam o uso possível.
3. **Os códigos de falha e de erro vêm no feed? Em qual formato?**
4. **Qual identificador é usado:** o número de série da máquina ou o do dispositivo de telemetria? O que acontece quando o da máquina não está disponível?
5. **A cobertura inclui máquinas mais antigas e todos os modelos?**
6. **Há limite de requisições, de histórico ou cobrança extra por série temporal?**
7. **Quem controla as credenciais de acesso?** Defina isso antes, principalmente quando a máquina é de terceiros.

Um método de implantação sugerido pela Proemion também vale como roteiro, mesmo sem comprar nada: primeiro conciliar o seu inventário com o que cada fabricante informa, usando um código seu para cada máquina e não o do fabricante; depois medir quais sinais chegam, para quais modelos e com que frequência; só então passar a operar e cobrar do fabricante o que faltar.

## Fontes

- [Proemion: guia para implantar uma solução de frota mista (white paper, em inglês)](https://www.proemion.com/en/resources/knowledge/white-paper/implement-mixed-fleet-solution.html)
- [AutoPi: o que é o padrão de telemática AEMP (em inglês)](https://www.autopi.io/blog/what-is-aemp-telematics-standard)
- [Trackunit: documentação da API AEMP/ISO (em inglês)](https://developers.trackunit.com/docs/api-reference/aemp-iso-api/aemp-iso-api/)
