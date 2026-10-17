<?php

namespace App\Console\Commands;

use App\Models\AccountReceivable;
use App\Models\TenantAsaasSetting;
use App\Services\Cobranca\CobrancaException;
use App\Services\Cobranca\CobrancaService;
use Illuminate\Console\Command;

/**
 * Rede de segurança do webhook: consulta no Asaas DA EMPRESA as cobranças ainda abertas e atualiza as contas a receber.
 * Cada empresa usa a própria chave (nunca a da Oravel) e só empresas com a cobrança automática ligada são consultadas.
 */
class SyncAsaasPaymentStatus extends Command
{
    protected $signature = 'asaas:sync-payment-status {--limit=100 : Máximo de contas por empresa em cada execução} {--tenant-id= : Só uma empresa}';

    protected $description = 'Confere no Asaas de cada empresa as cobranças abertas e atualiza as contas a receber';

    public function handle(CobrancaService $cobranca): int
    {
        $limite = (int) $this->option('limit');
        $atualizadas = 0;

        TenantAsaasSetting::withoutGlobalScopes()->where('enabled', true)
            ->when($this->option('tenant-id'), fn ($q, $id) => $q->where('tenant_id', $id))
            ->get()
            ->filter(fn (TenantAsaasSetting $c) => $c->pronta())
            ->each(function (TenantAsaasSetting $config) use ($cobranca, $limite, &$atualizadas) {
                AccountReceivable::withoutGlobalScopes()->where('tenant_id', $config->tenant_id)
                    ->whereNotNull('asaas_payment_id')->where('status', '!=', 'pago')
                    ->orderBy('updated_at')->limit($limite)->get()
                    ->each(function (AccountReceivable $conta) use ($cobranca, &$atualizadas) {
                        try {
                            $antes = $conta->status.'|'.$conta->asaas_status;
                            $cobranca->sincronizar($conta);
                            $atualizadas += $antes !== $conta->status.'|'.$conta->asaas_status ? 1 : 0;
                        } catch (CobrancaException $e) {
                            $this->warn("Conta {$conta->id}: {$e->getMessage()}");
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    });
            });

        $this->info("Contas atualizadas: {$atualizadas}");

        return self::SUCCESS;
    }
}
