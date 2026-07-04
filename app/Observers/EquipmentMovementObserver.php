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
}
