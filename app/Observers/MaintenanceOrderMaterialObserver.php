<?php

namespace App\Observers;

use App\Models\MaintenanceOrderMaterial;
use App\Services\MaterialConsumptionService;

class MaintenanceOrderMaterialObserver
{
    public function created(MaintenanceOrderMaterial $maintenanceOrderMaterial): void
    {
        app(MaterialConsumptionService::class)->recordConsumption($maintenanceOrderMaterial);
        $this->recalculateOrderCosts($maintenanceOrderMaterial);
    }

    public function updated(MaintenanceOrderMaterial $maintenanceOrderMaterial): void
    {
        $this->recalculateOrderCosts($maintenanceOrderMaterial);
    }

    public function deleted(MaintenanceOrderMaterial $maintenanceOrderMaterial): void
    {
        $this->recalculateOrderCosts($maintenanceOrderMaterial);
    }

    /**
     * material_cost/total_order_cost da O.S. nao eram calculados em lugar
     * nenhum (confirmado 2026-07-29) -- os 4 campos de custo eram texto
     * livre, sem trava nenhuma, deixando o tecnico digitar qualquer valor
     * manualmente na propria O.S. Agora material_cost e' a soma real de
     * quantity * unit_price de cada material aplicado, e total_order_cost
     * soma os 3 (mao de obra/material/logistica) -- o form
     * (MaintenanceOrderResource) trava os dois pra edicao manual, so'
     * exibe o resultado.
     */
    private function recalculateOrderCosts(MaintenanceOrderMaterial $maintenanceOrderMaterial): void
    {
        $order = $maintenanceOrderMaterial->maintenanceOrder;

        if (! $order) {
            return;
        }

        $materialCost = $order->materials()
            ->get()
            ->sum(fn (MaintenanceOrderMaterial $item) => (float) $item->quantity * (float) $item->unit_price);

        $order->update([
            'material_cost' => $materialCost,
            'total_order_cost' => $materialCost + (float) $order->labor_cost + (float) $order->logistics_cost,
        ]);
    }
}
