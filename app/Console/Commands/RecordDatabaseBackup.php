<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Chamado por scripts/backup-prod-database.sh logo apos um pg_dump bem
 * sucedido (ou uma falha) -- so' registra metadados pra aparecer na tela
 * "Backups do Banco" do painel Central, nao mexe no dump em si.
 */
class RecordDatabaseBackup extends Command
{
    protected $signature = 'backup:record
        {path : Caminho completo do arquivo .dump}
        {--failed : Marca o registro como falho (path ainda assim aceito, mesmo que vazio/parcial)}
        {--error= : Mensagem de erro, quando --failed}';

    protected $description = 'Registra um backup de banco (feito por scripts/backup-prod-database.sh) no log consultavel pelo painel Central';

    public function handle(): int
    {
        $path = $this->argument('path');
        $failed = (bool) $this->option('failed');

        $tenantNames = Tenant::orderBy('name')->pluck('name')->all();

        DatabaseBackup::create([
            'filename' => basename($path),
            'path' => $path,
            'size_bytes' => (! $failed && file_exists($path)) ? filesize($path) : 0,
            'tenant_count' => count($tenantNames),
            'tenant_names' => $tenantNames,
            'status' => $failed ? DatabaseBackup::STATUS_FAILED : DatabaseBackup::STATUS_COMPLETED,
            'error_message' => $this->option('error'),
        ]);

        $this->info($failed ? 'Backup registrado como falho.' : 'Backup registrado com sucesso.');

        return self::SUCCESS;
    }
}
