# G1 — Validação das normas pelo responsável técnico

> Gerado por `php artisan catalogo:pendencias-norma --md` a partir de `database/seeders/catalog/*.yaml`. **Não editar à mão.**

**Pendência bloqueante:** a chave `tabela_catalogo_equipamentos` não deve ser ligada em contrato de cliente real antes de todos os itens abaixo serem validados.
As normas citadas (NR-10, NR-11, NR-12, NR-13, NR-18, NR-35, CTB/ANTT) são **hipóteses** do template, não afirmações de exigência legal. Os valores são editáveis por empresa.

Total a validar: **33** item(ns).

## Caminhão

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Requisito (DOC) | CRLV / licenciamento | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (DOC) | RNTRC (transporte rodoviario de cargas) | ANTT (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (INSP) | Afericao do tacografo | validar (aferição do tacografo) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (ACESS) | Extintor | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (ACESS) | Triangulo e macaco | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Preventiva (intervalo provisório) | Revisao a cada 10.000 km — 10000 km | Manual do fabricante | Conferir/ajustar o intervalo conforme o manual do fabricante deste modelo. |  |

## Compressor

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Requisito (INSP) | Inspecao de vaso de pressao | NR-13 | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (DOC) | Prontuario | NR-13 | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (INSP) | Teste da valvula de seguranca | NR-13 | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (INSP) | Calibracao do manometro | NR-13 | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Regra | NR-13 vencida = Inapto | — | A regra (e o efeito sobre a aptidão) está correta para o grupo? |  |

## Empilhadeira

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Operador | Operador com capacitacao valida | NR-11 | Qual habilitação/capacitação é exigida e por quanto tempo vale? |  |

## Gerador

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Grupo | Gerador | — | NR-10: confirmar se alguma rotina de documento/laudo se aplica (o padrao nao define nenhuma rotina legal fixa para gerador) |  |

## Guindaste

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Requisito (INSP) | Laudo periodico do guindaste | validar (laudo periodico) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (DOC) | ART (Anotacao de Responsabilidade Tecnica) | validar (ART) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (INSP) | Revisao de cabos de aco e componentes de icamento | validar (revisao de cabos) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Operador | Operador habilitado de guindaste | NR-11 / NR-12 | Qual habilitação/capacitação é exigida e por quanto tempo vale? |  |
| ☐ | Regra | Sem plano de icamento valido: bloqueia a alocacao | — | A regra (e o efeito sobre a aptidão) está correta para o grupo? |  |
| ☐ | Preventiva (intervalo provisório) | Revisao periodica (horas) — 500 h | Manual do fabricante | Conferir/ajustar o intervalo conforme o manual do fabricante deste modelo. |  |

## Munk

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Requisito (INSP) | Laudo / inspecao periodica de icamento | validar (inspecao de icamento) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (DOC) | ART (Anotacao de Responsabilidade Tecnica) | validar (ART) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (DOC) | CRLV / licenciamento | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Operador | Operador habilitado (guindauto / munk) | NR-11 | Qual habilitação/capacitação é exigida e por quanto tempo vale? |  |
| ☐ | Preventiva (intervalo provisório) | Revisao do chassi (km) — 10000 km | Manual do fabricante | Conferir/ajustar o intervalo conforme o manual do fabricante deste modelo. |  |
| ☐ | Preventiva (intervalo provisório) | Revisao do guindaste (horas) — 500 h | Manual do fabricante | Conferir/ajustar o intervalo conforme o manual do fabricante deste modelo. |  |

## Plataforma Elevatória

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Requisito (INSP) | Inspecao periodica da plataforma | validar (NR-18/NR-35) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Operador | Treinamento do operador (trabalho em altura / plataforma) | NR-35 / NR-18 | Qual habilitação/capacitação é exigida e por quanto tempo vale? |  |

## Reboque

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Requisito (DOC) | CRLV / licenciamento | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Preventiva (intervalo provisório) | Revisao a cada 180 dias — 180 h | Manual do fabricante | Conferir/ajustar o intervalo conforme o manual do fabricante deste modelo. |  |

## Veículo leve

| ✔ | Tipo | Item | Norma | O que validar | Validado por / data |
|---|---|---|---|---|---|
| ☐ | Requisito (DOC) | CRLV / licenciamento | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (ACESS) | Extintor | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Requisito (ACESS) | Triangulo e macaco | CTB (validar) | A norma se aplica a este grupo? Qual a periodicidade/validade? (a validade é informada no registro do cumprimento) |  |
| ☐ | Preventiva (intervalo provisório) | Revisao a cada 10.000 km — 10000 km | Manual do fabricante | Conferir/ajustar o intervalo conforme o manual do fabricante deste modelo. |  |
