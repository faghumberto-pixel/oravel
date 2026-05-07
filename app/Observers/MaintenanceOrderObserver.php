<?php

namespace App\Observers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use Illuminate\Support\Str;

class MaintenanceOrderObserver
{
    /**
     * Handle the MaintenanceOrder "created" event.
     */
    public function created(MaintenanceOrder $maintenanceOrder): void
    {
        // Garante que carregamos a relação 'asset' antes de acessar
        $asset = $maintenanceOrder->asset()->first();
        
        // Verifica se o ativo existe e se possui itens de checklist (manual_items)
        if ($asset && !empty($asset->manual_items) && is_array($asset->manual_items)) {
            foreach ($asset->manual_items as $item) {
                MaintenanceOrderChecklist::create([
                    'id' => Str::uuid(), // Forçamos a geração do UUID se o Model não o fizer automaticamente
                    'maintenance_order_id' => $maintenanceOrder->id,
                    'category' => $item['category'] ?? 'geral',
                    'item_name' => $item['item_name'] ?? 'Item sem nome',
                    'instructions' => $item['instructions'] ?? null,
                    'is_completed' => false,
                ]);
            }
        }
    }
}