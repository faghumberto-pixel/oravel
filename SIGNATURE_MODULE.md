# Módulo de Assinatura Eletrônica

## Visão Geral

O módulo de Assinatura Eletrônica permite que Contratos de Locação e Ordens de Serviço sejam assinados eletronicamente via links seguros (tokens únicos), com suporte para:

- **Assinatura Manual** via toque em canvas HTML5 (mobile + desktop)
- **Assinatura Digitada** com renderização em cursivo
- **Captura de Metadados**: IP, User-Agent, Geolocalização
- **PDF Autenticado** com página de auditoria e hash SHA-256
- **Acesso Público** sem autenticação de sessão
- **Validade Jurídica Simples** (sem certificação digital)

---

## Arquitetura

### Models

#### `DocumentSignature`
- Relacionamento polimórfico com `Contract` e `MaintenanceOrder`
- Scopes: `pending()`, `signed()`, `notExpired()`, `byToken()`
- Acessadores: `is_expired`, `is_pending`, `is_signed`, `can_sign`
- Métodos: `markAsSigned()`, `markAsExpired()`, `markAsCanceled()`

#### `Contract` e `MaintenanceOrder`
- Trait `HasSignatures` adicionada
- Relacionamentos: `signatures()`, `pendingSignatures()`, `signedSignatures()`
- Métodos helper: `allSignaturesComplete()`, `countPendingSignatures()`

### Service

#### `SignatureService`
Métodos principais:

```php
// Gera link de assinatura
generateSignatureLink(Model $signable, array $signerData): string

// Recupera e valida assinatura por token
getSignatureByToken(string $token): DocumentSignature

// Processa assinatura (salva imagem, metadados, marca como assinada)
signDocument(string $token, array $data): bool

// Finaliza PDF com página de auditoria
finalizeSignedPdf(Model $signable): ?string

// Cancela assinatura pendente
cancelSignature(DocumentSignature $signature): bool

// Renova token (estende expiração)
renewSignatureToken(DocumentSignature $signature, int $daysToAdd = 30): bool
```

### Controller

#### `PublicSignatureController`
Rotas públicas (sem autenticação):

```
GET  /assinatura/{token}              → show()        (exibe formulário)
POST /assinatura/{token}/assinar      → store()       (processa assinatura)
GET  /assinatura/{token}/sucesso      → success()     (página de sucesso)
GET  /assinatura/{token}/download     → download()    (baixa PDF assinado)
```

### Views

#### `signature.form`
- Canvas HTML5 para desenho de assinatura
- Modo digitação com preview cursivo
- Campos de dados do signatário
- Captura transparente de geolocalização
- Responsivo (mobile + desktop)

#### `signature.success`
- Confirmação de assinatura concluída
- Exibição de metadados (data/hora, IP, geolocalização, hash)
- Link para download do PDF assinado

#### `signature.error`
- Exibição de erros (token expirado, inválido, etc.)
- Sugestões de ação

---

## Fluxo de Uso

### 1. Gerar Link de Assinatura

No painel Filament (admin autenticado):

```php
$service = app(SignatureService::class);

$link = $service->generateSignatureLink(
    $contract,
    [
        'name' => 'João Silva',
        'document' => '123.456.789-00',
        'email' => 'joao@example.com',
        'phone' => '(11) 99999-9999',
    ]
);

// Link: https://oravel.com.br/assinatura/abc123...
// Envia via WhatsApp ou E-mail
```

### 2. Signatário Acessa Link

Clica no link (sem autenticação necessária), vê formulário com:
- Dados do documento (contrato/OS)
- Campos de informações do signatário
- Canvas ou campo de digitação para assinatura

### 3. Signa e Submete

- Desenha ou digita assinatura
- Aceita termos
- Clica "Assinar Documento"
- Geolocalização é capturada automaticamente

### 4. Assinatura Salva

Backend processa:
- Salva PNG da assinatura no Storage (S3/GCS)
- Registra IP, User-Agent, geolocalização
- Marca como assinada
- Dispara evento `DocumentSigned`

### 5. PDF Finalizado

Observer ou job pode:
- Gerar PDF final com página de auditoria
- Anexar hash SHA-256
- Armazenar para download

### 6. Confirmação

Signatário vê:
- Página de sucesso com metadados
- Link para baixar PDF autenticado
- Mensagem "Receberá por e-mail"

---

## Banco de Dados

### Tabela `document_signatures`

```sql
CREATE TABLE document_signatures (
    id UUID PRIMARY KEY,
    signable_type VARCHAR(255),       -- App\Models\Contract, etc
    signable_id UUID,                 -- ID do contrato/OS
    token VARCHAR(64) UNIQUE,         -- Token seguro para link
    signer_name VARCHAR(255),         -- Nome do signatário
    signer_document VARCHAR(20),      -- CPF/CNPJ (opcional)
    signer_email VARCHAR(255),        -- E-mail (opcional)
    signer_phone VARCHAR(20),         -- Telefone (opcional)
    signature_image_path VARCHAR(255),-- Caminho PNG no Storage
    ip_address VARCHAR(45),           -- IPv4/IPv6 de assinatura
    user_agent TEXT,                  -- Browser info
    geolocation JSON,                 -- {lat, lng, accuracy}
    signed_at TIMESTAMP,              -- Quando foi assinado
    status ENUM('pending', 'signed', 'expired', 'canceled'),
    expires_at TIMESTAMP,             -- Expiração do link (padrão 30d)
    document_hash VARCHAR(64),        -- SHA-256 do PDF final
    tenant_id UUID,                   -- Tenant scoping
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,
    
    INDEX (token),
    INDEX (status),
    INDEX (expires_at),
    INDEX (tenant_id, status),
    INDEX (signable_type, signable_id)
);
```

---

## Testes

### Executar Suite Completa

```bash
php artisan test tests/Feature/Signature/
```

### Testes Disponíveis

1. **SignatureServiceTest** (14 testes)
   - Geração de links
   - Validação de tokens
   - Assinatura de documentos
   - Renovação de tokens
   - Cancelamento

2. **PublicSignatureControllerTest** (7 testes)
   - Acesso público sem autenticação
   - Submissão de formulário
   - Validação de erros
   - Download de PDF

3. **DocumentSignatureTest** (20 testes)
   - Relacionamentos morphs
   - Scopes e acessadores
   - Métodos helper na trait
   - Factory

---

## Configuração

### Storage

O módulo salva assinaturas (PNGs) no Storage configurado:

```php
// config/filesystems.php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
    ],
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app'),
    ],
],
```

### Expiração Padrão

Alterar em `SignatureService::generateSignatureLink()`:

```php
$signature->expires_at = now()->addDays(30); // padrão
```

---

## Segurança

### Mitigações Implementadas

1. **Token Único** - 64 caracteres aleatórios (bin2hex(random_bytes(32)))
2. **Expiração** - Links expiram em 30 dias por padrão
3. **Tenant Scoping** - Cada assinatura vinculada a um tenant
4. **Tenant Filtering** - Global scope filtra por tenant_id
5. **IP + User-Agent** - Registrados para auditoria
6. **Geolocalização** - Capturada via browser (opt-in do usuário)
7. **Hash SHA-256** - Verifica integridade do PDF final

### Considerações

- **Sem Certificação Digital** - Validade jurídica simples, não OID/ICP-Brasil
- **Sem Criptografia de Ponta a Ponta** - HTTPS padrão
- **Público** - Qualquer pessoa com token pode assinar (risco intencional: permite assinatura remota sem login)
- **Auditoria** - Atividade registrada via spatie/laravel-activitylog

---

## Eventos

### `DocumentSigned`

Disparado quando uma assinatura é completada:

```php
event(new App\Events\DocumentSigned($signature));
```

Listeners podem:
- Enviar notificação por e-mail
- Gerar PDF com auditoria
- Atualizar status do contrato/OS
- Registrar em CRM/ERP

---

## Extensões Futuras

1. **Certificação Digital** (ICP-Brasil)
2. **Assinatura em Lote** (múltiplos documentos)
3. **Fluxo de Múltiplas Assinaturas** (aprovações sequenciais)
4. **Webhook de Notificação** (integração externa)
5. **Auditoria Expandida** (logs de acesso ao link)
6. **QR Code no PDF** (link para verificação)
7. **2FA** (código SMS antes de assinar)

---

## Troubleshooting

### "Token expirou"
- Solicitar novo link ao admin
- Usar `renewSignatureToken()` para estender prazo

### Geolocalização não capturada
- Navegador pode bloquear (usuário recusa permissão)
- Só funciona em HTTPS
- Captura é best-effort (falha silenciosa se não autorizado)

### PDF não gera
- Verificar se view `documents.signature-audit-page` existe
- Confirmar permissões de escrita no Storage
- Checar logs em `storage/logs/laravel.log`

### Assinatura não salva no Storage
- Verificar configuração de disk em `.env`
- Para S3: confirmar credenciais AWS
- Para local: verificar permissões de pasta

---

## Suporte

Para reportar bugs ou sugerir melhorias:
- Adicionar teste que reproduza o problema
- Incluir stack trace completo
- Incluir dados (anonymized) de reprodução

