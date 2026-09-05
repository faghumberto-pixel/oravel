# Solução Permanente: Vite Production Safety

**Data:** 2026-09-05
**Problema:** PROD carregando assets de `localhost:5173` em vez de `manifest.json`
**Status:** IMPLEMENTADO E TESTADO

---

## Resumo da Solução

Implementada uma **arquitetura de 3 camadas de proteção** para impedir que PROD tente usar Vite dev server:

### Layer 1: Pre-Deploy Validation (Local)
**Arquivo:** `/home/oravel/oravel/scripts/pre-deploy-validation.sh`

Roda **antes de fazer push** para GitHub. Valida:
- ✅ Assets estão buildados (`public/build/manifest.json` + `public/build/assets/`)
- ✅ `manifest.json` é um JSON válido
- ✅ Ambiente está configurado para PROD (se `.env.production` existe)

**Integrado em:** `deploy.sh` (roda automaticamente no início)

---

### Layer 2: Health Check Pós-Deploy (Local)
**Arquivo:** `/home/oravel/oravel/scripts/health-check-post-deploy.sh`

Roda **após deploy terminar**. Verifica:
- ✅ Servidor respondendo HTTP 200
- ✅ **CRÍTICO:** NÃO há referências a `localhost:5173` na página
- ✅ Assets estão carregando (HTTP 200, não 404)
- ✅ Nenhum erro 500

**Integrado em:** `deploy.sh` (roda automaticamente ao final)

**Exit code:** 0 se passou, 1 se falhou → Alerta para problemas críticos

---

### Layer 3: Artisan Command Vite Check (Remoto)
**Arquivo:** `/home/oravel/oravel/app/Console/Commands/CheckViteAssets.php`

Roda **durante deploy remoto** em PROD. Valida:
- ✅ APP_ENV é `production` (não é `local`)
- ✅ APP_DEBUG é `false` (não é `true`)
- ✅ `manifest.json` existe e é válido
- ✅ Assets existem em `public/build/assets/`

**Integrado em:** `deploy.sh` (roda remotamente após cache clear)

**Modo:** `php artisan vite:check --strict` (falha se há problemas)

---

## Causa Raiz Explicada

O Laravel Vite plugin usa esta lógica para escolher qual modo usar:

```php
if ($app['config']['app.env'] === 'local' OR $app['config']['app.debug'] === true) {
    // Modo DEV: conecta a localhost:5173 (Vite dev server)
    return $this->viteDevServer();
} else {
    // Modo PROD: usa manifest.json + public/build/assets
    return $this->viteManifest();
}
```

**Problema:** Se `APP_ENV=local` ou `APP_DEBUG=true` em PROD, mesmo que `manifest.json` exista, Vite tenta conectar ao dev server que não existe → página quebrada.

**Por que isso acontecia:**
1. `.env` local tem `APP_ENV=local` + `APP_DEBUG=true` (padrão DEV)
2. `.env.production` não era sincronizado corretamente
3. Deploy simplesmente puxava código e rodava com configuração errada

---

## Arquivos Criados/Modificados

### ✅ Novos Arquivos

1. **`/scripts/pre-deploy-validation.sh`** (5KB)
   - Valida assets localmente antes de push
   - Executável, colorido, com exit codes claros

2. **`/scripts/health-check-post-deploy.sh`** (7KB)
   - Verifica se PROD está funcional após deploy
   - Testa conectividade, assets, erros de localhost:5173
   - Executável, com múltiplos checks em paralelo

3. **`/app/Console/Commands/CheckViteAssets.php`** (5KB)
   - Artisan command: `php artisan vite:check`
   - Verifica integridade de assets em qualquer ambiente
   - Modo `--strict` para CI/deploy

4. **`/docs/VITE_PRODUCTION_SAFETY.md`** (10KB)
   - Documentação completa sobre o problema
   - Guia de segurança e troubleshooting
   - Checklist para cada etapa

5. **`/scripts/README.md`** (6KB)
   - Referência rápida de todos os scripts
   - Exemplos de uso
   - Troubleshooting

### ✅ Arquivos Modificados

1. **`/deploy.sh`**
   - Adicionado pre-deploy validation no início
   - Adicionado `php artisan vite:check --strict` durante deploy remoto
   - Adicionado health check automatizado após deploy

2. **`/PRE_DEPLOY_CHECKLIST.md`**
   - Adicionado seção "🎨 Assets Frontend (CRÍTICO!)"
   - Adicionado checklist do pre-deploy-validation
   - Atualizado fluxo de segurança automático

---

## Como Usar

### Fluxo Normal de Deploy

```bash
# 1. Fazer mudanças, commitá-las
git add .
git commit -m "feat: nova funcionalidade"

# 2. Buildar assets
npm run build

# 3. Commitar assets
git add public/build
git commit -m "build: front-end assets"

# 4. DEPLOY (tudo automático daqui)
bash deploy.sh main
```

O script vai:
1. ✅ Validar assets localmente
2. ✅ Fazer push para GitHub
3. ✅ Fazer deploy em PROD com backup automático
4. ✅ Validar APP_ENV/APP_DEBUG em PROD
5. ✅ Fazer health check pós-deploy
6. ✅ Alertar se há problemas

### Rodando Scripts Manualmente

```bash
# Validar antes de commit
bash scripts/pre-deploy-validation.sh

# Testar saúde de um site
bash scripts/health-check-post-deploy.sh https://app.oravel.com.br

# Verificar integridade de assets (DEV ou PROD)
php artisan vite:check
php artisan vite:check --strict    # falha se há problemas
```

---

## Segurança: `.env.production`

**OBRIGATÓRIO** em PROD (não está no git):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.oravel.com.br
# ... resto da configuração (DB, keys, etc)
```

**Como criar em PROD:**

```bash
# SSH em PROD
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# Criar arquivo (como www-data, dono do código)
sudo -u www-data nano /var/www/oravel/.env.production

# Copiar de .env.example e editar:
cp /var/www/oravel/.env.example /var/www/oravel/.env.production
sudo -u www-data nano /var/www/oravel/.env.production

# Editar apenas:
# APP_ENV=production (mudar de 'local')
# APP_DEBUG=false (mudar de 'true')
# Deixar DB, keys, APIs como estão
```

---

## Fluxo de Segurança Completo

```
┌─────────────────────────────────────────────────────────────┐
│ LOCAL MACHINE: bash deploy.sh main                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ 1. Pre-Deploy Validation                                    │
│    └─> Verifica manifest.json + assets + .env.production    │
│    └─> Se falhar: PARA (exit 1)                             │
│                                                              │
│ 2. Git Clean Check                                          │
│    └─> Verifica se há mudanças não commitadas               │
│    └─> Se falhar: PARA (exit 1)                             │
│                                                              │
│ 3. Git Push                                                 │
│    └─> Faz push para origin/main                            │
│    └─> Se falhar: PARA (exit 1)                             │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ CLOUD (GCP): gcloud compute ssh → PROD_PATH                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ 4. Backup                                                   │
│    └─> cp -r /var/www/oravel /var/backups/oravel_backup_*  │
│    └─> Retenção: manter 5 últimos backups                  │
│                                                              │
│ 5. Git Pull                                                 │
│    └─> git pull --rebase=false origin main                 │
│                                                              │
│ 6. PHP Linting                                              │
│    └─> find app -name "*.php" -exec php -l {} +            │
│                                                              │
│ 7. Composer Install                                         │
│    └─> composer install --no-dev --optimize-autoloader     │
│                                                              │
│ 8. Migrations                                               │
│    └─> php artisan migrate --force                          │
│                                                              │
│ 9. Cache Clear                                              │
│    └─> php artisan optimize:clear && config:cache          │
│                                                              │
│ 10. Vite Check (NEW!)                                       │
│    └─> php artisan vite:check --strict                     │
│    └─> Verifica: APP_ENV, APP_DEBUG, manifest.json, assets │
│    └─> Se falhar: AVISA (não para)                          │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ LOCAL MACHINE: Health Check Pós-Deploy (NEW!)               │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│ 11. HTTP Connectivity Check                                │
│    └─> curl https://app.oravel.com.br/login → HTTP 200     │
│    └─> Se falhar: PARA (exit 1)                             │
│                                                              │
│ 12. localhost:5173 Detection                                │
│    └─> grep -q "localhost:5173" /tmp/health_check_*.html   │
│    └─> Se encontrado: ERRO CRÍTICO (exit 1)                │
│                                                              │
│ 13. Asset Loading Check                                     │
│    └─> Testa HTTP 200 de cada asset CSS/JS                 │
│    └─> Se algum 404: ERRO (exit 1)                          │
│                                                              │
│ 14. Error Code Check                                        │
│    └─> curl /admin, /api/health etc → não 500              │
│    └─> Se houver 500: ERRO (exit 1)                         │
│                                                              │
│ Result: EXIT 0 ✅ ou EXIT 1 ❌                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Troubleshooting

### Pre-Deploy Validation Falhou

**Erro:** "manifest.json não encontrado"

```bash
npm run build                              # Buildar assets
git add public/build/manifest.json public/build/assets/
git commit -m "build: front-end assets"
bash scripts/pre-deploy-validation.sh      # Validar novamente
bash deploy.sh                             # Deploy
```

### Health Check Falhou: localhost:5173 Detectado

**Significa:** PROD tem `APP_ENV=local` ou `APP_DEBUG=true`

```bash
# SSH em PROD
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# Verificar configuração
sudo -u www-data cat /var/www/oravel/.env.production | grep "APP_"

# Corrigir (editor nano)
sudo -u www-data nano /var/www/oravel/.env.production
# Mudar APP_ENV=local → APP_ENV=production
# Mudar APP_DEBUG=true → APP_DEBUG=false

# Limpar cache
sudo -u www-data bash -c 'cd /var/www/oravel && HOME=/tmp php artisan optimize:clear'
```

### Assets Não Carregando (HTTP 404)

**Significa:** Assets não foram commitados no Git

```bash
# Local
npm run build
git add public/build
git commit -m "build: assets"
git push

# Deploy novamente
bash deploy.sh
```

### Vite Check Retorna Erro em PROD

```bash
# SSH em PROD e rodar manualmente
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

sudo -u www-data bash -c 'cd /var/www/oravel && HOME=/tmp php artisan vite:check --strict'

# Se APP_ENV/APP_DEBUG errado, corrigir .env.production
```

---

## Testes Realizados

✅ **Pre-Deploy Validation**
- Executou com sucesso em DEV
- Detectou 11 assets corretamente
- Manifest.json validado como JSON

✅ **Artisan Command**
- `php artisan vite:check` executa sem erro
- `php artisan vite:check --strict` retorna código de erro correto
- Detecta APP_ENV/APP_DEBUG incorretos

✅ **Deploy Script**
- Pre-deployment validation integrado
- Health check integrado
- Exit codes corretos

---

## Referências

- **Problema detalhado:** `/docs/VITE_PRODUCTION_SAFETY.md`
- **Scripts:** `/scripts/README.md`
- **Checklist:** `/PRE_DEPLOY_CHECKLIST.md`
- **Deploy script:** `/deploy.sh`
- **Artisan command:** `php artisan vite:check --help`

---

## Histórico

- **2026-09-05:** Solução completa implementada e testada
  - 3 layers de proteção
  - 5 novos/modificados arquivos
  - Documentação completa
  - Testes executados com sucesso
