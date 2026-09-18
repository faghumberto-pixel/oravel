<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Services\AsaasService;
use Illuminate\Console\Command;

class SyncAsaasPaymentStatus extends Command
{
    protected $signature = 'asaas:sync-payment-status {--limit=100 : Máximo de registros por execução}';

    protected $description = 'Sincroniza status de pagamentos com Asaas (busca atualizações)';

    public function handle()
    {
        $asaasService = new AsaasService();
        $limit = (int) $this->option('limit');

        // Buscar contas a receber com asaas_payment_id mas que podem ter mudado de status
        $receivables = AccountReceivable::whereNotNull('asaas_payment_id')
            ->where('status', '!=', 'pago') // Já pagas não precisam sincronizar
            ->limit($limit)
            ->get();

        $total = $receivables->count();

        if ($total === 0) {
            $this->info('✓ Nenhuma conta para sincronizar');
            return 0;
        }

        $this->info("🔄 Sincronizando status de {$total} contas...");

        $bar = $this->output->createProgressBar($total);
        $updated = 0;

        foreach ($receivables as $receivable) {
            try {
                if ($asaasService->syncPaymentStatus($receivable)) {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $this->error("\n❌ Erro ao sincronizar {$receivable->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->newLine();

        $this->info("✅ Sincronização concluída: {$updated} contas atualizadas");

        return 0;
    }
}
