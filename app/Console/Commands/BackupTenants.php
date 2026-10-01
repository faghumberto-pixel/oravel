<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Models\Tenant;
use App\Services\TenantBackupService;
use Illuminate\Console\Command;

/**
 * Backup POR CLIENTE: um arquivo isolado por tenant + um da plataforma (ver TenantBackupService).
 * Substitui o dump geral único. Rodado diariamente por scripts/backup-prod-tenants.sh (cron do root).
 */
class BackupTenants extends Command
{
    protected $signature = 'backup:tenants
        {--tenant= : Só este tenant (slug ou id)}
        {--no-platform : Não gera o arquivo da plataforma}
        {--days= : Retenção em dias (padrão: config oravel.backups.days)}';

    protected $description = 'Gera um backup isolado por cliente (tenant) e um da plataforma, e apaga os antigos';

    public function handle(TenantBackupService $service): int
    {
        $tenants = Tenant::withoutGlobalScopes()->orderBy('name')
            ->when($this->option('tenant'), fn ($q, $t) => $q->where(fn ($w) => $w->where('slug', $t)->orWhere('id', $t)))
            ->get();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant encontrado.');

            return self::FAILURE;
        }

        $failures = 0;

        foreach ($tenants as $tenant) {
            $backup = $service->backupTenant($tenant);
            $failures += $backup->status === DatabaseBackup::STATUS_FAILED ? 1 : 0;
            $this->line(sprintf('%-8s %-34s %s', $backup->status === DatabaseBackup::STATUS_COMPLETED ? 'OK' : 'FALHOU', $tenant->name, $backup->status === DatabaseBackup::STATUS_COMPLETED ? number_format($backup->rows_count).' linhas, '.number_format($backup->size_bytes / 1024, 1, ',', '.').' KB' : $backup->error_message));
        }

        if (! $this->option('no-platform') && ! $this->option('tenant')) {
            $backup = $service->backupPlatform();
            $failures += $backup->status === DatabaseBackup::STATUS_FAILED ? 1 : 0;
            $this->line(sprintf('%-8s %-34s %s', $backup->status === DatabaseBackup::STATUS_COMPLETED ? 'OK' : 'FALHOU', 'Plataforma', $backup->status === DatabaseBackup::STATUS_COMPLETED ? number_format($backup->rows_count).' linhas' : $backup->error_message));
        }

        $pruned = $service->prune((int) ($this->option('days') ?: config('oravel.backups.days', 30)));
        $this->info("Backups antigos apagados: {$pruned}. Falhas: {$failures}.");

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
