<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Models\Tenant;
use Illuminate\Console\Command;

class AsaasSync extends Command
{
    protected $signature = 'asaas:sync-status {--tenant-id= : Mostrar status de um tenant específico}';

    protected $description = 'Mostra status de sincronização com Asaas';

    public function handle()
    {
        $this->info('🔄 Status de Sincronização Asaas');
        $this->line('');

        // Status da configuração
        $token = config('services.asaas.webhook_token');
        $this->info('⚙️  Configuração:');
        $this->line("  Token Webhook: " . ($token ? '✓ Configurado' : '✗ NÃO configurado'));
        $this->line("  Rota Webhook: " . route('asaas.webhook'));
        $this->line('');

        // Estatísticas de AccountReceivable por status
        $this->info('📊 Contas a Receber com Asaas:');
        $stats = AccountReceivable::whereNotNull('asaas_payment_id')
            ->selectRaw('status, count(*) as total, sum(amount) as total_amount')
            ->groupBy('status')
            ->get();

        if ($stats->isEmpty()) {
            $this->line('  Nenhuma conta com asaas_payment_id');
        } else {
            foreach ($stats as $stat) {
                $this->line("  {$stat->status}: {$stat->total} contas | R$ " . number_format($stat->total_amount, 2, ',', '.'));
            }
        }
        $this->line('');

        // Contas sem asaas_payment_id (manual)
        $manual = AccountReceivable::whereNull('asaas_payment_id')->count();
        $this->info("📋 Contas Manuais (sem Asaas): {$manual}");
        $this->line('');

        // Status de pagamento dos Tenants
        $this->info('🏢 Status de Assinatura dos Tenants:');
        $tenantStats = Tenant::selectRaw('asaas_payment_status, count(*) as total')
            ->groupBy('asaas_payment_status')
            ->get();

        if ($tenantStats->isEmpty()) {
            $this->line('  Nenhum tenant com status Asaas');
        } else {
            foreach ($tenantStats as $stat) {
                $status = $stat->asaas_payment_status ?? 'não definido';
                $this->line("  {$status}: {$stat->total} tenants");
            }
        }
        $this->line('');

        // Se um tenant específico foi solicitado
        if ($tenantId = $this->option('tenant-id')) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                $this->info("📌 Detalhes do Tenant: {$tenant->name}");
                $this->line("  ID: {$tenant->id}");
                $this->line("  Asaas Customer: {$tenant->asaas_customer_id}");
                $this->line("  Status Pagamento: {$tenant->asaas_payment_status}");
                $this->line("  Último Pagamento: {$tenant->asaas_last_payment_id}");
                $this->line("  Atualizado em: {$tenant->asaas_payment_updated_at}");
            } else {
                $this->error("Tenant {$tenantId} não encontrado");
                return 1;
            }
        }

        return 0;
    }
}
