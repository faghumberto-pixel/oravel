# Scripts de Deploy e Manutenção

## Sumário

Scripts auxiliares para deploy, validação e health checks.

## Scripts

### `pre-deploy-validation.sh`

**Uso:** `bash scripts/pre-deploy-validation.sh`

Valida localmente (antes de fazer push) se o código está pronto para PROD:

- ✅ `manifest.json` existe e é JSON válido
- ✅ Assets existem em `public/build/assets/`
- ✅ `.env.production` tem `APP_ENV=production` (se existir)
- ✅ `.env.production` NÃO tem `APP_DEBUG=true` (se existir)

**Exit code:** 0 se passou, 1 se falhou

**Integração:** Roda automaticamente no início de `deploy.sh`

---

### `health-check-post-deploy.sh`

**Uso:** 
```bash
bash scripts/health-check-post-deploy.sh                                    # testa app.oravel.com.br
bash scripts/health-check-post-deploy.sh https://seu-dominio.com          # testa URL customizada
```

Verifica se PROD está funcional após deploy:

- ✅ Servidor respondendo HTTP 200
- ✅ NÃO há referências a `localhost:5173` (Vite dev server)
- ✅ Assets carregando corretamente
- ✅ Nenhum erro 500

**Exit code:** 0 se passou, 1 se falhou

**Integração:** Roda automaticamente após `deploy.sh` terminar

---

### `../deploy.sh`

**Uso:** 
```bash
bash deploy.sh                    # faz deploy da branch main
bash deploy.sh seu-branch-name    # faz deploy de outra branch
```

Deploy completo para PROD:

1. **Pre-deploy validation** — valida se assets estão buildados
2. **Git check** — verifica mudanças não commitadas
3. **Git push** — faz push para origin
4. **Backup** — cria backup em `/var/backups` (retenção de 5)
5. **Git pull** — puxa código em PROD
6. **PHP linting** — valida syntax de arquivos PHP
7. **Composer install** — instala dependências
8. **Migrations** — roda migrations se houver
9. **Cache clear** — limpa cache Laravel
10. **Vite check** — valida integridade de assets em PROD
11. **Health check** — verifica se PROD está funcionando

**Fluxo de segurança:**

```
Local Pre-Deploy Validation
    ↓ (se passar)
Git Push to Origin
    ↓
Remote Deploy (Backup → Git Pull → Composer → Migrations → Cache Clear)
    ↓
Remote Vite Check
    ↓
Local Health Check
    ↓ (se passar)
✅ DEPLOY PRONTO
```

---

## Problema que estes scripts previnem

**CRÍTICO:** PROD não deve tentar carregar assets de `localhost:5173` (Vite dev server).

Se isso acontecer, o site fica quebrado (CSS/JS não carregam) porque `localhost:5173` não existe em PROD.

**Causa raiz:**
- `APP_ENV=local` ou `APP_DEBUG=true` em PROD
- Vite plugin detecta e tenta usar dev server em vez de manifest.json

**Solução implementada:**

1. **Pre-deploy validation** bloqueia push sem assets
2. **Health check** detecta se está usando localhost:5173
3. **Vite check** valida APP_ENV/APP_DEBUG em PROD

---

## Checklist de deploy seguro

### Antes de fazer push

```bash
# 1. Build assets localmente
npm run build

# 2. Commit assets
git add public/build/manifest.json public/build/assets/
git commit -m "build: front-end assets for deployment"

# 3. Validar antes de push (roda automaticamente, mas pode validar manualmente)
bash scripts/pre-deploy-validation.sh

# 4. Fazer deploy
bash deploy.sh
```

### Após deploy terminar

- Health check roda automaticamente
- Se passou: site está pronto em https://app.oravel.com.br
- Se falhou: verifique a saída, ou rode manualmente:
  ```bash
  bash scripts/health-check-post-deploy.sh
  ```

---

## Troubleshooting

### Pre-deploy validation falhou

**Problema:** "manifest.json não encontrado"

```bash
# Build assets
npm run build

# Tentar novamente
bash scripts/pre-deploy-validation.sh
```

**Problema:** ".env.production não tem APP_ENV=production"

```bash
# Verificar/editar .env.production
cat .env.production
# ou via SSH em PROD:
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap
sudo -u www-data cat /var/www/oravel/.env.production
```

---

### Health check falhou

**Problema:** "Página está tentando carregar de localhost:5173"

```bash
# Conectar em PROD
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# Verificar .env.production
sudo -u www-data cat /var/www/oravel/.env.production | grep "APP_"

# Se APP_ENV≠production ou APP_DEBUG=true, corrigir:
sudo -u www-data nano /var/www/oravel/.env.production

# Limpar cache
sudo -u www-data bash -c 'cd /var/www/oravel && HOME=/tmp php artisan optimize:clear'
```

**Problema:** "Assets carregando com erro (HTTP 404)"

```bash
# Conectar em PROD
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# Verificar se assets existem
ls -la /var/www/oravel/public/build/assets/

# Se vazio, assets não foram commitados. Refazer:
# 1. Localmente: npm run build && git add public/build && git commit
# 2. Deploy novamente: bash deploy.sh
```

---

## Documentação completa

Veja `/home/oravel/oravel/docs/VITE_PRODUCTION_SAFETY.md` para:
- Explicação detalhada da causa raiz
- Como configurar `.env.production` corretamente
- Histórico de incidentes
- Referências do Laravel Vite
