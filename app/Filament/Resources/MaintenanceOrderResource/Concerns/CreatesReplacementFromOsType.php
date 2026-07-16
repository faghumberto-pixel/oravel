<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Concerns;

use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;

trait CreatesReplacementFromOsType
{
    /**
     * "Troca de Equipamento" como Tipo de Operacao da O.S. -- entrada
     * principal da Troca (confirmado: geralmente solicitada pelo tecnico
     * em campo), mesmo padrao de CreatesDamageFromAvariaType. So' cria
     * uma vez: se a OS ja foi salva antes como Troca e o usuario salva de
     * novo, nao duplica. O resto do workflow (identificar substituto,
     * iniciar movimentacoes, concluir) acontece na tela de
     * EquipmentReplacementResource, nao aqui.
     */
    protected function createReplacementFromOsType(MaintenanceOrder $order): void
    {
        if ($order->maintenance_type !== MaintenanceOrder::TYPE_TROCA) {
            return;
        }

        if (EquipmentReplacement::where('maintenance_order_id', $order->id)->exists()) {
            return;
        }

        $rawState = $this->form->getRawState();

        EquipmentReplacement::create([
            'tenant_id' => $order->tenant_id,
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $order->asset_id,
            'requested_by_user_id' => auth()->id(),
            'urgency' => $rawState['replacement_urgency'] ?? EquipmentReplacement::URGENCY_NORMAL,
            'reason' => $order->description ?: "Troca solicitada na OS #{$order->os_number}",
        ]);
    }
}
