# Onda 3.2 — Treinamento: Proposta Comercial e Orçamentos

Onda 3 = Comercial e contratos (3.1 Contratos, 3.2 Proposta Comercial + Orçamentos, 3.3 Solicitações de Locação).

## 1. Conferência das aulas contra o sistema (feita em 14/10/2026)

Fonte conferida: `PropostaComercial` (+ item, template, observer), `PropostaComercialResource` (+ View/List/Create), `PropostaComercialMobile`, `PropostaComercialApprovalController`, `PropostaComercialKanban`, `Quote` (+ item), `QuoteResource` (+ `EditQuote`, stats).

### Proposta Comercial

| # | O que a aula dizia | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | 4 status (rascunho, enviada, aprovada, rejeitada) | 6: inclui Aprovada — Aguardando Cliente, Aceita pelo Cliente e Recusada pelo Cliente | Tabela nova |
| 2 | Aprovação "aciona" tudo | Aprovar exige e-mail do cliente, cria a Solicitação (se há equipamento) e **envia o PDF ao cliente por e-mail**; o cliente aceita/recusa por link público; o aceite gera os contratos de serviço em Rascunho | Seção nova + 6 perguntas |
| 3 | 2 tipos de item | 6 tipos (4 de serviço só no computador); itens têm período, datas e observações próprios | Tabela nova |
| 4 | "O vendedor nunca aprova a própria proposta" | **Não era verdade** (nada no código impedia) | **Corrigido no sistema** |
| 5 | "O vendedor pode reabrir" | O método existia, mas **nenhuma tela o chamava** | **Corrigido no sistema** |
| 6 | Template padrão "usado automaticamente" | **Nunca era aplicado** (`fillFromTemplate()` sem chamadores) | **Corrigido no sistema** |
| 7 | "Todos com perfil Comercial recebem o aviso" | A pessoa escolhida no envio recebe o aviso no sistema; o perfil Comercial recebe e-mail | Corrigido + pergunta |
| 8 | Não citava reenvio, WhatsApp, IA, interações, templates com cabeçalho/campos/foto, impressão | Existem | Seções novas + 7 perguntas |
| 9 | Texto duplicado | — | Removido |

### Orçamentos

| # | O que a aula dizia | O que o sistema faz | Ação |
|---|---|---|---|
| 1 | Interno "ligado a OS interna" e A Terceiro "solto" | O formulário **não tem vínculo com OS**; A Terceiro exige fornecedor e laudo técnico; só o Indenizatório fica ligado (à avaria) | Corrigido + 3 perguntas |
| 2 | Concluído = "aprovado e já faturado" | Há duas ações separadas: **Encaminhar ao Financeiro** (cria a Conta a Receber, uma vez) e **Concluir Processo** | Seção nova + 3 perguntas |
| 3 | Não citava itens, ações por status, e-mail, aprovar/reprovar manual | Tabela de ações por status | Tabela nova |
| 4 | Cartões "total, valor cotado…" | Total de Orçamentos, Valor Total Orçado, Aguardando Aprovação, Aprovados (aprovados + concluídos) | Corrigido + pergunta |
| 5 | Texto duplicado | — | Removido |

Provas: Proposta de 9 para 27 perguntas; Orçamentos de 8 para 18.

### Correções feitas no sistema

1. **Revisão da própria proposta:** `aprovar()`/`rejeitar()` recusam o vendedor; o administrador da empresa pode (para não travar quem vende e revisa sozinho). **Decisão sua:** se preferir a regra estrita, tirar a exceção do admin.
2. **Reabrir:** ação "Reabrir para edição" na tela da proposta (Rejeitada ou Recusada pelo Cliente → Rascunho, apagando o motivo).
3. **Template padrão:** proposta nova sem termos/cabeçalho/campos recebe o template Padrão ativo do tenant (copia termos, cabeçalho, campos e validade); no celular, a etapa de termos mostra o texto já aplicado.

Testes: `PropostaRevisaoRegrasTest` (5), `PropostaTemplatePadraoTest` (4). Rodei também os 78 testes de proposta existentes: passam. Não repeti a prova de que os novos falham sem a correção (o teste do template falharia por construção, pois `fillFromTemplate()` não tinha chamador).

### Achados em aberto

- **Orçamento Interno/A Terceiro sem vínculo com OS** no formulário (a aula antiga prometia).
- **Celular só tem 2 tipos de item**; contratos de serviço só nascem de propostas feitas no computador, ou que tenham itens dos 4 tipos de serviço.
- **Imagens:** `proposta-lista.jpg`, `proposta-kanban.jpg`, `orcamentos-lista.jpg` não foram conferidas; faltam prints da tela de aprovação do cliente, do template e do botão Reabrir.
- **Falhas antigas:** 5 testes de assinatura (`Tenant::factory()`), `step three can clear damage photos`, `PainelPmpTest` "page renders" (403).

## 2. Roteiro de aula (40 a 50 min)

1. Do lead ao cliente: de onde vem a proposta; o celular e o computador. 5 min
2. Montar uma proposta: itens (equipamento e serviço), termos e template. 12 min
3. Revisão: enviar, aprovar, rejeitar e reabrir; a regra do próprio vendedor. 8 min
4. O que o cliente vê e responde; o que o aceite aciona. 8 min
5. Orçamentos: os 3 tipos, itens, enviar, aprovar. 10 min
6. Do orçamento aprovado ao Financeiro. 5 min

## 3. Exercício prático (empresa "Oravel", dados fictícios)

**Parte A — template.** Criar um template Padrão com cabeçalho e dois campos (Pagamento e Garantia). Abrir uma proposta nova e conferir que veio preenchida.
**Parte B — proposta.** Criar uma proposta com 1 equipamento (categoria) e 1 serviço de mão de obra (no computador), com datas.
**Parte C — revisão.** Enviar para revisão. Tentar aprovar com o mesmo usuário (se não for admin: recusa). Entrar com outro usuário e rejeitar com motivo; o vendedor reabre e envia de novo.
**Parte D — aprovar.** Sem e-mail no cliente: anotar a mensagem. Cadastrar o e-mail e aprovar; abrir a Solicitação de Locação criada.
**Parte E — cliente.** Abrir o link, aceitar; conferir o contrato de serviço em Rascunho e o aviso ao Comercial.
**Parte F — orçamento.** Criar um orçamento Interno com 1 peça e 1 serviço; enviar ao cliente (e-mail de teste); marcar como Aprovado.
**Parte G — financeiro.** Encaminhar ao Financeiro e abrir a Conta a Receber; depois Concluir Processo.
**Parte H — indenizatório.** Em uma avaria com causa mau uso, gerar o orçamento Indenizatório.

## 4. Guia rápido (1 página)

**Proposta:** Rascunho → Enviada → Aprovada (aguardando cliente) → Aceita ou Recusada pelo Cliente; Rejeitada volta por "Reabrir para edição".
**Aprovar:** exige e-mail do cliente; cria Solicitação (se há equipamento) e envia PDF + link.
**Aceite do cliente:** contratos de serviço em Rascunho + aviso para ativar.
**Quem revisa:** não pode ser o vendedor (exceto admin).
**Template Padrão:** aplicado sozinho em proposta nova; é cópia.
**Orçamento:** Rascunho → Enviado → Aprovado/Reprovado → (Encaminhar ao Financeiro) → Concluído; cliente decide por link sem login.
**Indenizatório:** só nasce de uma Avaria com mau uso/dano do cliente.

## 5. Capturas e vídeos a produzir

Capturas: lista de propostas e Kanban Comercial; tela da proposta com Reabrir; formulário com itens; template (cabeçalho, campos, ler imagem); e-mail/PDF; página pública do cliente; lista de orçamentos com cartões; formulário A Terceiro; ações do orçamento enviado e aprovado.
Vídeos (3 a 5 min): (1) proposta do celular à aprovação; (2) o que o cliente vê e responde; (3) orçamento até a Conta a Receber.
