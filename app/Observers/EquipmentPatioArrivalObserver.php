<?php

namespace App\Observers;

use App\Models\EquipmentMovementItemTemplate;
use App\Models\EquipmentPatioArrival;
use App\Models\EquipmentPatioArrivalItem;

class EquipmentPatioArrivalObserver
{
    public function created(EquipmentPatioArrival $equipmentPatioArrival): void
    {
        $templates = EquipmentMovementItemTemplate::where('type', EquipmentPatioArrival::TEMPLATE_TYPE)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $template) {
            EquipmentPatioArrivalItem::create([
                'equipment_patio_arrival_id' => $equipmentPatioArrival->id,
                'tenant_id' => $equipmentPatioArrival->tenant_id,
                'section' => $template->section,
                'label' => $template->label,
                'sort_order' => $template->sort_order,
                'requires_photo' => $template->requires_photo,
                'is_checked' => false,
            ]);
        }
    }
}
