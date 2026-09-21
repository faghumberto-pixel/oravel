#!/bin/bash

###############################################################################
# Oravel PROD Provisioning Script
# Instala toda infraestrutura necessária para rodar Oravel em VM limpa
#
# Uso: sudo bash provision-prod.sh
###############################################################################

set -e

echo "🚀 INICIANDO PROVISIONING DE PROD"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

PROD_PATH="/var/www/oravel"
APP_USER="www-data"
GIT_REPO="https://github.com/faghumberto-pixel/oravel.git"
GIT_BRANCH="main"
DB_USER="oravel_user"
DB_PASSWORD="1915"
DB_NAME="oravel_db"
DB_HOST="127.0.0.1"

# ============================================================================
# 1. ATUALIZAR SISTEMA
# ============================================================================
echo "📦 [1/10] Atualizando pacotes do sistema..."
apt-get update
apt-get upgrade -y

# ============================================================================
# 2. INSTALAR POSTGRESQL
# ============================================================================
echo "🗄️  [2/10] Instalando PostgreSQL..."
apt-get install -y postgresql postgresql-contrib postgresql-client
systemctl start postgresql
systemctl enable postgresql

# Criar database e user PostgreSQL
echo "📋 Criando database e user PostgreSQL..."
sudo -u postgres psql <<EOF
CREATE USER $DB_USER WITH PASSWORD '$DB_PASSWORD' CREATEDB;
CREATE DATABASE $DB_NAME OWNER $DB_USER;
ALTER USER $DB_USER CREATEDB;
EOF

echo "✅ PostgreSQL pronto"

# ============================================================================
# 3. INSTALAR NGINX
# ============================================================================
echo "🌐 [3/10] Instalando Nginx..."
apt-get install -y nginx
systemctl stop nginx  # Vai ser configurado depois
systemctl enable nginx

echo "✅ Nginx instalado"

# ============================================================================
# 4. INSTALAR PHP 8.4 + EXTENSÕES
# ============================================================================
echo "🐘 [4/10] Instalando PHP 8.4 + extensões..."
apt-get install -y \
  php8.4 \
  php8.4-fpm \
  php8.4-cli \
  php8.4-common \
  php8.4-pgsql \
  php8.4-mysql \
  php8.4-sqlite3 \
  php8.4-xml \
  php8.4-dom \
  php8.4-gd \
  php8.4-curl \
  php8.4-mbstring \
  php8.4-zip \
  php8.4-bcmath \
  php8.4-json \
  php8.4-intl \
  php8.4-iconv

systemctl start php8.4-fpm
systemctl enable php8.4-fpm

echo "✅ PHP 8.4 pronto com todas extensões"

# ============================================================================
# 5. INSTALAR FERRAMENTAS
# ============================================================================
echo "🛠️  [5/10] Instalando ferramentas..."
apt-get install -y \
  git \
  curl \
  wget \
  nano \
  vim \
  htop \
  tmux \
  unzip \
  zip

echo "✅ Ferramentas instaladas"

# ============================================================================
# 6. INSTALAR COMPOSER
# ============================================================================
echo "📦 [6/10] Instalando Composer..."
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
chmod +x /usr/local/bin/composer

echo "✅ Composer instalado"

# ============================================================================
# 7. CLONAR REPOSITÓRIO
# ============================================================================
echo "📥 [7/10] Clonando repositório..."

if [ -d "$PROD_PATH" ]; then
    echo "⚠️  Diretório $PROD_PATH já existe. Usando diretório existente."
else
    mkdir -p $(dirname $PROD_PATH)
    git clone --branch $GIT_BRANCH $GIT_REPO $PROD_PATH
fi

cd $PROD_PATH
chown -R $APP_USER:$APP_USER $PROD_PATH
chmod -R 755 $PROD_PATH

echo "✅ Repositório pronto"

# ============================================================================
# 8. INSTALAR DEPENDÊNCIAS PHP
# ============================================================================
echo "📦 [8/10] Instalando dependências do Composer..."
sudo -u $APP_USER HOME=/tmp composer install --no-dev --optimize-autoloader --no-interaction

echo "✅ Dependências instaladas"

# ============================================================================
# 9. CONFIGURAR .ENV
# ============================================================================
echo "⚙️  [9/10] Configurando variáveis de ambiente..."

if [ ! -f "$PROD_PATH/.env" ]; then
    cp "$PROD_PATH/.env.example" "$PROD_PATH/.env"
    echo "✅ .env criado do template"
else
    echo "⚠️  .env já existe"
fi

# Atualizar variáveis críticas
sed -i "s|APP_ENV=.*|APP_ENV=production|" $PROD_PATH/.env
sed -i "s|APP_DEBUG=.*|APP_DEBUG=false|" $PROD_PATH/.env
sed -i "s|APP_URL=.*|APP_URL=https://app.oravel.com.br|" $PROD_PATH/.env
sed -i "s|DB_HOST=.*|DB_HOST=$DB_HOST|" $PROD_PATH/.env
sed -i "s|DB_PORT=.*|DB_PORT=5432|" $PROD_PATH/.env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" $PROD_PATH/.env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" $PROD_PATH/.env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|" $PROD_PATH/.env
sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=pgsql|" $PROD_PATH/.env

# Gerar APP_KEY se necessário
if grep -q "APP_KEY=$" $PROD_PATH/.env; then
    sudo -u $APP_USER HOME=/tmp php $PROD_PATH/artisan key:generate --force
fi

chown $APP_USER:$APP_USER $PROD_PATH/.env
chmod 644 $PROD_PATH/.env

echo "✅ .env configurado"

# ============================================================================
# 10. RODAR MIGRATIONS E SETUP
# ============================================================================
echo "🗄️  [10/10] Rodando migrations..."

cd $PROD_PATH
sudo -u $APP_USER HOME=/tmp php artisan migrate --force
sudo -u $APP_USER HOME=/tmp php artisan filament:upgrade
sudo -u $APP_USER HOME=/tmp php artisan cache:clear
sudo -u $APP_USER HOME=/tmp php artisan config:cache
sudo -u $APP_USER HOME=/tmp php artisan route:cache
sudo -u $APP_USER HOME=/tmp php artisan view:clear

echo "✅ Migrations e cache prontos"

# ============================================================================
# CONFIGURAR NGINX VHOST
# ============================================================================
echo "🌐 Configurando Nginx vhost..."

# Remover Apache2 se existir
systemctl stop apache2 2>/dev/null || true
systemctl disable apache2 2>/dev/null || true
apt-get remove -y apache2 2>/dev/null || true

# Criar vhost config
cat > /etc/nginx/sites-available/oravel << 'NGINX_EOF'
server {
    listen 80;
    listen [::]:80;
    server_name _;

    root /var/www/oravel/public;
    index index.php;

    # Fotos de campo têm 4-8 MB; o padrão do nginx (1 MB) barra o upload com 413.
    client_max_body_size 32M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    # Logs
    access_log /var/log/nginx/oravel_access.log;
    error_log /var/log/nginx/oravel_error.log;
}
NGINX_EOF

# Limites de upload do PHP-FPM (padrão do pacote: 2M/8M). Drop-in, sem tocar no php.ini do pacote.
cat > /etc/php/8.4/fpm/conf.d/99-oravel-uploads.ini << 'PHPINI_EOF'
; Oravel: fotos de campo têm 4-8 MB
upload_max_filesize = 32M
post_max_size = 40M
PHPINI_EOF

# Habilitar vhost
ln -sf /etc/nginx/sites-available/oravel /etc/nginx/sites-enabled/oravel

# Testar config
nginx -t

# Iniciar nginx
systemctl start nginx
systemctl enable nginx

echo "✅ Nginx vhost configurado"

# ============================================================================
# CRIAR ESTRUTURA DE DIRETÓRIOS
# ============================================================================
echo "📁 Criando estrutura de diretórios..."

mkdir -p $PROD_PATH/storage/{logs,framework}
mkdir -p $PROD_PATH/bootstrap/cache
mkdir -p /var/backups

chown -R $APP_USER:$APP_USER $PROD_PATH/storage
chown -R $APP_USER:$APP_USER $PROD_PATH/bootstrap/cache
chmod -R 755 $PROD_PATH/storage
chmod -R 755 $PROD_PATH/bootstrap/cache

echo "✅ Estrutura de diretórios pronta"

# ============================================================================
# RESUMO FINAL
# ============================================================================
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ PROVISIONING CONCLUÍDO COM SUCESSO!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📋 RESUMO:"
echo "  • PostgreSQL: rodando em localhost:5432"
echo "  • Database: $DB_NAME (user: $DB_USER)"
echo "  • PHP 8.4-FPM: ativo"
echo "  • Nginx: ativo na porta 80"
echo "  • Aplicação: $PROD_PATH"
echo ""
echo "🔗 Próximos passos:"
echo "  1. Atualizar DNS para apontar para este servidor"
echo "  2. Configurar certificado SSL via Certbot"
echo "  3. Rodar: bash deploy.sh para deploy inicial"
echo ""
echo "📝 Comandos úteis:"
echo "  systemctl restart php8.4-fpm    # Reiniciar PHP"
echo "  systemctl restart nginx          # Reiniciar Nginx"
echo "  systemctl status postgresql      # Status PostgreSQL"
echo "  tail -f /var/log/nginx/oravel_error.log  # Ver erros Nginx"
echo "  tail -f $PROD_PATH/storage/logs/laravel.log  # Ver erros Laravel"
echo ""
