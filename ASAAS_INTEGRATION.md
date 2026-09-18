# Integração Asaas

Guia completo para configurar e usar a sincronização de cobranças com Asaas.

## Configuração Inicial

### 1. Variáveis de Ambiente

Adicione ao `.env`:

```bash
ASAAS_API_KEY=your_api_key_here
ASAAS_WEBHOOK_TOKEN=your_webhook_token_here
ASAAS_BASE_URL=https://api.asaas.com/v3
```

### 2. Registrar Webhook no Painel Asaas

1. Acesse https://painel.asaas.com
2. Vá para **Configurações > Webhooks**
3. Clique em **Adicionar Webhook**
4. Configure:
   - **URL:** `https://app.oravel.com.br/api/webhooks/asaas`
   - **Token:** Cole o valor de `ASAAS_WEBHOOK_TOKEN`
   - **Eventos:** Selecione:
     - ✓ PAYMENT_CONFIRMED
     - ✓ PAYMENT_RECEIVED
     - ✓ PAYMENT_OVERDUE
     - ✓ PAYMENT_DELETED
     - ✓ PAYMENT_REFUNDED
5. Salve

## Fluxos de Sincronização

### Fluxo 1: Criar Conta → Sincronizar com Asaas (Automático)

Quando uma **AccountReceivable** é criada:

1. Observer `AccountReceivableObserver` é acionado
2. Se tenant tem `asaas_customer_id`, cria cobrança no Asaas
3. `asaas_payment_id` é gravado automaticamente
4. Status fica sincronizado com Asaas

**Sem configuração necessária** — automático via Observer.

### Fluxo 2: Pagamento no Asaas → Atualizar Status (Via Webhook)

Quando cliente paga no Asaas:

1. Webhook dispara em `/api/webhooks/asaas`
2. Status é atualizado automaticamente:
   - PAYMENT_CONFIRMED / PAYMENT_RECEIVED → `pago`
   - PAYMENT_OVERDUE → `atrasado`
   - PAYMENT_DELETED / PAYMENT_REFUNDED → `pendente`
3. Idempotente — eventos duplicados são ignorados

**Sem configuração necessária** — automático via webhook.

### Fluxo 3: Sincronização Periódica (Via Cron)

A cada 30 minutos, o comando:

```bash
php artisan asaas:sync-payment-status
```

Sincroniza status de contas pendentes/atrasadas com Asaas.

**Ativado automaticamente** se cron está rodando.

## Comandos de Gerenciamento

### Ver Status de Sincronização

```bash
php artisan asaas:sync-status
```

Mostra:
- Configuração (token)
- Estatísticas de contas (com/sem Asaas)
- Status de tenants

### Sincronizar Contas Existentes

Para sincronizar todas contas que ainda não têm `asaas_payment_id`:

```bash
php artisan asaas:sync-receivables
```

Opções:
- `--tenant-id=UUID` — sincronizar apenas um tenant

### Testar Webhook

```bash
php artisan asaas:test-webhook
```

Simula um pagamento confirmado para verificar se webhook está funcionando.

## UI do Painel Admin

Na tela de **Contas a Receber**:

1. **Coluna "Asaas"** — mostra se conta está sincronizada
   - ✓ Verde = sincronizada
   - ⚠ Amarelo = manual (sem Asaas)

2. **Ação "Sincronizar Asaas"** — sincronizar conta individual
   - Clique para criar cobrança no Asaas
   - Notificação de sucesso/erro

## Troubleshooting

### "Token Asaas não configurado"

```bash
# Verificar
grep ASAAS_WEBHOOK_TOKEN .env

# Adicionar
echo 'ASAAS_WEBHOOK_TOKEN=seu_token' >> .env
```

### Contas não sincronizando

1. Verifique se tenant tem `asaas_customer_id`:
   ```bash
   php artisan asaas:sync-status --tenant-id=UUID
   ```

2. Se não tiver, crie customer:
   - Edite tenant no painel Central
   - Clique "Sincronizar com Asaas"

3. Tente sincronizar manual:
   ```bash
   php artisan asaas:sync-receivables --tenant-id=UUID
   ```

### Webhook não recebendo eventos

1. Verifique token no painel Asaas
2. Teste webhook:
   ```bash
   php artisan asaas:test-webhook
   ```

3. Verifique logs:
   ```bash
   tail -f storage/logs/laravel.log | grep Asaas
   ```

## Teste Automatizado

```bash
php artisan test tests/Feature/Asaas/WebhookTest.php
```

Testa:
- ✓ Autenticação webhook
- ✓ Atualização de status
- ✓ Idempotência
- ✓ Reembolsos

## Arquitetura

```
┌─────────────────────────┐
│  AccountReceivable      │
│  criada no Painel       │
└──────────┬──────────────┘
           │
           ├─→ Observer.created()
           │   └─→ AsaasService.createPayment()
           │       └─→ POST /customers/xxx/payments (Asaas)
           │           └─→ asaas_payment_id gravado ✓

┌─────────────────────────┐
│  Cliente paga no Asaas  │
└──────────┬──────────────┘
           │
           ├─→ POST /webhooks/asaas (webhook)
           │   └─→ AsaasWebhookController.handle()
           │       └─→ status atualizado ✓

┌─────────────────────────┐
│  Cron Job (a cada 30min)│
└──────────┬──────────────┘
           │
           ├─→ asaas:sync-payment-status
           │   └─→ AsaasService.syncPaymentStatus()
           │       └─→ GET /payments/{id} (Asaas)
           │           └─→ status sincronizado ✓
```

## Segurança

- ✓ Token webhook validado em cada requisição
- ✓ Idempotência automática (no duplica pagamentos)
- ✓ Tenant-scoped (contas isoladas por tenant)
- ✓ Logging de todos os eventos
- ✓ Sem exponentes de API key via frontend

## Support

Para dúvidas ou problemas:

1. Consulte os logs: `storage/logs/laravel.log`
2. Teste o webhook: `php artisan asaas:test-webhook`
3. Verifique status: `php artisan asaas:sync-status`
