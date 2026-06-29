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

## 🔐 Segurança
- [ ] `.env` NÃO commitado
- [ ] Secrets NÃO estão no código
- [ ] Não há `dd()` ou `dump()` no código
- [ ] Credenciais não expostas nos comentários

## 🧪 Testes
- [ ] Testou em DEV: `http://127.0.0.1:8000`
- [ ] Testou login
- [ ] Testou features (Central → Admin Panel)
- [ ] Testou bloqueio de acesso

## 📤 Deploy
- [ ] Backup feito em PROD
- [ ] Network estável
- [ ] Horário de baixo uso (evitar horário de pico)
- [ ] Celular carregado (deploy pode demorar)

---

**PRONTO? Execute:**
```bash
./deploy.sh main
```

**EM CASO DE ERRO:**
```bash
# Volta para backup
ssh root@app.oravel.com.br "cp -r /var/backups/oravel_backup_YYYYMMDD_HHMMSS /var/www/oravel"
```
