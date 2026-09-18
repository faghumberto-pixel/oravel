<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Services\AsaasService;
use Illuminate\Console\Command;

class SyncAccountReceivablesToAsaas extends Command
{
    protected $signature = 'asaas:sync-receivables {--tenant-id= : Sincronizar apenas um tenant}';

    protected $description = 'Sincroniza contas a receber com Asaas (cria cobranças)';

    public function handle()
    {
        $asaasService = new AsaasService();

        $query = AccountReceivable::whereNull('asaas_payment_id');

        if ($tenantId = $this->option('tenant-id')) {
            $query->where('tenant_id', $tenantId);
        }

        $receivables = $query->get();
        $total = $receivables->count();

        if ($total === 0) {
            $this->info('✓ Nenhuma conta a sincronizar');
            return 0;
        }

        $this->info("📤 Sincronizando {$total} contas com Asaas...");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $created = 0;
        $failed = 0;

        foreach ($receivables as $receivable) {
            try {
                $paymentId = $asaasService->createPayment($receivable);

                if ($paymentId) {
                    $created++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("\n❌ Erro ao processar {$receivable->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        $this->info("✅ Sincronização concluída:");
        $this->line("   Criadas: {$created} cobranças");
        $this->line("   Falhadas: {$failed} cobranças");

        return $failed > 0 ? 1 : 0;
    }
}
