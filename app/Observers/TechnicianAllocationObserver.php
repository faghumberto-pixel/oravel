<?php

namespace App\Observers;

use App\Models\TechnicianAllocation;

/**
 * TechnicianAllocation e' a fonte de verdade pro Gantt de Alocacao de
 * Tecnicos, mas MaintenanceOrderResource/PainelPmp/AgendaTecnicoWidget/
 * CargaTecnica continuam lendo MaintenanceOrder.technician_id/scheduled_at
 * diretamente -- este observer espelha pra la' sem criar uma segunda
 * fonte de verdade divergente.
 */
class TechnicianAllocationObserver
{
    public function saved(TechnicianAllocation $allocation): void
    {
        if (! $allocation->maintenance_order_id) {
            return;
        }

        $allocation->maintenanceOrder?->update([
            'technician_id' => $allocation->technician_id,
            'scheduled_at' => $allocation->starts_at,
        ]);
    }
}
