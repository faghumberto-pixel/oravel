<?php

namespace App\Observers;

use App\Models\FleetMaintenanceHistory;

/**
 * Ate 2026-07-14 registrar uma execucao aqui nao atualizava o Plano
 * vinculado (FleetMaintenancePlan.ultima_execucao_km/data) -- o usuario
 * precisava editar o plano manualmente por fora, senao o badge de
 * vencido/proximo do vencimento (FleetMaintenancePlan::isVencido()/
 * isProximoVencimento()) ficava desatualizado. Mesmo espirito do que
 * FuelRecordsRelationManager::after() ja faz pra FleetVehicle.km_atual:
 * usa sempre o maior valor, nunca regride o plano se alguem lancar uma
 * execucao antiga fora de ordem.
 */
class FleetMaintenanceHistoryObserver
{
    public function created(FleetMaintenanceHistory $history): void
    {
        $plan = $history->maintenancePlan;

        if (! $plan) {
            return;
        }

        $novoKm = max((float) ($history->km_na_execucao ?? 0), (float) ($plan->ultima_execucao_km ?? 0));

        $novaData = $plan->ultima_execucao_data;
        if ($history->data_execucao && (! $novaData || $history->data_execucao->greaterThan($novaData))) {
            $novaData = $history->data_execucao;
        }

        $plan->update([
            'ultima_execucao_km' => $novoKm,
            'ultima_execucao_data' => $novaData,
        ]);
    }
}
