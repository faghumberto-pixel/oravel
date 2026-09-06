<?php

namespace App\Services;

use App\Domain\Fleet\Models\ContractMeasurement;
use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\Contract;
use Carbon\Carbon;

/**
 * Gera a medição mensal consolidada de um contrato: valor base
 * proporcional (pró-rata quando o contrato começou/terminou no meio do
 * período) + excedente de franquia de horas (reaproveita
 * ContractOverageCalculator/RentalOverageCharge já existentes -- não
 * duplica o cálculo de horímetro) + extras (adicionados manualmente
 * depois, ver ContractMeasurementExtra).
 *
 * Cria sempre em status DRAFT -- quem decide enviar pra aprovação é o
 * financeiro (ContractMeasurement::submit()), nunca automático.
 */
class ContractMeasurementService
{
    public function __construct(
        private ContractOverageCalculator $overageCalculator,
    ) {}

    public function generateForPeriod(Contract $contract, Carbon $periodStart, Carbon $periodEnd): ContractMeasurement
    {
        $existing = ContractMeasurement::where('contract_id', $contract->id)
            ->where('reference_period_start', $periodStart->toDateString())
            ->where('reference_period_end', $periodEnd->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        [$proratedDays, $totalDaysInPeriod] = $this->calculateProratedDays($contract, $periodStart, $periodEnd);

        $baseAmount = $this->calculateBaseAmount($contract, $proratedDays, $totalDaysInPeriod);

        $overageCharge = $this->resolveOverageCharge($contract, $periodStart, $periodEnd);
        $excessHoursAmount = $overageCharge && $overageCharge->status !== RentalOverageCharge::STATUS_CONFLICT
            ? (float) $overageCharge->amount
            : 0.0;

        $totalAmount = round($baseAmount + $excessHoursAmount, 2);

        return ContractMeasurement::create([
            'tenant_id' => $contract->tenant_id,
            'contract_id' => $contract->id,
            'reference_period_start' => $periodStart,
            'reference_period_end' => $periodEnd,
            'total_days_in_period' => $totalDaysInPeriod,
            'prorated_days' => $proratedDays,
            'total_base_amount' => $baseAmount,
            'total_excess_hours_amount' => $excessHoursAmount,
            'total_extras_amount' => 0,
            'total_amount' => $totalAmount,
            'rental_overage_charge_id' => $overageCharge?->id,
            'status' => ContractMeasurement::STATUS_DRAFT,
        ]);
    }

    /**
     * Dias do período em que o contrato de fato esteve vigente -- a
     * interseção entre [contract.start_date, contract.end_date] e
     * [periodStart, periodEnd]. Sem interseção real (contrato terminou
     * antes do período começar, ou começa depois do período acabar) ->
     * zero dias, base zerada.
     *
     * @return array{0: int, 1: int} [prorated_days, total_days_in_period]
     */
    private function calculateProratedDays(Contract $contract, Carbon $periodStart, Carbon $periodEnd): array
    {
        // Normaliza pra meia-noite antes de qualquer diff -- $periodEnd
        // costuma vir de ->endOfMonth() (23:59:59.999999), e comparar isso
        // direto com uma data em 00:00:00 gera diffInDays() fracionário
        // (ex: 30.999999988 em vez de 31) por causa dos microssegundos
        // residuais, o que quebra a coluna smallint no banco.
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->startOfDay();

        $totalDaysInPeriod = (int) round($periodStart->diffInDays($periodEnd)) + 1;

        $contractStart = $contract->start_date?->copy()->startOfDay();
        $contractEnd = $contract->end_date?->copy()->startOfDay();

        $effectiveStart = $contractStart && $contractStart->gt($periodStart)
            ? $contractStart
            : $periodStart;

        $effectiveEnd = $contractEnd && $contractEnd->lt($periodEnd)
            ? $contractEnd
            : $periodEnd;

        if ($effectiveStart->gt($effectiveEnd)) {
            return [0, $totalDaysInPeriod];
        }

        $proratedDays = (int) round($effectiveStart->diffInDays($effectiveEnd)) + 1;

        return [$proratedDays, $totalDaysInPeriod];
    }

    /**
     * Diária (billing_type=diaria) já é por dia trabalhado -- price *
     * prorated_days, sem proporção adicional (o "cheio" dela é o próprio
     * dia). Mensal fixo / franquia+excedente: price é o valor do MÊS
     * CHEIO, então proporcionaliza por (prorated_days / total_days).
     */
    private function calculateBaseAmount(Contract $contract, int $proratedDays, int $totalDaysInPeriod): float
    {
        $price = (float) $contract->price;

        if ($contract->usesDailyBilling()) {
            return round($price * $proratedDays, 2);
        }

        if ($totalDaysInPeriod === 0) {
            return 0.0;
        }

        return round($price * ($proratedDays / $totalDaysInPeriod), 2);
    }

    /**
     * Só calcula excedente pra contratos billing_type=franquia_excedente
     * com uma RentalHourFranchise vigente no período -- mesma regra de
     * "franquia mais recente por effective_from" usada em
     * App\Console\Commands\CalculateContractOverage.
     */
    private function resolveOverageCharge(Contract $contract, Carbon $periodStart, Carbon $periodEnd): ?RentalOverageCharge
    {
        if (! $contract->usesHourFranchise() || ! $contract->asset_id) {
            return null;
        }

        $franchise = RentalHourFranchise::where('contract_id', $contract->id)
            ->where('effective_from', '<=', $periodEnd)
            ->orderByDesc('effective_from')
            ->first();

        if (! $franchise) {
            return null;
        }

        return $this->overageCalculator->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);
    }
}
