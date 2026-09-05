#!/bin/bash

set -e

BRANCH=${1:-main}
# oravel-prod (nome antigo) foi TERMINATED em 2026-08-19 apos o incidente do
# rootkit; a VM ativa e oravel-prod-new, reprovisionada do zero.
VM_INSTANCE="oravel-prod-new"
VM_ZONE="southamerica-east1-c"
PROD_PATH="/var/www/oravel"
BACKUP_DIR="/var/backups"
# gcloud compute ssh conecta como o usuario "oravel" (OS Login), que nao e
# dono nem de PROD_PATH nem de BACKUP_DIR (root:root). Na VM reprovisionada
# nao existe mais o usuario faghumberto - PROD_PATH pertence a www-data:www-data.
# Backup roda via sudo (root); o resto roda como www-data via sudo -u, para
# preservar o dono dos arquivos da aplicacao. www-data tem shell nologin, mas
# "sudo -u www-data bash -c '...'" funciona normalmente.
# HOME=/var/www nao e gravavel por www-data (psysh/composer/git precisam
# escrever cache/config), entao forcamos HOME=/tmp no bloco remoto.
APP_USER="www-data"

echo "🚀 INICIANDO DEPLOY PARA PRODUÇÃO"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Branch: $BRANCH"
echo "VM: $VM_INSTANCE ($VM_ZONE)"
echo "Path: $PROD_PATH"
echo ""

# PRÉ-DEPLOY: Validação local de assets
# Previne que PROD seja deployada sem assets, ou com APP_ENV=local/APP_DEBUG=true
echo "🔍 Executando pre-deploy validation..."
if [[ ! -f "scripts/pre-deploy-validation.sh" ]]; then
    echo "⚠️  AVISO: scripts/pre-deploy-validation.sh não encontrado, pulando validação"
else
    if ! bash scripts/pre-deploy-validation.sh; then
        echo "❌ PRÉ-DEPLOY VALIDATION FALHOU!"
        echo ""
        echo "Corrija os problemas acima antes de fazer deploy."
        exit 1
    fi
fi
echo ""

# 1. Verifica se há mudanças não commitadas
if ! git diff-index --quiet HEAD --; then
    echo "❌ ERRO: Existem mudanças não commitadas!"
    echo "Execute: git add . && git commit -m 'sua mensagem'"
    exit 1
fi

echo "✅ Git clean"

# 2. Faz push para o branch
echo "📤 Fazendo push para $BRANCH..."
git push origin $BRANCH

# 3. Monta o script remoto (variáveis locais como $PROD_PATH e $BRANCH são
# expandidas aqui; variáveis com \$ só existem no lado remoto, ex: \$BACKUP_FILE).
# Assets do frontend (public/build) já vêm buildados e commitados no git —
# a VM não tem node/npm instalado, então não há passo de "npm run build" aqui.
REMOTE_SCRIPT=$(cat <<REMOTE_EOF
set -e

# Faz backup (root e dono de $BACKUP_DIR)
BACKUP_FILE="$BACKUP_DIR/oravel_backup_\$(date +%Y%m%d_%H%M%S)"
echo "💾 Criando backup: \$BACKUP_FILE"
sudo cp -r $PROD_PATH \$BACKUP_FILE

# Retenção: mantém só os 5 backups mais recentes (cada um é uma cópia completa
# de $PROD_PATH — sem limpeza, isso já encheu o disco da VM uma vez, 2026-07).
echo "🧹 Limpando backups antigos (mantendo os 5 mais recentes)..."
sudo bash -c "cd $BACKUP_DIR && ls -dt oravel_backup_*/ 2>/dev/null | tail -n +6 | xargs -r rm -rf --"

# Resto roda como $APP_USER, dono de $PROD_PATH
# HOME=/tmp: /var/www (HOME padrao de www-data) nao e gravavel por ele.
sudo -u $APP_USER HOME=/tmp bash -c '
set -e
cd $PROD_PATH

echo "📥 Puxando código..."
git pull --rebase=false origin $BRANCH

echo "🔍 Validando PHP..."
find app -name "*.php" -exec php -l {} +

echo "📦 Instalando dependências do Composer..."
composer install --no-dev --optimize-autoloader --no-interaction

if compgen -G "database/migrations/*.php" > /dev/null; then
    echo "🗄️ Rodando migrations..."
    php artisan migrate --force
fi

echo "🧹 Limpando cache..."
php artisan optimize:clear
php artisan config:cache

echo "🔍 Verificando integridade de Vite assets..."
php artisan vite:check --strict || {
    echo "⚠️  AVISO: Possível problema com Vite assets detectado"
    echo "   Verifique a configuração de ambiente acima"
}

echo "✨ DEPLOY CONCLUÍDO COM SUCESSO!"
'
REMOTE_EOF
)

# 4. Conecta na VM via gcloud e roda o script remoto
# --tunnel-through-iap: a porta 22 publica foi fechada (2026-08-18, pos-incidente
# de mineracao); SSH so e permitido via IAP (range 35.235.240.0/20).
echo "🔌 Conectando na VM via gcloud compute ssh (IAP)..."
gcloud compute ssh "$VM_INSTANCE" --zone="$VM_ZONE" --tunnel-through-iap --command="$REMOTE_SCRIPT"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ DEPLOY FINALIZADO!"
echo "🌐 Acesse: https://app.oravel.com.br"
echo ""

# 5. PÓS-DEPLOY: Health check automatizado
# Verifica se PROD está funcional e não está tentando usar Vite dev server
echo "🏥 Executando health check pós-deploy..."
if [[ ! -f "scripts/health-check-post-deploy.sh" ]]; then
    echo "⚠️  AVISO: scripts/health-check-post-deploy.sh não encontrado, pulando health check"
else
    if bash scripts/health-check-post-deploy.sh "https://app.oravel.com.br"; then
        echo ""
        echo "✅ HEALTH CHECK PASSOU!"
    else
        echo ""
        echo "⚠️  AVISO: Health check detectou problemas"
        echo "   Verifique o site manualmente em https://app.oravel.com.br"
        echo "   ou rode o health check novamente com:"
        echo "   bash scripts/health-check-post-deploy.sh"
    fi
fi
