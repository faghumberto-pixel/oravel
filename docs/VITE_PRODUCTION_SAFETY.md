# Vite Production Safety — Prevenção permanente do problema localhost:5173

**CRÍTICO:** PROD não deve nunca tentar carregar assets de `localhost:5173` (Vite dev server).
Isso já aconteceu múltiplas vezes no passado e deixa a aplicação quebrada.

## Causa raiz

O Laravel com Vite plugin decide usar um dos dois modos baseado em:

```
if (APP_ENV === 'local' OR APP_DEBUG === true)
  → usa Vite dev server em localhost:5173
else
  → usa manifest.json + assets em public/build/assets
```

**Problema:** Se `APP_ENV=local` ou `APP_DEBUG=true` em PROD, mesmo que haja `public/build/manifest.json` buildado,
o Vite plugin tenta conectar ao `localhost:5173` que não existe. O browser então carrega a página sem CSS/JS,
deixando tudo quebrado.

### Por que acontecia?

1. `.env` local tem `APP_ENV=local` + `APP_DEBUG=true` (padrão para desenvolvimento)
2. `.env.production` não era sincronizado com PROD
3. `.env` era commitado no git (❌ práticas ruins)
4. Deploy simplesmente puxava o código e rodava com o `.env` errado

## Solução implementada

### 1. **Pre-Deploy Validation** (`scripts/pre-deploy-validation.sh`)

Roda **localmente** antes de fazer push para o Git. Valida:

- ✅ `public/build/manifest.json` existe
- ✅ `public/build/assets/` tem arquivos
- ✅ `manifest.json` é um JSON válido
- ✅ `.env.production` tem `APP_ENV=production`
- ✅ `.env.production` NÃO tem `APP_DEBUG=true`

**Uso:**
```bash
bash scripts/pre-deploy-validation.sh
```

Integrado no `deploy.sh` — roda automaticamente antes de qualquer push.

### 2. **Health Check Pós-Deploy** (`scripts/health-check-post-deploy.sh`)

Roda **localmente depois que o deploy termina**. Verifica:

- ✅ Servidor respondendo HTTP 200
- ✅ NÃO há referências a `localhost:5173` na página
- ✅ Assets estão carregando (HTTP 200)
- ✅ Nenhum erro 500

**Uso:**
```bash
bash scripts/health-check-post-deploy.sh              # testa https://app.oravel.com.br
bash scripts/health-check-post-deploy.sh https://url # testa URL customizada
```

Integrado no `deploy.sh` — roda automaticamente após deploy terminar.

### 3. **Artisan Command** (`php artisan vite:check`)

Roda **em PROD ou DEV** para inspecionar integridade de assets.

**Verifica:**
- APP_ENV e APP_DEBUG
- Integridade de manifest.json
- Presença de assets
- Se dev server está ativo

**Uso:**
```bash
php artisan vite:check              # simples check
php artisan vite:check --strict     # falha se há problemas (exit code 1)
```

Integrado no `deploy.sh` — roda remotamente durante deploy.

## Configuração segura em PROD

### `.env.production` (arquivo de PROD, não commitado)

Deve ter OBRIGATORIAMENTE:

```env
APP_ENV=production
APP_DEBUG=false
```

Este arquivo **não está no git**. É criado manualmente em PROD.

### Como criar em PROD (via gcloud SSH)

```bash
# Conectar na VM
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# Criar/editar .env.production
sudo -u www-data nano /var/www/oravel/.env.production

# Mínimo obrigatório (pode copiar .env.example e editar):
APP_ENV=production
APP_DEBUG=false
APP_KEY=... (já configurado)
APP_URL=https://app.oravel.com.br
DB_... (já configurado)
...
```

Depois, no deploy, Laravel carrega:
1. `.env` padrão (local, tem settings de DEV)
2. `.env.production` sobrescreve com settings de PROD (o arquivo em .env.production vence)

## Checklist de segurança antes de cada deploy

### Localmente (antes de fazer push)

- [ ] Executei `npm run build` e commitei assets
- [ ] Executei `bash scripts/pre-deploy-validation.sh` e passou
- [ ] `.env.production` existe na VM e tem `APP_ENV=production` + `APP_DEBUG=false`
- [ ] Não há mudanças não commitadas

### Remotamente (durante/após deploy)

- [ ] Health check passou
- [ ] Acessar https://app.oravel.com.br/login no navegador
- [ ] CSS e JavaScript estão carregando
- [ ] Não há erros de "localhost:5173" no console do navegador (F12)

### Se algo der errado

#### Cenário 1: Pre-deploy validation falhou

```bash
# Causa mais comum: faltou fazer build dos assets
npm run build

# Validar manifest.json
cat public/build/manifest.json | jq '.' # deve ser um JSON válido

# Tentar novamente
bash scripts/pre-deploy-validation.sh
```

#### Cenário 2: Health check falhou

```bash
# Conectar em PROD
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# Verificar .env.production
sudo -u www-data cat /var/www/oravel/.env.production | grep "APP_"

# Rodar vite:check lá
sudo -u www-data bash -c 'cd /var/www/oravel && HOME=/tmp php artisan vite:check --strict'

# Se APP_ENV≠production ou APP_DEBUG=true, corrigir:
sudo -u www-data nano /var/www/oravel/.env.production
```

#### Cenário 3: PROD está quebrada (assets não carregam)

```bash
# Conectar em PROD
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# Verificar qual é o problema
sudo -u www-data bash -c 'cd /var/www/oravel && HOME=/tmp php artisan vite:check'

# Verificar se manifest.json existe
ls -la /var/www/oravel/public/build/

# Verificar se há um dev server ativo (não deve ter!)
ps aux | grep -i vite

# Se tudo está lá mas ainda quebrado, limpar cache
sudo -u www-data bash -c 'cd /var/www/oravel && HOME=/tmp php artisan optimize:clear'
sudo -u www-data bash -c 'cd /var/www/oravel && HOME=/tmp php artisan config:cache'
```

## Histórico de incidentes

- **2026-08-XX**: PROD tentou carregar de localhost:5173, página ficou sem CSS
  - Causa: `.env.production` tinha `APP_ENV=local`
  - Solução: Pre-deploy validation + health check implementados

## Referências

- Laravel Vite Plugin: https://laravel.com/docs/vite
- Filament Assets: https://filamentphp.com/docs/3.x/installation
- App config: `/home/oravel/oravel/config/app.php`
- Vite config: `/home/oravel/oravel/vite.config.js`
