# Proposta Comercial: fluxo de envio, e-mail, impressão e avaliação por IA

Data: 2026-08-28
Status: aprovado para implementação

## Contexto

`App\Models\PropostaComercial` hoje tem um fluxo de 4 status
(`rascunho → enviada_para_comercial → aprovada/rejeitada`), com aprovação
só interna (time Comercial). O usuário relatou que não fica claro pra
quem/quando a proposta é enviada, faltam ferramentas de envio por e-mail
direto do sistema, impressão (individual e em lote) e um recurso de
avaliação por IA que ajude o Comercial a decidir.

Módulos de referência já existentes e testados, reaproveitados por este
design:
- `App\Models\Quote` — já tem aprovação do cliente via link público com
  `approval_token`, sem exigir login (`app/Http/Controllers/QuoteApprovalController.php`,
  rotas `/orcamento/{token}`).
- `App\Mail\GenericPdfMail` — Mailable genérico (corpo + PDF anexo
  opcional), SMTP compartilhado `contato@oravel.com.br`.
- `App\Http\Controllers\QuoteReportController` — gera PDF real via
  `barryvdh/dompdf` (`Pdf::loadView(...)->download(...)`).
- Rotas `*/print` existentes (ex: `maintenance-orders/{id}/print`) — view
  Blade de impressão via navegador (Ctrl+P), sem gerar arquivo.
- `App\Services\AnthropicApiClient` — já em produção no atendente de
  WhatsApp; `send(systemPrompt, userContent, maxTokens)` +
  `parseJson($text)` prontos para resposta estruturada.

## Escopo

1. Novo fluxo de status com aceite/recusa do cliente via link público.
2. E-mail automático ao Comercial (envio interno) e ao cliente (após
   aprovação interna), ambos via `GenericPdfMail`.
3. PDF real da proposta (para o e-mail) + impressão via navegador
   individual e em lote (para a tela).
4. Avaliação por IA sob demanda (botão), 3 critérios combinados.

Fora de escopo (não pedido, não implementar): histórico versionado de
avaliações de IA (reavaliar sobrescreve); SMTP dedicado por tenant;
assinatura eletrônica formal do cliente (o aceite é um clique no link,
mesmo nível de "aceite" que `Quote` já usa hoje).

## 1. Novo fluxo de status

```
rascunho
  → enviada_para_comercial        (vendedor envia; e-mail interno ao Comercial)
    → aprovada_interna             (Comercial aprova; e-mail ao cliente com PDF + link)
      → aceita_pelo_cliente        (cliente aceita no link; SÓ AQUI cria SolicitacaoLocacao)
      → recusada_pelo_cliente      (cliente recusa no link)
    → rejeitada                    (Comercial rejeita; sem mudança de comportamento)
```

### Mudanças em `App\Models\PropostaComercial`

- Novas constantes: `STATUS_APROVADA_INTERNA`, `STATUS_ACEITA_PELO_CLIENTE`,
  `STATUS_RECUSADA_PELO_CLIENTE`. `STATUS_APROVADA` é **removida** (não
  existe dado em produção ainda usando este model — confirmar via
  `PropostaComercial::where('status', 'aprovada')->count()` antes de
  remover a constante; se houver linhas, migrar para
  `aprovada_interna` na mesma migration que adiciona as colunas).
- `statusLabels()`: atualizado com os 3 novos rótulos, remove o antigo
  "Aprovada".
- `aprovar(User $revisor)`: renomeado o efeito — muda status para
  `aprovada_interna` (não mais `aprovada`), **não cria mais a
  SolicitacaoLocacao aqui**. Ao final, dispara o e-mail ao cliente (ver
  seção 2). Se `client->email` estiver vazio, lança
  `\RuntimeException('Defina o e-mail do cliente antes de aprovar.')`
  — mesmo padrão de erro que `enviarParaComercial()` já usa.
- Novo método `aceitarPeloCliente()`: só válido a partir de
  `aprovada_interna`; atualiza status para `aceita_pelo_cliente` +
  `client_responded_at` (nova coluna, mesmo nome usado em `Quote`); então
  chama a lógica hoje em `aprovar()` que cria a `SolicitacaoLocacao`
  (extrair para um método privado `criarSolicitacaoLocacao()` já existe,
  só passa a ser chamado daqui).
- Novo método `recusarPeloCliente(string $motivo)`: só válido a partir de
  `aprovada_interna`; atualiza status para `recusada_pelo_cliente` +
  `client_responded_at` + `rejection_reason` (campo já existe, reaproveita).
- `reabrirParaEdicao()`: passa a aceitar tanto `rejeitada` quanto
  `recusada_pelo_cliente` como estado de origem (`in_array` em vez de
  comparação única), zera os mesmos campos de revisão de hoje.

### Migration nova

`add_client_response_fields_to_proposta_comerciais_table`:
- `approval_token` (string, nullable, unique) — mesmo padrão de `quotes.approval_token`.
- `client_viewed_at` (timestamp, nullable) — mesmo padrão de `quotes.client_viewed_at`.
- `client_responded_at` (timestamp, nullable).

## 2. E-mails (via `GenericPdfMail`)

### Ao Comercial — em `enviarParaComercial()`

Depois do `update()` que já existe, envia para todos os `User` do tenant
com a role `EquipmentDamage::ROLE_COMERCIAL` (mesma role que
`AbstractPolicy`/`EquipmentDamage` já usam — não criar role nova):

```php
$comerciais = User::where('tenant_id', $this->tenant_id)
    ->whereHas('roles', fn ($q) => $q->where('name', EquipmentDamage::ROLE_COMERCIAL))
    ->get();

foreach ($comerciais as $user) {
    Mail::to($user->email)->queue(new GenericPdfMail(
        subjectLine: "Proposta comercial aguardando revisão — {$this->client?->name}",
        greeting: "Olá, {$user->name}",
        bodyText: "Uma proposta comercial foi enviada por {$this->sellerUser?->name} e aguarda sua revisão no painel.",
    ));
}
```

Sem PDF anexo (aviso interno; o revisor abre a tela pra decidir). Se não
houver nenhum usuário com a role Comercial no tenant, não lança erro —
só não envia nada (mesmo espírito de "resolve sozinho quando a role
ainda não foi configurada" já usado em outros pontos do código).

### Ao Cliente — em `aprovar()` (renomeado internamente, ver seção 1)

```php
$pdf = Pdf::loadView('pdf.proposta-comercial', ['proposta' => $this->load(['items', 'client', 'sellerUser'])])->output();

Mail::to($this->client->email)->queue(new GenericPdfMail(
    subjectLine: "Proposta comercial — {$this->client->name}",
    greeting: "Olá, {$this->client->name}",
    bodyText: "Segue em anexo a proposta comercial. Para aceitar ou recusar, acesse: "
        . route('proposta-comercial.public-approval', $this->approval_token),
    pdfContent: $pdf,
    pdfFilename: "proposta-comercial-{$this->id}.pdf",
    senderDisplayName: $this->tenant->name,
));
```

`approval_token` é gerado (se ainda não existir) no mesmo método, mesmo
padrão de `Quote::send()` (`$this->approval_token ?? Str::random(48)`).

## 3. Rotas e páginas públicas (aceite do cliente)

Réplica direta de `QuoteApprovalController` / rotas `/orcamento/{token}`:

- `app/Http/Controllers/PropostaComercialApprovalController.php`:
  - `show(string $token)`: busca por `approval_token`, chama
    `markViewedByClient()` (novo método, mesmo padrão de `Quote`),
    retorna view `proposta-comercial.public-approval`.
  - `approve(string $token)`: chama `aceitarPeloCliente()`, captura
    `\RuntimeException` (já respondida) sem quebrar a página.
  - `reject(string $token, Request $request)`: valida `reason` (required,
    string, max 2000), chama `recusarPeloCliente($reason)`.
- Rotas em `routes/web.php`, fora do grupo autenticado (público, igual
  Quote):
  ```php
  Route::get('/proposta-comercial/{token}', [PropostaComercialApprovalController::class, 'show'])
      ->name('proposta-comercial.public-approval');
  Route::post('/proposta-comercial/{token}/aceitar', [PropostaComercialApprovalController::class, 'approve'])
      ->name('proposta-comercial.public-accept');
  Route::post('/proposta-comercial/{token}/recusar', [PropostaComercialApprovalController::class, 'reject'])
      ->name('proposta-comercial.public-reject');
  ```
- View `resources/views/proposta-comercial/public-approval.blade.php`:
  réplica visual de `resources/views/quotes/public-approval.blade.php`
  (resumo da proposta, itens, valor total, 2 botões — Aceitar / Recusar
  com campo de motivo).

## 4. PDF (e-mail) e impressão (tela)

- `resources/views/pdf/proposta-comercial.blade.php`: view nova para o
  `dompdf`, réplica estrutural de `resources/views/pdf/quote.blade.php`
  (cabeçalho do tenant, dados do cliente, tabela de itens, termos/`terms`,
  total).
- `app/Http/Controllers/PropostaComercialReportController.php::download()`:
  réplica de `QuoteReportController::download()`, mesma assinatura.
  Rota autenticada: `GET /admin/proposta-comercial/{record}/pdf`.
- **Impressão individual** (via navegador, não gera arquivo):
  `resources/views/proposta-comercial/print.blade.php` — reaproveita o
  mesmo layout Blade do PDF acima via um partial compartilhado
  (`resources/views/proposta-comercial/_conteudo.blade.php`, incluído
  tanto pelo `pdf/proposta-comercial.blade.php` quanto por
  `print.blade.php`, evita duplicar o HTML dos itens/termos duas vezes).
  Rota: `GET /admin/proposta-comercial/{id}/print`. Botão "Imprimir" em
  `ViewPropostaComercial` (`Actions::make()` do header, abre em nova aba).
- **Impressão geral** (lote, via navegador): rota
  `GET /admin/proposta-comercial/print?status=&data_de=&data_ate=&vendedor_id=`
  — mesmos filtros já disponíveis na tabela de `ListPropostaComerciais`.
  Controller monta a query com os mesmos filtros, itera as propostas
  encontradas e inclui o partial `_conteudo.blade.php` uma vez por
  proposta dentro de um loop, cada uma com `page-break-after: always` no
  CSS de impressão (mesma técnica de `TablePrintController` genérico).
  Botão "Imprimir Selecionadas" como bulk action na tabela de
  `ListPropostaComerciais`, e um botão "Imprimir Todas (filtro atual)" no
  header da lista que propaga os filtros ativos pra query string da rota.

## 5. Avaliação por IA

Nova coluna `ai_evaluation` (jsonb, nullable) + `ai_evaluated_at`
(timestamp, nullable) em `proposta_comerciais`.

- Botão "Avaliar com IA" em `ViewPropostaComercial`, síncrono (usuário
  espera — sem fila, propostas são poucas linhas de texto, resposta da
  API em poucos segundos).
- `App\Services\PropostaComercialAiEvaluator` (novo, injeta
  `AnthropicApiClient`):
  ```php
  public function evaluate(PropostaComercial $proposta): array
  {
      $systemPrompt = <<<PROMPT
      Você avalia propostas comerciais de locação de equipamentos. Responda
      SOMENTE em JSON com esta estrutura exata:
      {"risco_coerencia": {"nota": 1-5, "comentario": "..."},
       "qualidade_clareza": {"nota": 1-5, "comentario": "..."},
       "probabilidade_fechamento": {"nota": 1-5, "comentario": "..."}}
      PROMPT;

      $userContent = "Cliente: {$proposta->client?->name}\n"
          . "Valor total: R$ {$proposta->total_value}\n"
          . "Validade: {$proposta->valid_until}\n"
          . "Termos: {$proposta->terms}\n"
          . "Itens:\n" . $proposta->items->map(fn ($i) => "- {$i->description} (qtd {$i->quantity}, R$ {$i->unit_price})")->implode("\n");

      $response = $this->client->send($systemPrompt, $userContent, 1024);

      if (! $response['ok']) {
          throw new \RuntimeException($response['error'] ?? 'Falha ao avaliar a proposta com IA.');
      }

      $parsed = $this->client->parseJson($response['text']);

      if (! $parsed) {
          throw new \RuntimeException('A IA não retornou um parecer em formato reconhecível.');
      }

      $proposta->update(['ai_evaluation' => $parsed, 'ai_evaluated_at' => now()]);

      return $parsed;
  }
  ```
  Contrato confirmado em `AnthropicApiClient::send()`:
  `['ok' => bool, 'text' => ?string, 'error' => ?string]` — `text` já vem
  extraído (via `extractText()` interno, que trata o formato de blocos de
  conteúdo incluindo respostas com extended thinking). O botão na UI deve
  capturar `\RuntimeException` e mostrar como `Notification::danger()` do
  Filament, não deixar a página quebrar.
- Reavaliar sobrescreve (`ai_evaluation`/`ai_evaluated_at` atualizados),
  sem histórico — conforme escopo.
- Exibição: `Infolists\Components\KeyValueEntry` ou 3
  `Infolists\Components\TextEntry` (um por critério, com nota + comentário)
  na tela de detalhe, visível só quando `ai_evaluated_at` não é nulo.

## Testes

Seguir `superpowers:test-driven-development` para as partes com lógica
de negócio real (não é seed/dado, é comportamento):
- `PropostaComercialTest`: transições de status (incluindo os novos
  `aprovada_interna`/`aceita_pelo_cliente`/`recusada_pelo_cliente`),
  bloqueio de aprovar sem e-mail de cliente, `aceitarPeloCliente()` cria
  `SolicitacaoLocacao` (e `aprovar()` sozinho **não** cria mais),
  `reabrirParaEdicao()` aceita os dois status de origem.
- `PropostaComercialApprovalControllerTest`: fluxo público completo
  (show marca viewed, approve/reject mudam status, token inválido/
  reutilizado não quebra).
- `PropostaComercialAiEvaluatorTest`: mock do `AnthropicApiClient`,
  confirma parsing e persistência — não bater na API real em teste.
- Envio de e-mail: `Mail::fake()` + `assertQueued(GenericPdfMail::class)`
  nos dois pontos de disparo.
