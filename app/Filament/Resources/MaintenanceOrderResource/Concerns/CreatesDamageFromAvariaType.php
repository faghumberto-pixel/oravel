<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Concerns;

use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;

trait CreatesDamageFromAvariaType
{
    /**
     * "Registro de Avaria" como Tipo de Operacao da O.S. -- cria a
     * EquipmentDamage direto (sem depender do atalho via foto-evidencia,
     * ver StoresPhotoEvidence::persistPhotoEvidences()). So' cria uma vez:
     * se a OS ja foi salva antes como Avaria e o usuario salva de novo, nao
     * duplica.
     */
    protected function createDamageFromAvariaType(MaintenanceOrder $order): void
    {
        if ($order->maintenance_type !== MaintenanceOrder::TYPE_AVARIA) {
            return;
        }

        if (EquipmentDamage::where('maintenance_order_id', $order->id)->exists()) {
            return;
        }

        $rawState = $this->form->getRawState();

        EquipmentDamage::create([
            'tenant_id' => $order->tenant_id,
            'maintenance_order_id' => $order->id,
            'asset_id' => $order->asset_id,
            'reported_by_user_id' => auth()->id(),
            'severity' => $rawState['damage_severity'] ?? EquipmentDamage::SEVERITY_MODERADA,
            'damage_type' => $rawState['damage_type'] ?? EquipmentDamage::DAMAGE_TYPE_OUTRO,
            'description' => $order->description ?: "Avaria registrada na OS #{$order->os_number}",
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);
    }
}
