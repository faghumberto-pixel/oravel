#!/bin/bash
#
# Backup diario do banco de dados de PRODUCAO (todos os tenants, mesmo dump --
# nao tem isolamento por tenant no nivel de infra, cada tenant e' so' uma
# linha em "tenants" + linhas com tenant_id nas tabelas normais).
#
# Criado 2026-09-18 apos incidente: reprovisionamento da VM em 18/09 deixou
# o oravel_db vazio (so' schema, 0 tenants/usuarios) sem ninguem perceber ate'
# um cliente tentar logar. Recuperado via snapshot de disco antigo do GCP
# (dados de 19/08 -- ~1 mes de atividade real perdida, sem como recuperar).
# Isso NUNCA deveria depender de snapshot de disco (retencao/timing nao
# pensados pra isso) -- dump logico diario e dedicado e' a rede de seguranca
# de verdade daqui pra frente.
#
# Uso: roda via cron como root (ver crontab -l), nao interativo.
# Local dos dumps: /var/backups/db/ (mesmo diretorio pai do backup de
# arquivos que o deploy.sh ja faz em /var/backups/oravel_backup_*).
# Cada rodada registra um App\Models\DatabaseBackup (php artisan backup:record),
# visivel no painel Central em "Backups do Banco" (filtro por tenant/data --
# ver App\Filament\Central\Resources\DatabaseBackupResource).

set -euo pipefail

DB_NAME="oravel_db"
DB_USER="oravel_user"
BACKUP_DIR="/var/backups/db"
RETENTION_DAYS=30
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="$BACKUP_DIR/oravel_db_${TIMESTAMP}.dump"
LOG_FILE="/var/log/oravel-db-backup.log"
APP_PATH="/var/www/oravel"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

mkdir -p "$BACKUP_DIR"
# pg_dump roda como postgres (peer auth local, sem depender de senha) --
# sem isso o diretorio fica dono de quem chamou o script (root, via cron)
# e o postgres nao consegue escrever nele.
chown postgres:postgres "$BACKUP_DIR"

log "Iniciando backup de $DB_NAME..."

# Formato custom (-Fc): comprimido, restauravel seletivamente (pg_restore
# -t <tabela>), mais robusto a diferenca de versao do Postgres do que um
# dump plain-SQL gigante. Roda como postgres (superuser local, sem senha
# via peer auth) pra nao depender de DB_PASSWORD estar certo no ambiente
# do cron.
if sudo -u postgres pg_dump -Fc -d "$DB_NAME" -f "$DUMP_FILE"; then
    SIZE=$(du -h "$DUMP_FILE" | cut -f1)
    log "Backup concluido: $DUMP_FILE ($SIZE)"
    # Registro consultavel na tela "Backups do Banco" do painel Central
    # (2026-09-18) -- so' metadados, nao falha o backup se der erro aqui.
    sudo -u www-data HOME=/tmp bash -c "cd '$APP_PATH' && php artisan backup:record '$DUMP_FILE'" \
        || log "AVISO: backup:record falhou (backup em si esta OK, so' nao apareceu no painel)."
else
    log "ERRO: pg_dump falhou -- veja acima. Backup NAO foi criado."
    sudo -u www-data HOME=/tmp bash -c "cd '$APP_PATH' && php artisan backup:record '$DUMP_FILE' --failed" 2>/dev/null || true
    exit 1
fi

# Retencao: apaga dumps com mais de RETENTION_DAYS dias. Nao mexe em nada
# fora de $BACKUP_DIR/oravel_db_*.dump (mesmo cuidado do deploy.sh com os
# backups de arquivo, ver incidente de disco cheio em
# project_deploy_backup_disk_full_incident).
DELETED=$(find "$BACKUP_DIR" -maxdepth 1 -name "oravel_db_*.dump" -mtime "+$RETENTION_DAYS" -print -delete | wc -l)
if [ "$DELETED" -gt 0 ]; then
    log "Retencao: removidos $DELETED dump(s) com mais de $RETENTION_DAYS dias."
fi

TOTAL=$(find "$BACKUP_DIR" -maxdepth 1 -name "oravel_db_*.dump" | wc -l)
log "Total de backups mantidos: $TOTAL"
log "Backup finalizado com sucesso."
