<?php

namespace App\Console\Commands;

use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ContractOverageCalculatedNotification;
use App\Services\ContractOverageCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Roda mensalmente (App\Console\Kernel::schedule()) e calcula, por tenant,
 * o excedente de franquia de horas de cada contrato com franquia vigente
 * no mês anterior -- pedido do usuário 2026-08-24: cálculo automático,
 * mas NÃO gera cobrança sozinho (ver RentalOverageCharge::approve(),
 * chamado manualmente pelo financeiro depois de revisar).
 *
 * Popula App\Domain\Fleet\Models\RentalOverageCharge, que já existia no
 * schema (RentalOverageChargeResource, CRUD manual) mas sem motor
 * automático -- este comando é o motor que faltava.
 *
 * Dedupe via unique(contract_id, asset_id, period_start, period_end) na
 * própria tabela rental_overage_charges -- rodar o comando 2x no mesmo mês
 * não duplica.
 *
 * Só considera a franquia mais recente por contrato (maior effective_from
 * <= fim do período) -- RentalHourFranchise não tem effective_to, o
 * design assume versionamento por effective_from mais recente.
 */
class CalculateContractOverage extends Command
{
    protected $signature = 'contracts:calculate-overage {--month= : Mês de referência (YYYY-MM), default mês anterior}';

    protected $description = 'Calcula o excedente de franquia de horas do mês anterior para cada contrato com franquia e notifica o financeiro';

    public function handle(ContractOverageCalculator $calculator): int
    {
        $reference = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $periodStart = $reference->copy()->startOfMonth();
        $periodEnd = $reference->copy()->endOfMonth();

        $totalCalculados = 0;
        $totalConflitos = 0;

        foreach (Tenant::all() as $tenant) {
            $franchises = RentalHourFranchise::where('tenant_id', $tenant->id)
                ->where('effective_from', '<=', $periodEnd)
                ->with('contract')
                ->get()
                ->groupBy('contract_id')
                ->map(fn ($group) => $group->sortByDesc('effective_from')->first());

            if ($franchises->isEmpty()) {
                continue;
            }

            $conflitosDoTenant = 0;

            foreach ($franchises as $franchise) {
                $contract = $franchise->contract;

                if (! $contract || ! $contract->asset_id) {
                    continue;
                }

                $charge = $calculator->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);

                $totalCalculados++;

                if ($charge->status === RentalOverageCharge::STATUS_CONFLICT) {
                    $totalConflitos++;
                    $conflitosDoTenant++;
                }
            }

            $pendentes = RentalOverageCharge::where('tenant_id', $tenant->id)
                ->where('period_start', $periodStart->toDateString())
                ->where('period_end', $periodEnd->toDateString())
                ->where('status', RentalOverageCharge::STATUS_PENDING)
                ->count();

            if ($pendentes > 0 || $conflitosDoTenant > 0) {
                $this->notifyFinanceiro($tenant, $pendentes, $conflitosDoTenant, $periodStart);
            }
        }

        $this->info("Cálculo concluído. {$totalCalculados} contrato(s) processado(s), {$totalConflitos} conflito(s) encontrado(s).");

        return Command::SUCCESS;
    }

    /**
     * Ver EquipmentReplacementObserver::notifyRole() / CheckMaintenanceDueAlerts
     * -- não usar User::role($roleName) direto, Spatie resolve por nome
     * globalmente (ignora tenant_id).
     */
    private function notifyFinanceiro(Tenant $tenant, int $pendentes, int $conflitos, Carbon $periodStart): void
    {
        $role = Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->where('tenant_id', $tenant->id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)->where('tenant_id', $tenant->id)->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new ContractOverageCalculatedNotification($pendentes, $conflitos, $periodStart));
        }
    }
}
