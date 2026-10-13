# Onda 1.2 — Treinamento: Cadastro de Clientes

Material de apoio da aula "Clientes" da Academia Oravel. Parte do roadmap de treinamento (Onda 1: Ativos e Clientes).

## 1. Conferência da aula contra o sistema (feita em 13/10/2026)

Aula conferida: `database/data/academy/lessons/modulos__clientes.html` (2,5 mil caracteres) e as 8 perguntas originais.

| # | O que a aula dizia | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | Botão "Criar client" | Era um texto em inglês solto na interface (o rótulo do modelo estava sem tradução) | **Corrigido no sistema**: agora é "Novo Cliente" |
| 2 | Ficha com 5 abas | Seis: entra **Acesso ao Portal** | Aula e pergunta corrigidas |
| 3 | Filtros "UF" e "Com Contrato Ativo" | Também **Nicho** e **Lixeira** | Aula e pergunta corrigidas |
| 4 | "Top Clientes" (ranking de 5) na lista de clientes | Não existe na lista. O ranking é um painel do Início ("Top N Clientes com Mais Locações"). Na lista há 5 painéis: cartões, contratos ativos, novos clientes, contratos iniciados x encerrados e Clientes por Nicho | Aula corrigida; pergunta trocada |
| 5 | Não falava de duplicidade, portal, importação, Gestão de Clientes nem Voz do Cliente | Todos existem e são o que mais gera dúvida | 5 seções novas + 15 perguntas |
| 6 | Cartão "Cadastrados no tenant" | Termo interno na interface | **Corrigido no sistema**: "Cadastrados na empresa" (Clientes, Colaboradores e Fornecedores) |

**Pendente (depende de capturas de tela):** `clientes-lista.jpg` e `cliente-novo.jpg` precisam ser conferidas contra a tela atual.

## 2. Regras que o aluno precisa dominar

**Duplicidade** (`App\Support\ClientDuplicidade`): dentro da empresa, não repete Razão Social, Nome Fantasia (e um não coincide com o outro), CNPJ, Inscrição Estadual, Inscrição Municipal, E-mail, Telefone/Celular (8 dígitos ou mais; vale em qualquer dos dois campos) e Endereço (logradouro, complemento, cidade e UF juntos). Ignora maiúsculas, acentos, pontuação e espaços. "isento", "nao tem", "na" e similares não contam. Clientes na Lixeira entram na comparação.

**Portal:** e-mail é o login (obrigatório com o acesso ligado, único entre clientes com acesso). Senha do portal com mínimo de 8 caracteres. Ação "Conceder acesso ao portal"/"Reenviar acesso": senha temporária de 12 caracteres, enviada por e-mail e exibida uma única vez. O cliente também entra por link de 15 minutos e uso único.

**Importação** (`App\Services\ClientExcelImporter`): só Razão Social obrigatória; reconhece cliente existente pelo CNPJ/CPF (dígitos) ou, sem documento, pela Razão Social; "Apenas validar" não grava nada; "Atualizar clientes já cadastrados" só mexe nas colunas preenchidas; não cria acesso ao portal; não geocodifica.

## 3. Roteiro da turma ao vivo (90 minutos)

Pré-requisitos: acesso à empresa de demonstração "Oravel", perfil com permissão em Clientes, e a Onda 1.1 (Ativos) já feita.

| Min | Bloco | O que fazer |
|---|---|---|
| 0–5 | Abertura | O cliente alimenta propostas, contratos, OS, cobranças e o portal. |
| 5–25 | Cadastrar | Ao vivo: Novo Cliente, aba Identificação e Faturamento (Razão Social, CNPJ, Nicho, contato, endereço). O aluno repete sozinho. |
| 25–35 | Demais abas | Entrega e Contatos (obra), Legal e Documentação (checklist de 7 documentos), Análise de Risco. |
| 35–45 | Duplicidade | Tentar cadastrar o mesmo cliente com CNPJ pontuado e sem pontuação; tentar o mesmo telefone no campo Celular; ler os avisos. |
| 45–55 | Portal | Liberar o acesso pela aba (senha de 8+ caracteres) e pela ação "Conceder acesso ao portal"; entrar no portal com o cliente. |
| 55–65 | Pausa | 10 minutos. |
| 65–80 | Importar por Excel | Baixar o modelo, abrir a planilha do exercício, validar, ler os erros, corrigir e gravar. |
| 80–86 | Gestão de Clientes e Voz do Cliente | Solicitações pendentes, mensagens, e a resposta a um elogio com protocolo. |
| 86–90 | Fechamento | Prova da Academia (nota mínima 7,0) e próximos passos (Comercial). |

Perguntas para a turma: "O mesmo CNPJ com e sem pontuação é duplicado?", "Por que o botão de conceder acesso não aparece para este cliente?" (sem e-mail), "Por que importar com Apenas validar primeiro?".

## 4. Exercício prático e gabarito

Planilha: `docs/treinamento/onda-1/planilha-exercicio-clientes.xlsx` (gerada por `scripts/treinamento/gerar-planilha-clientes.php`; dados fictícios).

**Parte A — cadastro manual.** Cadastrar um cliente completo: identificação, contato, endereço de faturamento, local de obra com responsável, e-mails de setor, representante legal e checklist de documentos.

**Parte B — duplicidade.** Tentar cadastrar de novo o mesmo cliente (CNPJ sem pontuação) e anotar a mensagem.

**Parte C — portal.** Ligar o acesso, definir a senha, entrar em `/cliente` com o e-mail do cliente e conferir as telas.

**Parte D — importar a planilha.** 10 linhas, 2 erros propositais:
1. Primeiro com **Apenas validar** ligado: resultado esperado = 8 criados, 2 erros, nada gravado.
2. Corrigir a planilha (preencher a Razão Social da linha 7 e trocar o CNPJ repetido da linha 9).
3. Desligar "Apenas validar" e importar: 10 clientes criados.

| Linha | Cliente | Problema | Mensagem esperada |
|---|---|---|---|
| 7 | (sem Razão Social, nome fantasia "Épsilon Obras") | Razão Social vazia | "Razão Social é obrigatória." |
| 9 | Eta Montagens Industriais Ltda | CNPJ igual ao da linha 3 | "Cliente repetido na planilha (primeira vez na linha 3)." |

O teste `tests/Feature/TreinamentoPlanilhaClientesTest.php` garante que a planilha continua dando 8 + 2 a cada versão do sistema.

## 5. Guia rápido (1 página)

**Salvar exige só a Razão Social.** O e-mail passa a ser obrigatório se o acesso ao portal estiver ligado.
**Antes de criar, procure:** o sistema barra duplicidade por nome, CNPJ, inscrições, e-mail, telefone e endereço (e olha a Lixeira).
**Portal:** e-mail = login; senha com 8+ caracteres; ou "Conceder acesso ao portal" para senha temporária (anote na hora).
**Em massa:** Modelo de importação (Excel) → preencher a partir da linha 3 → Importar Excel com "Apenas validar" → corrigir → importar de verdade. Não cria acesso ao portal.
**Acompanhar o portal:** Gestão de Clientes (solicitações e mensagens) e Voz do Cliente (reclamações, sugestões e elogios).

## 6. Capturas e vídeos a produzir

Capturas (empresa "Oravel", dados fictícios): lista de Clientes com cartões e painéis; ficha na aba Identificação e Faturamento; aviso de duplicidade; aba Acesso ao Portal; janela "Importar Excel"; resultado da validação com erros; tela Gestão de Clientes; tela Voz do Cliente.

Vídeos (3 a 5 minutos cada): (1) cadastrar um cliente completo; (2) duplicidade; (3) acesso ao portal; (4) importar por Excel com validação.
