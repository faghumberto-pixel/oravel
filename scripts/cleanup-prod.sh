#!/bin/bash

# Cleanup script para remover arquivos problemáticos em PROD
# Executado manualmente quando git pull não remove arquivo corretamente

echo "🧹 LIMPANDO PRODUÇÃO..."

# Remover arquivo que Haiku criou e que causa 500
rm -f /var/www/oravel/app/Filament/Client/Pages/Auth/RequestPasswordReset.php && \
  echo "✅ Removido: RequestPasswordReset.php" || \
  echo "⚠️  Arquivo não encontrado (já removido?)"

# Remover seeders de teste que ficaram
rm -f /var/www/oravel/database/seeders/ClientPortalTestSeeder.php && \
  echo "✅ Removido: ClientPortalTestSeeder.php" || true

rm -f /var/www/oravel/database/seeders/DemoPortalClientsSeeder.php && \
  echo "✅ Removido: DemoPortalClientsSeeder.php" || true

rm -f /var/www/oravel/diagnose_client_portal.php && \
  echo "✅ Removido: diagnose_client_portal.php" || true

# Clear cache
echo "🔄 Limpando cache do Laravel..."
cd /var/www/oravel
sudo -u www-data HOME=/tmp php artisan cache:clear 2>/dev/null
sudo -u www-data HOME=/tmp php artisan config:clear 2>/dev/null
sudo -u www-data HOME=/tmp php artisan view:clear 2>/dev/null

echo "✅ Limpeza concluída"
