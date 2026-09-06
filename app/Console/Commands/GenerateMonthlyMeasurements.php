<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\ContractMeasurementService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Roda mensalmente (App\Console\Kernel::schedule(), logo depois de
 * contracts:calculate-overage -- a medição reaproveita o excedente já
 * calculado por aquele comando em vez de recalcular) e gera, em rascunho,
 * a medição consolidada do mês anterior pra cada contrato de locação de
 * longo prazo ativo.
 *
 * NÃO cobre billing_type=por_hora de propósito -- esse tipo já fatura sob
 * demanda por outro fluxo, não tem um "valor base mensal" que faça sentido
 * proporcionalizar aqui.
 *
 * Dedupe via unique(contract_id, reference_period_start,
 * reference_period_end) na própria tabela -- rodar o comando 2x no mesmo
 * mês não duplica (ver ContractMeasurementService::generateForPeriod()).
 */
class GenerateMonthlyMeasurements extends Command
{
    protected $signature = 'contracts:generate-measurements {--month= : Mês de referência (YYYY-MM), default mês anterior}';

    protected $description = 'Gera, em rascunho, a medição consolidada do mês anterior para cada contrato de locação de longo prazo ativo';

    private const BILLABLE_TYPES = [
        Contract::BILLING_MENSAL_FIXO,
        Contract::BILLING_FRANQUIA_EXCEDENTE,
        Contract::BILLING_DIARIA,
    ];

    public function handle(ContractMeasurementService $service): int
    {
        $reference = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $periodStart = $reference->copy()->startOfMonth();
        $periodEnd = $reference->copy()->endOfMonth();

        $contracts = Contract::whereIn('billing_type', self::BILLABLE_TYPES)
            ->where('start_date', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $periodStart);
            })
            ->get();

        $generated = 0;
        $skipped = 0;

        foreach ($contracts as $contract) {
            $measurement = $service->generateForPeriod($contract, $periodStart, $periodEnd);

            if ($measurement->wasRecentlyCreated) {
                $generated++;
            } else {
                $skipped++;
            }
        }

        $this->info("Medições geradas: {$generated}. Já existentes (puladas): {$skipped}.");

        return Command::SUCCESS;
    }
}
