# Onda 1.1 — Treinamento: Cadastro de Ativos

Material de apoio da aula "Ativos (Equipamentos)" da Academia Oravel. Parte do roadmap de treinamento (Onda 1: Ativos e Clientes).

## 1. Conferência da aula contra o sistema (feita em 13/10/2026)

Aula conferida: `database/data/academy/lessons/modulos__ativos.html` e as 20 perguntas originais.

| # | O que a aula dizia | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | "Categoria e Tipo" é texto livre, "não afeta nada além da exibição" | É uma lista (Categorias de Ativos), alimenta a busca de disponibilidade das Solicitações de Locação e **define quais abas técnicas aparecem** (Empilhadeira, Gerador, Plataforma Elevatória Articulada/Tesoura, pelo nome exato) | Corrigido na aula + 2 perguntas |
| 2 | Campo chamado "Grupo de Checklist" | No formulário o rótulo é **Grupo** | Corrigido |
| 3 | Lista 7 abas | Entram também Empilhadeira, Plataforma Elevatória e Gerador, conforme a categoria | Corrigido |
| 4 | Não fala de **Importar Excel** | Existem "Modelo de importação (Excel)" e "Importar Excel" (Apenas validar / Atualizar existentes) | Seção nova + 6 perguntas |
| 5 | Resposta da pergunta 1 dizia "Categoria é só texto" | Idem item 1 | Texto da resposta corrigido |

**Pendente (depende de capturas de tela):** as imagens `ativos-novo.jpg`, `ativos-edit.jpg` e `ativos-bulk.jpg` precisam ser conferidas contra o formulário atual.

Itens do sistema que a aula **não** cobre e que valem uma decisão: os status `Quarentena` e `Reservado` existem no modelo, mas não aparecem no formulário de cadastro (só os 5 da aula).

## 2. Roteiro da turma ao vivo (90 minutos)

Pré-requisitos: cada aluno com acesso à empresa de demonstração "Oravel" e perfil com permissão em Ativos.

| Min | Bloco | O que fazer |
|---|---|---|
| 0–5 | Abertura | Por que o cadastro de ativos importa: contratos, OS, QR Code, horímetro dependem dele. |
| 5–15 | Preparar | Categorias de Ativos e Grupos de Ativos: criar uma categoria ("Gerador") e um grupo. Mostrar que Categoria ≠ Grupo. |
| 15–40 | Cadastrar | Ao vivo: máquina (Nº Patrimônio único, Nome/Modelo, fotos até 3, localização). Depois o aluno repete sozinho. |
| 40–50 | Veículo e abas técnicas | Trocar "Este ativo é" para Veículo (placa, chassi 17 caracteres, tacógrafo no pesado). Mostrar a aba Empilhadeira aparecendo só com a categoria certa. |
| 50–60 | QR Code e Dossiê | Imprimir etiqueta, abrir o Dossiê Rápido pelo patrimônio e pelo celular. |
| 60–70 | Pausa | 10 minutos. |
| 70–85 | Importar por Excel | Baixar o modelo, abrir a planilha do exercício, importar com "Apenas validar", ler os erros, corrigir e gravar. |
| 85–90 | Fechamento | Perguntas, prova da Academia (nota mínima 7,0), próximos passos (Clientes). |

Perguntas para a turma: "O que acontece se eu cadastrar dois ativos com o mesmo Nº Patrimônio?", "Por que a aba Empilhadeira não apareceu?", "Por que usar 'Apenas validar'?".

## 3. Exercício prático e gabarito

Planilha: `docs/treinamento/onda-1/planilha-exercicio-ativos.xlsx` (gerada por `scripts/treinamento/gerar-planilha-ativos.php`).

**Parte A — cadastro manual.** Cadastrar 3 ativos: uma máquina, uma empilhadeira (preencher a aba Empilhadeira) e um veículo.
Critério: os três salvos, com Nº Patrimônio próprio, e a aba Empilhadeira visível no segundo.

**Parte B — gerar e consultar o QR Code.** Imprimir a etiqueta de um ativo e abrir o Dossiê pelo patrimônio.

**Parte C — importar a planilha.** 10 linhas, 2 erros propositais:
1. Primeiro com **Apenas validar** ligado: resultado esperado = 8 criados, 2 erros, nada gravado.
2. Corrigir a planilha (preencher o Nome/Modelo da linha 7 e trocar o patrimônio repetido da linha 9).
3. Desligar "Apenas validar" e importar: 10 ativos criados.

| Linha | Nº Patrimônio | Problema | Mensagem esperada |
|---|---|---|---|
| 7 | TRE-0005 | Sem Nome/Modelo | "Nome/Modelo é obrigatório." |
| 9 | TRE-0002 | Patrimônio repetido na planilha | "Nº Patrimônio repetido na planilha (primeira vez na linha 4)." |

O teste `tests/Feature/TreinamentoPlanilhaAtivosTest.php` garante que a planilha continua dando 8 + 2 a cada versão do sistema.

## 4. Guia rápido (1 página)

**Antes de cadastrar:** crie as Categorias de Ativos e os Grupos de Ativos que vai usar.
**Campos que travam o cadastro:** Nº Patrimônio (obrigatório e único) e Nome/Modelo.
**Máquina ou veículo?** Escolha em "Este ativo é" primeiro: os campos mudam (veículo tem placa, chassi de 17 caracteres e odômetro em km; pesado tem tacógrafo).
**Categoria × Grupo:** a Categoria classifica e libera as abas técnicas; o Grupo liga o ativo ao checklist e à preventiva.
**Fotos e documentos:** até 3 fotos de 10 MB; documentos privados abrem por link de 10 minutos.
**Em massa:** Modelo de importação (Excel) → preencher a partir da linha 3 → Importar Excel com "Apenas validar" ligado → corrigir → importar de verdade.

## 5. Capturas e vídeos a produzir

Capturas (empresa "Oravel", dados fictícios): lista de Ativos com os dois cartões; formulário completo na aba Informações Gerais (máquina e veículo); aba Empilhadeira; aba Rastreabilidade e QR Code; janela "Importar Excel"; resultado da validação com erros.

Vídeos (3 a 5 minutos cada): (1) cadastrar uma máquina; (2) veículo e abas técnicas; (3) QR Code e Dossiê; (4) importar por Excel com validação.
