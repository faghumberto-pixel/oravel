# Backup por cliente (tenant)

`php artisan backup:tenants [--tenant=ID] [--no-platform] [--days=N]`

- Gera um arquivo por cliente: `{ORAVEL_BACKUP_DIR}/{slug}/{slug}_{Ymd_His}.sql.gz` + `.manifest.json` (contagem de linhas por tabela, SHA-256).
- Gera também o backup "Plataforma": tabelas sem tenant + linhas com `tenant_id` nulo.
- Tabelas efêmeras ficam fora (sessions, cache, jobs, tokens, breezy_sessions, migrations, database_backups).
- Retenção: `ORAVEL_BACKUP_DAYS` (padrão 30). Diretório: `ORAVEL_BACKUP_DIR` (padrão `/var/backups/oravel-tenants`).
- Cron em PROD: `scripts/backup-prod-tenants.sh` (root, grava em `/var/log/oravel-tenant-backup.log`).
- A tela **Central > Backups do Banco** lista tudo agrupado por cliente.

## Como funciona
Cada cliente é copiado para um schema temporário (`CREATE TABLE ... AS SELECT` filtrando por `tenant_id`, filhos por FK, polimórficos), o `pg_dump --data-only` do schema é reescrito para `public`, e o schema temporário é removido. O dump usa `session_replication_role = replica` dentro de transação.

## Restaurar um cliente
1. Banco com o schema atual (`php artisan migrate`).
2. `gunzip -c {arquivo}.sql.gz | psql -d BANCO` (use o `.manifest.json` para conferir as contagens).
3. Se o cliente já existe no banco de destino, apague as linhas dele antes — o dump só faz INSERT.

## Verificação
Restaurar todos os arquivos (clientes + plataforma) num banco vazio deve reproduzir exatamente as contagens do banco original. Isso foi conferido em clone do DEV: 184 tabelas, 1638 = 1638 linhas.
