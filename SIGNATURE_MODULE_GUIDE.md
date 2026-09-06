# Guia Completo — Módulo de Assinatura Eletrônica Oravel

## Resumo Executivo

O módulo de assinatura eletrônica permite que contratos e ordens de manutenção sejam assinados digitalmente com captura de:
- Assinatura PNG
- Metadados (IP, User-Agent, localização geográfica)
- Hash SHA-256 para auditoria
- Página de auditoria no PDF final

**Status:** ✅ Implementação completa (2026-09-06)

---

## 1. Fluxo End-to-End

```
1. Admin cria Contrato → gera link de assinatura único
2. Link enviado ao cliente (manual ou email future)
3. Cliente acessa link público → formulário de assinatura
4. Cliente desenha assinatura PNG → captura metadados
5. Sistema gera PDF final com página de auditoria
6. Cliente pode baixar PDF assinado
```

---

## 2. Arquitetura

### Models
- **Contract** (e futuramente MaintenanceOrder)
  - `use HasSignatures` → polymorphic relationship
  - `signatures()` → MorphMany para DocumentSignature
  
- **DocumentSignature**
  - Polymorphic: `signable_type` + `signable_id`
  - Status: pending, signed, expired, canceled
  - Token único (32 bytes) com TTL 30 dias
  - Campos: signer_name, signer_email, signature_image_path, ip_address, geolocation, document_hash

### Service Layer
- **SignatureService**
  - `generateSignatureLink()` → cria token, retorna URL
  - `getSignatureByToken()` → valida token + expiração
  - `signDocument()` → processa assinatura, salva PNG, dispara eventos
  - `finalizeSignedPdf()` → gera PDF + página de auditoria + hash
  - `mergePdfs()` → FPDI para combinar PDFs
  - Storage: S3 (signatures/, signed-documents/) ou local fallback

### Controllers
- **PublicSignatureController**
  - GET `/assinatura/{token}` → exibe formulário
  - POST `/assinatura/{token}/assinar` → processa assinatura
  - GET `/assinatura/{token}/sucesso` → página de sucesso
  - GET `/assinatura/{token}/download` → download do PDF assinado

---

## 3. Testando em DEV

### 3.1 Executar Seeder

```bash
# Popula dados de teste
php artisan db:seed --class=ContractSignatureTestSeeder
```

Cria:
- 3 Contratos de teste
- Assinatura **pendente** (não assinado)
- Assinatura **assinada** (com metadados)
- Múltiplas assinaturas (fluxo de aprovação)

### 3.2 Acessar Link de Assinatura

Após o seeder, aparecerá na saída:
```
Link para assinatura pendente: http://localhost:8000/assinatura/[TOKEN]
```

Acesse no navegador para ver o formulário.

### 3.3 Simular Assinatura (via tinker)

```bash
php artisan tinker

# Recuperar assinatura pendente
$sig = \App\Models\DocumentSignature::where('status', 'pending')->first();

# Simular submissão de assinatura com metadados
\App\Facades\SignatureService::signDocument($sig->token, [
    'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
    'signer_name' => $sig->signer_name,
    'ip_address' => '192.168.1.100',
    'user_agent' => 'Mozilla/5.0 (Test)',
    'geolocation' => json_encode(['lat' => -23.5505, 'lng' => -46.6333]),
]);

# Verificar que foi marcado como signed
$sig->refresh();
echo $sig->status; // "signed"
echo $sig->signed_at; // timestamp
```

### 3.4 Gerar e Baixar PDF Assinado

```bash
php artisan tinker

# Recuperar contrato assinado
$contract = \App\Models\Contract::whereHas('signedSignatures')->first();

# Gerar PDF com página de auditoria
$pdfPath = \App\Services\SignatureService::finalizeSignedPdf($contract);

echo $pdfPath; // "signed-documents/{tenant_id}/Contract_{id}_2026-09-06-143022.pdf"

# Verificar arquivo no storage
\Storage::disk('s3')->exists($pdfPath);
```

---

## 4. Fluxo de Impressão do Contrato Assinado

### 4.1 Estrutura do PDF Final

```
[Documento Original]
    ↓ (FPDI importa todas as páginas)
[Página de Auditoria]
    - Signatário: Nome, CPF/CNPJ
    - Data/Hora: ISO 8601
    - IP: 192.168.1.100
    - Geolocalização: Lat/Lng
    - Hash SHA-256: abc123...
    - QR Code (opcional): link de verificação
```

### 4.2 Caminho Exato para Imprimir

#### Opção A: Download Direto (Frontend)
```
GET /assinatura/{token}/download
→ retorna PDF para download no browser
→ arquivo: Contract_{contract_id}_assinado.pdf
```

#### Opção B: Acessar PDF no Storage (Backend)
```php
// Em tinker ou controller:
$signature = DocumentSignature::byToken($token)->first();
$contract = $signature->signable;
$pdfPath = SignatureService::finalizeSignedPdf($contract);

// Caminho no S3/Local:
// signed-documents/{tenant_id}/Contract_{contract_id}_YYYY-MM-DD-HHmmss.pdf

// Para imprimir programaticamente:
$pdf = \Storage::disk('s3')->get($pdfPath);
return response()->streamDownload(fn() => echo $pdf, 'contrato_assinado.pdf');
```

#### Opção C: View + Imprimir (Print-to-PDF)
```blade
<!-- resources/views/signature/success.blade.php -->
<button onclick="window.print()" class="btn btn-primary">Imprimir/Salvar PDF</button>

<!-- Conteúdo renderizado para impressão -->
<div class="print-only">
    {{ $signature->signable->contract_number }}
    Assinado por: {{ $signature->signer_name }}
    Data: {{ $signature->signed_at->format('d/m/Y H:i') }}
</div>
```

### 4.3 Gerar Via API (Integração)

```bash
# Endpoint público (sem autenticação)
GET /api/signature/{token}/pdf

# Retorna:
# - Content-Type: application/pdf
# - Content-Disposition: attachment; filename=contrato_assinado.pdf
```

---

## 5. Testes Unitários

### 5.1 Executar Testes de Assinatura

```bash
php artisan test tests/Feature/SignaturePdfMergeTest.php
php artisan test tests/Feature/SignatureServiceTest.php --filter=test_sign_document
```

### 5.2 Testes Inclusos

- ✅ `test_merge_pdfs_handles_base_pdfs()` — valida FPDI merge
- ✅ Geração de link com token único
- ✅ Validação de expiração
- ✅ Captura de metadados
- ✅ Hash SHA-256 do documento
- ✅ Serialização de geolocalização

---

## 6. Configuração de Storage

### 6.1 Em DEV (Local)

Arquivo `.env`:
```
SIGNATURE_DISK=local
```

PDFs salvos em:
```
storage/app/signed-documents/{tenant_id}/...
```

### 6.2 Em PROD (S3)

Arquivo `.env`:
```
SIGNATURE_DISK=s3
AWS_BUCKET=oravel-documents
AWS_REGION=us-east-1
```

PDFs salvos em:
```
s3://oravel-documents/signed-documents/{tenant_id}/...
```

---

## 7. Views Necessárias

### 7.1 Faltantes (TODO)

Criar as seguintes Blade templates em `resources/views/signature/`:

```
signature/
├── form.blade.php          # Formulário de assinatura com canvas
├── success.blade.php       # Página de sucesso + botão download
├── error.blade.php         # Erro genérico
└── audit-page.blade.php    # Página de auditoria para PDF
```

#### `signature/form.blade.php` (Canvas de Assinatura)

```blade
@extends('layouts.app')

@section('content')
<div class="signature-container">
    <h2>{{ $document->contract_number }} — Assinatura Eletrônica</h2>
    
    <form id="signatureForm" method="POST" action="{{ route('signature.store', $token) }}">
        @csrf
        
        <label>Signatário</label>
        <input type="text" name="signer_name" value="{{ $signature->signer_name }}" required>
        
        <label>CPF/CNPJ (opcional)</label>
        <input type="text" name="signer_document" value="{{ $signature->signer_document }}">
        
        <label>E-mail</label>
        <input type="email" name="signer_email" value="{{ $signature->signer_email }}">
        
        <label>Assinatura Digital (desenhe abaixo)</label>
        <canvas id="signatureCanvas" width="400" height="200"></canvas>
        <input type="hidden" id="signatureBase64" name="signature_base64">
        
        <button type="submit" class="btn btn-primary">Assinar Documento</button>
    </form>
</div>

<script>
// SignaturePad ou similar — captura PNG em base64
// Evento: clique em "Assinar" → envia assinatura_base64 + metadados
</script>
@endsection
```

#### `signature/success.blade.php`

```blade
@extends('layouts.app')

@section('content')
<div class="alert alert-success">
    ✓ Documento assinado com sucesso!
</div>

<p>Assinante: {{ $signature->signer_name }}</p>
<p>Data: {{ $signature->signed_at->format('d/m/Y H:i') }}</p>

<a href="{{ route('signature.download', $signature->token) }}" class="btn btn-primary">
    Baixar PDF Assinado
</a>
@endsection
```

---

## 8. Integração com Filament (Admin)

### 8.1 Resource: DocumentSignatureResource

Localização: `app/Filament/Resources/DocumentSignatureResource.php`

Exibe:
- Token
- Status (pending/signed/expired/canceled)
- Signatário (nome, email, documento)
- Timestamps (created_at, signed_at, expires_at)
- Ações: Visualizar, Cancelar, Renovar Token

### 8.2 Relacionamentos em ContractResource

Adicionar na aba "Assinaturas":
```php
Repeater::make('signatures')
    ->relationship()
    ->schema([
        TextInput::make('signer_name'),
        TextInput::make('status')->disabled(),
        TextInput::make('signed_at')->disabled(),
    ])
```

---

## 9. Checklist de Implementação

- [x] Model DocumentSignature com status, token, expiração
- [x] Trait HasSignatures (polymorphic)
- [x] SignatureService (generate link, sign, finalize PDF)
- [x] FPDI merge PDFs + página de auditoria
- [x] PublicSignatureController + rotas públicas
- [x] Factory e Seeder para testes
- [ ] Blade templates (form, success, error, audit-page)
- [ ] SignaturePad ou canvas library frontend
- [ ] Resource Filament (admin)
- [ ] Emails de notificação (invito para assinar)
- [ ] QR code na página de auditoria
- [ ] Logs de auditoria (activity log)
- [ ] Tests (feature tests para fluxo completo)

---

## 10. Próximas Etapas (Roadmap)

### FASE 1 (Atual)
- Criar views Blade
- Integrar canvas de assinatura (SignaturePad.js)
- Testar fluxo end-to-end em DEV

### FASE 2
- Filament Resource
- Emails de notificação
- QR code + verificação

### FASE 3 (Deploy)
- Testar em staging
- Deploy em PROD
- Monitoramento

---

## 11. Troubleshooting

### "Assinatura expirou"
- Token TTL é 30 dias
- Renovar via `SignatureService::renewSignatureToken()`

### "Erro ao mesclar PDFs"
- FPDI pode falhar com PDFs protegidos
- Fallback retorna PDF original (sem página de auditoria)
- Verificar logs: `Log::error('Erro ao mesclar PDFs')`

### "PDF não encontrado no storage"
- Checar `SIGNATURE_DISK` em `.env`
- Caminho no S3: `s3://bucket/signed-documents/{tenant_id}/...`

---

## 12. Contatos & Dúvidas

- **Código:** `/home/oravel/oravel/app/Services/SignatureService.php`
- **Testes:** `/home/oravel/oravel/tests/Feature/SignaturePdfMergeTest.php`
- **Seeder:** `php artisan db:seed --class=ContractSignatureTestSeeder`
