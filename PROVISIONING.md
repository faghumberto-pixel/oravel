# Provisioning de Produção - Oravel

Guia para provisionar uma VM limpa de Debian 13 para rodar Oravel em produção.

## Uso Rápido

```bash
sudo bash scripts/provision-prod.sh
```

O script irá:

1. ✅ Atualizar sistema operacional
2. ✅ Instalar PostgreSQL 17
3. ✅ Instalar Nginx
4. ✅ Instalar PHP 8.4 + todas extensões necessárias
5. ✅ Instalar Composer
6. ✅ Clonar repositório Git
7. ✅ Criar database PostgreSQL
8. ✅ Configurar .env
9. ✅ Rodar migrations
10. ✅ Configurar Nginx vhost
11. ✅ Criar estrutura de diretórios

**Tempo esperado:** 10-15 minutos

## Pré-requisitos

- VM limpa rodando Debian 13
- Acesso root ou sudo
- Conexão de internet
- GitHub SSH key configurada (para clonar repo privado)

## O que o Script Faz Automaticamente

### 1. Sistema Operacional
- Atualiza apt-get
- Instala pacotes essenciais

### 2. Banco de Dados
- Instala PostgreSQL 17
- Cria database `oravel_db`
- Cria user `oravel_user` com password `1915`

### 3. Servidor Web
- Instala Nginx
- Remove Apache2 se existir
- Configura vhost em `/etc/nginx/sites-available/oravel`

### 4. PHP
- Instala PHP 8.4-FPM
- Instala extensões: pgsql, mysql, sqlite3, xml, dom, gd, curl, mbstring, zip, bcmath, json, intl, iconv

### 5. Aplicação
- Clona repositório Git
- Instala Composer
- Instala dependências PHP
- Copia `.env.example` → `.env`
- Configura variáveis de ambiente
- Roda migrations
- Cria cache de config e routes

### 6. Diretórios
- Cria estrutura em `/var/www/oravel`
- Define permissões corretas para www-data

## Depois do Provisioning

### 1. Verificar Status

```bash
# PostgreSQL
systemctl status postgresql

# PHP-FPM
systemctl status php8.4-fpm

# Nginx
systemctl status nginx
```

### 2. Testar Aplicação

```bash
curl http://localhost/cliente/entrar-sem-senha
```

Deve retornar HTML com título "Oravel" e texto "Entrar sem senha".

### 3. Configurar SSL

```bash
sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d app.oravel.com.br
```

### 4. Deploy Inicial

```bash
cd /var/www/oravel
bash deploy.sh
```

## Troubleshooting

### PostgreSQL não conecta
```bash
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

### Nginx porta 80 ocupada
```bash
sudo killall -9 nginx
sudo killall -9 apache2
sudo systemctl start nginx
```

### Permissões erradas
```bash
sudo chown -R www-data:www-data /var/www/oravel
sudo chmod -R 755 /var/www/oravel
```

### Ver logs
```bash
# Nginx error
tail -f /var/log/nginx/oravel_error.log

# Laravel error
tail -f /var/www/oravel/storage/logs/laravel.log

# PHP-FPM
tail -f /var/log/php8.4-fpm.log
```

## Variáveis de Ambiente

O script configura automaticamente:

| Variável | Valor | Notas |
|----------|-------|-------|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | |
| `APP_URL` | `https://app.oravel.com.br` | Atualizar conforme necessário |
| `DB_CONNECTION` | `pgsql` | |
| `DB_HOST` | `127.0.0.1` | |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | `oravel_db` | |
| `DB_USERNAME` | `oravel_user` | |
| `DB_PASSWORD` | `1915` | Alterar em produção! |

## Segurança

⚠️ **IMPORTANTE:**

1. Alterar `DB_PASSWORD` em `.env` após provisioning
2. Alterar `APP_KEY` (gerado automaticamente)
3. Configurar SSH key properly
4. Habilitar firewall
5. Configurar certificado SSL com Certbot
6. Desabilitar root login SSH

## Customização

Se precisar modificar o script:

1. `PROD_PATH`: local de instalação (padrão: `/var/www/oravel`)
2. `DB_USER`, `DB_PASSWORD`, `DB_NAME`: credenciais PostgreSQL
3. `GIT_REPO`, `GIT_BRANCH`: qual repositório clonar
4. `APP_URL`: URL da aplicação

Edite as variáveis no topo do script `provision-prod.sh`.

## Referência Rápida

```bash
# Ver status de tudo
sudo systemctl status postgresql php8.4-fpm nginx

# Reiniciar serviços
sudo systemctl restart postgresql php8.4-fpm nginx

# Ver logs em tempo real
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/php8.4-fpm.log

# Deploy
cd /var/www/oravel && sudo bash deploy.sh

# Artisan commands
cd /var/www/oravel
sudo -u www-data php artisan migrate
sudo -u www-data php artisan cache:clear
```

## Histórico de Mudanças

### v1.0 (2026-09-17)
- Script inicial de provisioning
- Suporte para Debian 13
- PostgreSQL 17
- PHP 8.4
- Nginx com vhost automático

---

**Última atualização:** 2026-09-17  
**Testado em:** Debian 13, GCP Compute Engine e2-small
