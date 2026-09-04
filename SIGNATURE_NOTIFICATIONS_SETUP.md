# Integração de Notificações - Assinatura Eletrônica

Guia completo para configurar **E-mail** e **WhatsApp** no módulo de Assinatura Eletrônica.

---

## 📧 Configuração de E-mail

### 1. Variáveis de Ambiente (`.env`)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io        # ou seu SMTP
MAIL_PORT=465                      # ou 587
MAIL_USERNAME=seu-usuario
MAIL_PASSWORD=sua-senha
MAIL_ENCRYPTION=tls                # ou ssl
MAIL_FROM_ADDRESS=noreply@oravel.com.br
MAIL_FROM_NAME="Oravel - Sistema de Gestão"

# Fila
QUEUE_CONNECTION=database          # ou redis/sync
```

### 2. Configurar Fila (opcional mas recomendado)

Para enviar e-mails assincronamente:

```bash
# Usar banco de dados como fila
php artisan queue:table
php artisan migrate

# Ou usar Redis
# QUEUE_CONNECTION=redis em .env
```

### 3. Rodar Fila em Produção

```bash
# Foreground (desenvolvimento)
php artisan queue:work

# Background (produção)
php artisan queue:work --daemon

# Via Supervisor
# /etc/supervisor/conf.d/oravel-queue-worker.conf
[program:oravel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/oravel/artisan queue:work --queue=default,high --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/oravel/queue.log
```

### 4. Testar Envio de E-mail

```bash
# Enviar e-mail de teste
php artisan tinker
>>> Mail::to('teste@example.com')->send(new \App\Mail\SignatureLinkMail(\App\Models\DocumentSignature::first()))
```

---

## 💬 Configuração de WhatsApp

### Opção 1: Evolution API (Recomendado - Open Source)

#### A. Instalar Evolution API

```bash
# Docker Compose
version: '3'
services:
  evolution:
    image: atendai/evolution:latest
    ports:
      - "8080:8080"
    environment:
      - DATABASE_TYPE=postgres
      - DATABASE_HOST=postgres
      - DATABASE_PORT=5432
      - DATABASE_USER=evolution
      - DATABASE_PASSWORD=senha123
      - DATABASE_NAME=evolution_db
    volumes:
      - evolution_data:/app/data

  postgres:
    image: postgres:14
    environment:
      - POSTGRES_USER=evolution
      - POSTGRES_PASSWORD=senha123
      - POSTGRES_DB=evolution_db
    volumes:
      - postgres_data:/var/lib/postgresql/data

volumes:
  evolution_data:
  postgres_data:
```

#### B. Configurar `.env`

```env
WHATSAPP_PROVIDER=evolution
WHATSAPP_API_URL=http://localhost:8080
WHATSAPP_API_KEY=seu-api-key-aqui
WHATSAPP_INSTANCE=seu-numero-whatsapp  # Ex: 5511999999999
```

#### C. Conectar Instância WhatsApp

```bash
# 1. Acessar API
curl -X GET http://localhost:8080/instance/create \
  -H "Authorization: Bearer seu-api-key-aqui" \
  -H "Content-Type: application/json"

# 2. Será retornado um QR code
# 3. Escanear com o WhatsApp
```

---

### Opção 2: Twilio (SaaS, Mais Confiável)

#### A. Criar Conta Twilio

1. Ir a https://www.twilio.com
2. Registrar e criar projeto
3. Ativar WhatsApp Sandbox (ou usar Número Real após verificação)
4. Copiar credenciais

#### B. Configurar `.env`

```env
WHATSAPP_PROVIDER=twilio
TWILIO_ACCOUNT_SID=seu-account-sid
TWILIO_AUTH_TOKEN=seu-auth-token
TWILIO_WHATSAPP_FROM=whatsapp:+1234567890  # Seu número Twilio
```

#### C. Testar Envio

```bash
php artisan tinker
>>> $service = app(\App\Services\WhatsAppService::class)
>>> $service->sendMessage('5511999999999', 'Teste de mensagem!')
```

---

### Opção 3: Z-API (Brazilian Provider)

#### A. Criar Conta Z-API

1. Ir a https://www.z-api.io
2. Registrar
3. Criar token de acesso
4. Conectar sua conta WhatsApp

#### B. Configurar `.env`

```env
WHATSAPP_PROVIDER=zapi
WHATSAPP_API_KEY=seu-token-zapi
```

---

## 🧪 Testando Notificações

### 1. Criar Assinatura via CLI

```bash
php artisan signature:generate-link \
  --type=contract \
  --id=abc123 \
  --name="João Silva" \
  --email="joao@example.com" \
  --phone="11999999999"
```

### 2. Verificar se E-mail foi Enviado

```bash
# Se usar banco de dados como fila
php artisan queue:work

# Verificar tabela jobs
select * from jobs;
```

### 3. Verificar Logs

```bash
tail -f storage/logs/laravel.log
```

---

## 📱 Personalizar Mensagens

### E-mail

Editar: `resources/views/emails/signature-link.blade.php`

```blade
<p>Mensagem customizada aqui</p>
```

### WhatsApp

Editar: `app/Notifications/SignatureLinkWhatsAppNotification.php`

```php
private function buildWhatsAppMessage(string $link, string $documentType): string
{
    return <<<MSG
    Sua mensagem personalizada
    Link: {$link}
    MSG;
}
```

---

## 🔐 Segurança & Boas Práticas

### 1. Nunca Commitar Credenciais

```bash
# .env nunca vai para git
git status
# .env não deve aparecer

# .env.example com placeholders
WHATSAPP_API_KEY=your-key-here
MAIL_PASSWORD=your-password-here
```

### 2. Usar Variáveis de Ambiente

```php
// ✅ Correto
$apiKey = config('services.whatsapp.api_key');

// ❌ Errado
$apiKey = 'chave-hardcoded-no-codigo';
```

### 3. Rate Limiting

Para evitar spam, adicionar rate limiting:

```php
// app/Filament/Resources/DocumentSignatureResource.php
Tables\Actions\Action::make('send_email')
    ->rateLimit(3)  // Máximo 3 e-mails por minuto
    ->rateLimitedAssets(['signature']),
```

### 4. Validar Telefones

```php
// Sempre validar antes de enviar
if (!WhatsAppService::validatePhone($phone)) {
    throw new \Exception('Telefone inválido');
}
```

---

## 🐛 Troubleshooting

### E-mail não chega

**Problema:** Notificação criada mas e-mail não foi recebido

**Soluções:**
1. Verificar fila: `php artisan queue:work`
2. Verificar configuração SMTP em `.env`
3. Checar logs: `storage/logs/laravel.log`
4. Testar envio direto: `Mail::to('teste@example.com')->send(...)`

---

### WhatsApp não envia

**Problema:** Mensagem não chega no WhatsApp

**Soluções:**
1. Verificar se instância está online (Evolution)
2. Confirmar número no formato internacional (55 + DDD + número)
3. Verificar logs de erro
4. Validar permissões de acesso à API

```bash
# Evolution: verificar status
curl http://localhost:8080/instance/stats \
  -H "Authorization: Bearer seu-api-key"
```

---

### Fila travada

**Problema:** `queue:work` saiu do ar e e-mails ficaram presos

**Solução:**
```bash
# Limpar fila
php artisan queue:clear

# Ver jobs falhados
php artisan queue:failed

# Reprocessar falhados
php artisan queue:retry all
```

---

## 📊 Monitorar Notificações

### Verificar Enviadas

```php
// database/migrations - já temos activity log
// Ver notificações enviadas via activity logs

// Filament Resource
App\Models\ActivityLog::where('subject_type', DocumentSignature::class)
    ->where('description', 'like', '%email%')
    ->get();
```

### Dashboards

Considerar adicionar Dashboard Filament para:
- Total de assinaturas enviadas
- Taxa de sucesso de e-mails
- Falhas de envio por dia
- Performance de fila

---

## ✅ Checklist de Produção

- [ ] Configurar SMTP real (não use Mailtrap em produção)
- [ ] Ativar WhatsApp (Evolution ou Twilio)
- [ ] Configurar supervisor para `queue:work`
- [ ] Não commitar credenciais (usar `.env` local)
- [ ] Testar fluxo completo (criar assinatura → receber e-mail/SMS)
- [ ] Monitorar logs regularmente
- [ ] Configurar rate limiting
- [ ] Backup de credenciais (cofre seguro, não no Git)
- [ ] Testar recuperação de fila (queue:retry)

---

## 📞 Suporte

Para erros específicos, consulte:

- **Evolution API**: https://evolution-api.gitbook.io/
- **Twilio**: https://www.twilio.com/docs/whatsapp/
- **Z-API**: https://www.z-api.io/docs
- **Laravel Mail**: https://laravel.com/docs/mail
- **Laravel Queues**: https://laravel.com/docs/queues

