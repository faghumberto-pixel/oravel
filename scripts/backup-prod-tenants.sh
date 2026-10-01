#!/bin/bash
#
# Backup diario POR CLIENTE (tenant) de PRODUCAO -- substitui scripts/backup-prod-database.sh (dump geral
# unico), a partir de 2026-10-02 (pedido do dono: backups separados por tenant, nao gerais).
#
# Um arquivo isolado por cliente + um da plataforma (dados que nao sao de nenhum cliente), em
# /var/backups/oravel-tenants/<slug>/<slug>_<data>.sql.gz, mais .manifest.json (contagem por tabela e
# SHA-256). Retencao de 30 dias. Cada arquivo e' registrado em database_backups (painel Central ->
# "Backups do Banco", com filtro por cliente). Restaurar um cliente:
#     gunzip -c ARQUIVO.sql.gz | sudo -u postgres psql -d oravel_db
# (num banco com o schema atual; o cliente nao pode ja existir -- ver docs/BACKUP_POR_TENANT.md).
#
# Uso: cron do root, nao interativo. O backup em si roda como www-data (php artisan backup:tenants).

set -uo pipefail

BACKUP_DIR="/var/backups/oravel-tenants"
APP_PATH="/var/www/oravel"
LOG_FILE="/var/log/oravel-tenant-backup.log"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"; }

mkdir -p "$BACKUP_DIR"
chown www-data:www-data "$BACKUP_DIR"
chmod 750 "$BACKUP_DIR"

log "Iniciando backups por cliente..."
if sudo -u www-data HOME=/tmp bash -c "cd '$APP_PATH' && php artisan backup:tenants" >> "$LOG_FILE" 2>&1; then
    log "Backups por cliente concluidos."
else
    log "ERRO: ao menos um backup falhou -- veja $LOG_FILE e o painel Central (Backups do Banco)."
    exit 1
fi
