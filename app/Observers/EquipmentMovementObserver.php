<?php

namespace App\Observers;

use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use App\Models\EquipmentMovementItemTemplate;

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
    }

    /**
     * Quando este movement faz parte de uma operacao de Troca de
     * Equipamento (EquipmentReplacement), propaga a conclusao pro status
     * conjunto da troca -- ver EquipmentReplacement::syncStatusFromMovements().
     */
    public function updated(EquipmentMovement $equipmentMovement): void
    {
        if (! $equipmentMovement->wasChanged('status') || $equipmentMovement->status !== EquipmentMovement::STATUS_CONCLUIDO) {
            return;
        }

        $replacement = $equipmentMovement->replacementAsDesmobilization
            ?? $equipmentMovement->replacementAsMobilization;

        $replacement?->syncStatusFromMovements();
    }
}
