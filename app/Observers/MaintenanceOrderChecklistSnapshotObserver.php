<?php

namespace App\Observers;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;

/**
 * Ao criar uma OS, copia (snapshot -- nunca referencia direta) os itens de
 * checklist basico do Grupo do ativo + os itens extras especificos daquele
 * ativo, para a OS recem-criada. Edicao futura do template (grupo ou
 * ativo) nao afeta checklists ja gerados.
 *
 * Substitui a logica de checklist do antigo MaintenanceOrderObserver (nunca
 * registrado, referenciava App\Models\LogisticsQueue inexistente em outro
 * metodo) -- este observer so cuida do snapshot do checklist.
 */
class MaintenanceOrderChecklistSnapshotObserver
{
    public function created(MaintenanceOrder $order): void
    {
        $asset = $order->asset()->first();

        if (! $asset) {
            return;
        }

        $templates = collect();

        if ($asset->checklist_group_id) {
            $templates = $templates->concat(
                MaintenanceOrderChecklist::where('is_template', true)
                    ->where('checklist_group_id', $asset->checklist_group_id)
                    ->get()
            );
        }

        $templates = $templates->concat(
            MaintenanceOrderChecklist::where('is_template', true)
                ->where('asset_id', $asset->id)
                ->get()
        );

        foreach ($templates as $template) {
            MaintenanceOrderChecklist::create([
                'tenant_id' => $order->tenant_id,
                'maintenance_order_id' => $order->id,
                'category' => $template->category,
                'item_name' => $template->item_name,
                'instructions' => $template->instructions,
                'section' => $template->section,
                'checklist_type' => $order->maintenance_type ?: $template->checklist_type,
                'is_template' => false,
                'is_completed' => false,
            ]);
        }
    }
}
