#!/bin/bash

set -e

BRANCH=${1:-main}
PROD_HOST="root@app.oravel.com.br"
PROD_PATH="/var/www/oravel"
BACKUP_DIR="/var/backups"

echo "🚀 INICIANDO DEPLOY PARA PRODUÇÃO"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "Branch: $BRANCH"
echo "Host: $PROD_HOST"
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

# 3. SSH para PROD
ssh $PROD_HOST << PROD_COMMANDS
set -e
cd $PROD_PATH

# Faz backup
BACKUP_FILE="$BACKUP_DIR/oravel_backup_\$(date +%Y%m%d_%H%M%S)"
echo "💾 Criando backup: \$BACKUP_FILE"
cp -r . \$BACKUP_FILE

# Puxa código
echo "📥 Puxando código..."
git pull origin $BRANCH

# Verifica syntax
echo "🔍 Validando PHP..."
find app -name "*.php" -exec php -l {} +

# Roda migrations (se houver)
if [ -f database/migrations/*.php ]; then
    echo "🗄️ Rodando migrations..."
    php artisan migrate --force
fi

# Limpa cache
echo "🧹 Limpando cache..."
php artisan optimize:clear
php artisan config:cache

# Valida features
echo "✅ Validando features..."
php artisan test:features

echo "✨ DEPLOY CONCLUÍDO COM SUCESSO!"

PROD_COMMANDS

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ DEPLOY FINALIZADO!"
echo "🌐 Acesse: https://app.oravel.com.br"
