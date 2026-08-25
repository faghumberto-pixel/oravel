<?php

namespace App\Services;

use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\Contract;
use App\Models\HorimeterReading;
use Carbon\Carbon;

/**
 * Calcula o excedente de franquia de horas de um contrato num período --
 * pedido do usuário 2026-08-24. Popula App\Domain\Fleet\Models\
 * RentalOverageCharge, que já existia no schema (migration
 * 2026_08_05_185904_create_rental_overage_charges_table + Resource CRUD
 * manual RentalOverageChargeResource) mas sem nenhum código gerando os
 * registros automaticamente -- este service é o motor que faltava.
 *
 * Reaproveita a mesma lógica de "horas trabalhadas num intervalo" já usada
 * em App\Console\Commands\FleetUtilizationReport::horasTrabalhadas()
 * (última leitura de HorimeterReading menos a primeira no período; retorna
 * null, não 0, se houver menos de 2 leituras -- dado insuficiente é
 * diferente de "não rodou").
 *
 * Não confia em Contract.status='Ativo' sozinho pra achar o contrato
 * vigente: cruza por data (period dentro de start_date/end_date), porque
 * o sistema não tem constraint que impeça 2 Contracts sobrepostos no
 * mesmo Asset (confirmado por investigação 2026-08-24). Se achar mais de
 * um contrato vigente pro mesmo Asset no mesmo período, marca como
 * RentalOverageCharge::STATUS_CONFLICT em vez de escolher um.
 */
class ContractOverageCalculator
{
    public function calculateForPeriod(Contract $contract, RentalHourFranchise $franchise, Carbon $periodStart, Carbon $periodEnd): RentalOverageCharge
    {
        $existing = RentalOverageCharge::where('contract_id', $contract->id)
            ->where('asset_id', $contract->asset_id)
            ->where('period_start', $periodStart->toDateString())
            ->where('period_end', $periodEnd->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        $baseAttributes = [
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'asset_id' => $contract->asset_id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'hours_included' => $franchise->included_hours_per_period,
        ];

        $conflictReason = $this->detectOverlapConflict($contract, $periodStart, $periodEnd);

        if ($conflictReason) {
            return RentalOverageCharge::create(array_merge($baseAttributes, [
                'status' => RentalOverageCharge::STATUS_CONFLICT,
                'conflict_reason' => $conflictReason,
            ]));
        }

        $hoursWorked = $this->horasTrabalhadas($contract->asset_id, $periodStart, $periodEnd);

        if ($hoursWorked === null) {
            return RentalOverageCharge::create(array_merge($baseAttributes, [
                'status' => RentalOverageCharge::STATUS_CONFLICT,
                'conflict_reason' => 'Menos de 2 leituras de horímetro no período — dado insuficiente para calcular.',
            ]));
        }

        $overageHours = max(0.0, $hoursWorked - (float) $franchise->included_hours_per_period);
        $overageAmount = round($overageHours * (float) $franchise->overage_rate_per_hour, 2);

        return RentalOverageCharge::create(array_merge($baseAttributes, [
            'hours_used' => $hoursWorked,
            'hours_overage' => $overageHours,
            'amount' => $overageAmount,
            'status' => RentalOverageCharge::STATUS_PENDING,
        ]));
    }

    /**
     * Mesma lógica de FleetUtilizationReport::horasTrabalhadas() -- última
     * leitura menos a primeira no intervalo. null (não 0) se não houver
     * leituras suficientes pra afirmar algo.
     */
    private function horasTrabalhadas(string $assetId, Carbon $start, Carbon $end): ?float
    {
        $leituras = HorimeterReading::query()
            ->where('asset_id', $assetId)
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->pluck('reading');

        if ($leituras->count() < 2) {
            return null;
        }

        return max(0.0, (float) $leituras->last() - (float) $leituras->first());
    }

    /**
     * Confirma se existe outro Contract do mesmo Asset com período
     * sobreposto ao período calculado -- sem isso, um cálculo poderia
     * cobrar o cliente errado silenciosamente.
     */
    private function detectOverlapConflict(Contract $contract, Carbon $periodStart, Carbon $periodEnd): ?string
    {
        $overlapping = Contract::where('asset_id', $contract->asset_id)
            ->where('id', '!=', $contract->id)
            ->where('start_date', '<=', $periodEnd)
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('end_date')->orWhere('end_date', '>=', $periodStart);
            })
            ->exists();

        if (! $overlapping) {
            return null;
        }

        return sprintf(
            'Ativo "%s" tem outro contrato com período sobreposto a %s–%s. Confirme qual contrato é o vigente antes de aprovar.',
            $contract->asset?->name ?? $contract->asset_id,
            $periodStart->format('d/m/Y'),
            $periodEnd->format('d/m/Y')
        );
    }
}
