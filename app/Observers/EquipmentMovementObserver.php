<?php

namespace App\Observers;

use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use App\Models\EquipmentMovementItemTemplate;
use App\Models\FleetVehicle;

class EquipmentMovementObserver
{
    public function created(EquipmentMovement $equipmentMovement): void
    {
        $templates = EquipmentMovementItemTemplate::where('type', $equipmentMovement->type)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $template) {
            EquipmentMovementItem::create([
                'equipment_movement_id' => $equipmentMovement->id,
                'tenant_id' => $equipmentMovement->tenant_id,
                'section' => $template->section,
                'label' => $template->label,
                'sort_order' => $template->sort_order,
                'requires_photo' => $template->requires_photo,
                'is_checked' => false,
            ]);
        }

        $this->syncFleetVehicleStatus($equipmentMovement);
    }

    /**
     * Quando este movement faz parte de uma operacao de Troca de
     * Equipamento (EquipmentReplacement), propaga a conclusao pro status
     * conjunto da troca -- ver EquipmentReplacement::syncStatusFromMovements().
     */
    public function updated(EquipmentMovement $equipmentMovement): void
    {
        if ($equipmentMovement->wasChanged('fleet_vehicle_id')) {
            $originalVehicleId = $equipmentMovement->getOriginal('fleet_vehicle_id');
            if ($originalVehicleId) {
                $this->releaseFleetVehicleIfIdle($originalVehicleId, $equipmentMovement->id);
            }
        }

        $this->syncFleetVehicleStatus($equipmentMovement);

        if (! $equipmentMovement->wasChanged('status') || $equipmentMovement->status !== EquipmentMovement::STATUS_CONCLUIDO) {
            return;
        }

        $replacement = $equipmentMovement->replacementAsDesmobilization
            ?? $equipmentMovement->replacementAsMobilization;

        $replacement?->syncStatusFromMovements();
    }

    /**
     * Fonte unica de verdade de FleetVehicle::status (disponivel <->
     * em_rota) -- nunca editado a mao pelo usuario nas telas de
     * movimentacao, so aqui. Um veiculo em manutencao/inativo nao e'
     * "puxado de volta" pra disponivel so porque uma movimentacao
     * concluiu -- so DISPONIVEL <-> EM_ROTA sao automatizados.
     */
    private function syncFleetVehicleStatus(EquipmentMovement $equipmentMovement): void
    {
        if (! $equipmentMovement->fleet_vehicle_id) {
            return;
        }

        if ($equipmentMovement->status === EquipmentMovement::STATUS_CONCLUIDO) {
            $this->releaseFleetVehicleIfIdle($equipmentMovement->fleet_vehicle_id, $equipmentMovement->id);

            return;
        }

        FleetVehicle::where('id', $equipmentMovement->fleet_vehicle_id)
            ->where('status', FleetVehicle::STATUS_DISPONIVEL)
            ->update(['status' => FleetVehicle::STATUS_EM_ROTA]);
    }

    /**
     * So libera o veiculo se nenhuma OUTRA movimentacao ativa ainda
     * referencia ele -- evita "roubar" o veiculo de volta pra disponivel
     * enquanto ele ainda esta em uso por outra movimentacao.
     */
    private function releaseFleetVehicleIfIdle(string $fleetVehicleId, string $excludingMovementId): void
    {
        $stillInUse = EquipmentMovement::where('fleet_vehicle_id', $fleetVehicleId)
            ->where('id', '!=', $excludingMovementId)
            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
            ->exists();

        if ($stillInUse) {
            return;
        }

        FleetVehicle::where('id', $fleetVehicleId)
            ->where('status', FleetVehicle::STATUS_EM_ROTA)
            ->update(['status' => FleetVehicle::STATUS_DISPONIVEL]);
    }
}
