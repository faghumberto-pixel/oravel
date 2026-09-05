# ✅ PRÉ-DEPLOY CHECKLIST

Antes de fazer qualquer deploy, **OBRIGATÓRIO** passar por este checklist:

## 🔍 Código
- [ ] Todos os testes passando (`php artisan test`)
- [ ] Sem erros de sintaxe (`php artisan tinker` → `exit`)
- [ ] Sem warnings do Laravel (`php artisan make:request TestRequest`)
- [ ] Sem arquivos `.bak` ou `.orig` commitados

## 📋 Git
- [ ] Branch correto (`git branch`)
- [ ] Commits bem descritos (`git log --oneline -5`)
- [ ] Nenhuma mudança não-commitada (`git status`)
- [ ] Sincronizado com origin (`git fetch && git status`)

## 🗄️ Banco de Dados
- [ ] Migrations criadas se houver schema changes
- [ ] Migrations testadas em DEV
- [ ] Rollback pensado (tem `down()` em todas)

## 🎨 Assets Frontend (CRÍTICO!)
- [ ] Executou `npm run build`
- [ ] `public/build/manifest.json` existe
- [ ] `public/build/assets/` tem arquivos
- [ ] Commitou assets: `git add public/build && git commit`
- [ ] **NOVO:** Executou `bash scripts/pre-deploy-validation.sh` e passou ✅

## 🔐 Segurança
- [ ] `.env` NÃO commitado
- [ ] `.env.production` existe em PROD com `APP_ENV=production` + `APP_DEBUG=false`
- [ ] Secrets NÃO estão no código
- [ ] Não há `dd()` ou `dump()` no código
- [ ] Credenciais não expostas nos comentários

## 🧪 Testes
- [ ] Testou em DEV: `http://127.0.0.1:8000`
- [ ] Testou login
- [ ] Testou features (Central → Admin Panel)
- [ ] Testou bloqueio de acesso

## 📤 Deploy
- [ ] Network estável
- [ ] Horário de baixo uso (evitar horário de pico)
- [ ] Celular carregado (deploy pode demorar)
- [ ] **NOVO:** Sabe onde consultar logs (gcloud ssh)

---

## 🤖 Fluxo de segurança AUTOMÁTICO

Quando executar `./deploy.sh main`:

1. **Pre-deploy validation** → valida assets localmente
2. **Git push** → sobe para GitHub
3. **Remote deploy** → puxa em PROD, faz backup, migrations, etc
4. **Vite check** → valida APP_ENV/APP_DEBUG/assets em PROD
5. **Health check** → verifica se site está funcionando (localhost:5173 check)

Se qualquer passo falhar, o script para e avisa. **Não precisa fazer nada manualmente.**

---

**PRONTO? Execute:**
```bash
bash deploy.sh main
```

**EM CASO DE ERRO:**

```bash
# 1. Ler a saída do script (vai dizer qual step falhou)

# 2. Se foi pre-deploy validation (local):
bash scripts/pre-deploy-validation.sh   # corrigir problema local

# 3. Se foi health check (remoto, mas rodar localmente):
bash scripts/health-check-post-deploy.sh

# 4. Se precisa ver mais detalhes em PROD:
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap

# 5. Voltar para backup (último recurso):
gcloud compute ssh oravel-prod-new --zone=southamerica-east1-c --tunnel-through-iap --command="sudo cp -r /var/backups/oravel_backup_YYYYMMDD_HHMMSS /var/www/oravel"
```

---

## 📚 Documentação

- **Problema de localhost:5173:** `/docs/VITE_PRODUCTION_SAFETY.md`
- **Scripts detalhados:** `/scripts/README.md`
- **Comando artisan:** `php artisan vite:check`
