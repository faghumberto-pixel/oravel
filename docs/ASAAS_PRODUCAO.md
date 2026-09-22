# Asaas em PRODUÇÃO: passo a passo

Estado em 2026-09-21: a produção tem `ASAAS_API_KEY` **vazia** e `ASAAS_BASE_URL` apontando para o
**sandbox**; nenhum tenant tem `asaas_customer_id`. Sem a chave, o `/assinar` cadastra o tenant (status
`trial`, admin **não aprovado**), não cria cliente/assinatura na Asaas (`asaas_status = error`) e cai em
`checkout.pending`, sem link de pagamento.

Este guia substitui a parte de configuração do `ASAAS_INTEGRATION.md` (que ainda cita menus antigos).

> **Nunca cole a chave de API em chat, e-mail ou commit.** Ela vai direto do painel da Asaas para o
> terminal da VM (passo 2), sem passar por mais ninguém.

## Endereços e autenticação (documentação oficial da Asaas, conferida em 2026-09-21)

| | URL base |
|---|---|
| Produção | `https://api.asaas.com/v3` |
| Sandbox  | `https://api-sandbox.asaas.com/v3` |

Chave no cabeçalho `access_token` (o `AsaasService` já faz isso). Contas e chaves de sandbox e produção
são **independentes**: use a URL do mesmo ambiente da chave. O default de `config/services.php`
(`https://sandbox.asaas.com/api/v3`) é o endereço antigo do sandbox.

## Passo 0: no painel da Asaas (você)

1. A conta de **produção** precisa estar ativa, com o cadastro comercial (CNPJ) aprovado.
2. Gere a **chave de API de produção** em *Integrações → Chaves de API* (o nome exato do menu pode
   variar). Ela aparece uma vez; deixe a aba aberta até o passo 2. Começa com `$aact_`.
3. Ainda **não** crie o webhook: o token dele é gerado no passo 2.

## Passo 1: entrar na VM

```bash
gcloud compute ssh oravel-prod-v2 --zone=southamerica-east1-c --tunnel-through-iap
cd /var/www/oravel
sudo cp -p .env .env.bak-asaas-$(date +%Y%m%d-%H%M)     # backup do .env
```

## Passo 2: gravar chave, token do webhook e URL (um comando, sem histórico)

O comando pede a chave **sem mostrar na tela**, gera o token do webhook e mostra **só o token** (você
vai colá-lo no painel). Valores vão entre aspas simples no `.env` porque a chave contém `$`.

```bash
sudo python3 - <<'PY'
import getpass, re, secrets
key = getpass.getpass("Cole a chave de API da Asaas (não aparece na tela): ").strip()
assert key.startswith("$aact_"), "formato inesperado: a chave começa com $aact_"
token = secrets.token_hex(24)
p = "/var/www/oravel/.env"
s = open(p).read()
def setv(s, k, v):
    line = "%s='%s'" % (k, v)
    return re.sub(r"(?m)^%s=.*$" % k, lambda m: line, s) if re.search(r"(?m)^%s=" % k, s) else s.rstrip("\n") + "\n" + line + "\n"
s = setv(s, "ASAAS_API_KEY", key)
s = setv(s, "ASAAS_WEBHOOK_TOKEN", token)
s = setv(s, "ASAAS_BASE_URL", "https://api.asaas.com/v3")
open(p, "w").write(s)
print("\n.env atualizado.\nTOKEN DO WEBHOOK (cole no painel da Asaas):", token)
PY
sudo chown www-data:www-data .env && sudo chmod 640 .env          # hoje está 644 (legível por qualquer usuário da VM)
sudo -u www-data php artisan config:cache
```

## Passo 3: webhook no painel da Asaas (você)

*Integrações → Webhooks → criar*:

- **URL:** `https://app.oravel.com.br/api/webhooks/asaas`
- **Token de autenticação:** o token mostrado no passo 2
- **Versão da API:** v3
- **Eventos:** `PAYMENT_CONFIRMED`, `PAYMENT_RECEIVED`, `PAYMENT_OVERDUE`, `PAYMENT_DELETED`, `PAYMENT_REFUNDED`
- Ativar. (Eventos fora dessa lista são ignorados com 200.)

## Passo 4: validar a chave, só leitura (nenhuma cobrança)

```bash
KEY=$(sudo grep '^ASAAS_API_KEY=' .env | cut -d= -f2- | tr -d "'")
curl -s -H "access_token: $KEY" -H "User-Agent: oravel" https://api.asaas.com/v3/myAccount/commercialInfo | python3 -m json.tool
unset KEY
```

Esperado: JSON com a razão social, o CNPJ e o endereço da conta (`401` = chave errada ou de outro ambiente).
Esses dados também alimentam o rodapé do site.

## Passo 5: validar o webhook, sem efeitos

```bash
T=$(sudo grep '^ASAAS_WEBHOOK_TOKEN=' .env | cut -d= -f2- | tr -d "'")
curl -s -w '  -> HTTP %{http_code}\n' -X POST https://app.oravel.com.br/api/webhooks/asaas \
  -H 'Content-Type: application/json' -H "asaas-access-token: $T" -d '{"event":"TESTE"}'      # 200 {"status":"received"}
curl -s -w '  -> HTTP %{http_code}\n' -X POST https://app.oravel.com.br/api/webhooks/asaas \
  -H 'Content-Type: application/json' -d '{"event":"TESTE"}'                                   # 401 (sem o token)
unset T
```

Não rode `php artisan asaas:test-webhook` em produção: ele **simula pagamento confirmado** de um
recebível real.

## Passo 6: teste ponta a ponta

1. **Antes, em DEV com sandbox** (sem dinheiro): chave do sandbox e `ASAAS_BASE_URL=https://api-sandbox.asaas.com/v3`
   no `.env` do DEV, faça uma assinatura em `/assinar` e simule o pagamento no painel do sandbox. Para o
   webhook chegar ao DEV é preciso túnel (ngrok).
2. **Em produção, uma vez:** contrate o plano de **menor valor**, pague por PIX e depois estorne/cancele
   pelo painel da Asaas. Confirme: `asaas_customer_id`/`asaas_subscription_id` preenchidos no tenant,
   `asaas_payment_status` = em dia após o pagamento, e o admin aparece para aprovação.

## Passo 7: scheduler do Laravel (hoje NÃO roda na produção)

O `Kernel` agenda `asaas:sync-payment-status` a cada 30 minutos (rede de segurança se um webhook falhar),
mas não há `schedule:run` no cron da VM. Para ativar:

```bash
sudo crontab -u www-data -l 2>/dev/null | { cat; echo '* * * * * cd /var/www/oravel && php artisan schedule:run >> /dev/null 2>&1'; } | sudo crontab -u www-data -
```

Confira antes o que mais está agendado no `Kernel` (`php artisan schedule:list`).

## Antes de abrir para clientes

- O `/assinar` grava `terms_accepted_at`: precisa existir uma página de **Termos de Uso** e de
  **Política de Privacidade** publicadas e linkadas no formulário (hoje o site não tem).
- CNPJ, razão social e endereço no rodapé do site (Decreto 7.962/2013).
- Apague os `.env.bak-*` da VM quando confirmar que tudo funciona (contêm segredos antigos).

## Reverter

`ASAAS_API_KEY=''` no `.env` + `sudo -u www-data php artisan config:cache` volta ao modo atual
(cadastro sem cobrança). O backup do `.env` do passo 1 restaura tudo.
