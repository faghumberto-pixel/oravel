#!/bin/bash

set -e

BRANCH=${1:-main}
VM_INSTANCE="oravel-prod"
VM_ZONE="southamerica-east1-c"
PROD_PATH="/var/www/oravel"
BACKUP_DIR="/var/backups"
# gcloud compute ssh conecta como o usuario "oravel" (OS Login), que nao e
# dono nem de PROD_PATH (faghumberto:faghumberto) nem de BACKUP_DIR (root:root).
# Backup roda via sudo (root); o resto roda como faghumberto via sudo -u, para
# preservar o dono dos arquivos da aplicacao.
APP_USER="faghumberto"

echo "🚀 INICIANDO DEPLOY PARA PRODUÇÃO"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Branch: $BRANCH"
echo "VM: $VM_INSTANCE ($VM_ZONE)"
echo "Path: $PROD_PATH"
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

# Resto roda como $APP_USER, dono de $PROD_PATH
sudo -u $APP_USER bash -c '
set -e
cd $PROD_PATH

echo "📥 Puxando código..."
git pull origin $BRANCH

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

echo "✨ DEPLOY CONCLUÍDO COM SUCESSO!"
'
REMOTE_EOF
)

# 4. Conecta na VM via gcloud e roda o script remoto
echo "🔌 Conectando na VM via gcloud compute ssh..."
gcloud compute ssh "$VM_INSTANCE" --zone="$VM_ZONE" --command="$REMOTE_SCRIPT"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ DEPLOY FINALIZADO!"
echo "🌐 Acesse: https://app.oravel.com.br"
