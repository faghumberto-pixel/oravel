<?php

namespace App\Services;

use App\Models\MaintenanceOrderMaterial;
use App\Models\Material;
use App\Models\PartsRequest;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Regras de negocio ao aplicar um Material numa OS/Preventiva: baixa de
 * estoque, requisicao automatica de compra quando fica abaixo do minimo, e
 * alerta de garantia quando a mesma peca (mesmo numero de serie) e aplicada
 * de novo dentro do prazo de garantia -- em vez de comprar peca nova.
 */
class MaterialConsumptionService
{
    public function recordConsumption(MaintenanceOrderMaterial $consumption): void
    {
        if (! $consumption->material_id) {
            return;
        }

        $material = $consumption->material ?? Material::find($consumption->material_id);

        if (! $material) {
            return;
        }

        DB::transaction(function () use ($material, $consumption) {
            $material->decrement('current_stock', (float) $consumption->quantity);
            $material->refresh();

            StockMovement::record(
                $material,
                StockMovement::TYPE_SAIDA_CONSUMO,
                (float) $consumption->quantity,
                (float) $material->current_stock,
                $consumption,
                Auth::id()
            );

            if ($material->isLowStock()) {
                $this->createPartsRequestIfNeeded($material, $consumption);
            }
        });

        $this->alertIfWithinWarranty($material, $consumption);
    }

    private function createPartsRequestIfNeeded(Material $material, MaintenanceOrderMaterial $consumption): void
    {
        $hasOpenRequest = PartsRequest::where('tenant_id', $material->tenant_id)
            ->where('material_id', $material->id)
            ->whereIn('status', ['pendente', 'pedida'])
            ->exists();

        if ($hasOpenRequest) {
            return;
        }

        PartsRequest::create([
            'tenant_id' => $material->tenant_id,
            'maintenance_order_id' => $consumption->maintenance_order_id,
            'material_id' => $material->id,
            'quantity' => max((float) ($material->max_stock - $material->current_stock), (float) $consumption->quantity),
            'status' => 'pendente',
            'cost_at_time' => $material->last_purchase_price ?? $material->unit_cost,
        ]);
    }

    private function alertIfWithinWarranty(Material $material, MaintenanceOrderMaterial $consumption): void
    {
        if (! $material->requires_serial_number || ! $material->warranty_days || blank($consumption->serial_number)) {
            return;
        }

        $priorApplication = MaintenanceOrderMaterial::where('material_id', $material->id)
            ->where('serial_number', $consumption->serial_number)
            ->where('id', '!=', $consumption->id)
            ->where('created_at', '>=', now()->subDays($material->warranty_days))
            ->latest('created_at')
            ->first();

        if (! $priorApplication) {
            return;
        }

        $this->notifyAdmins(
            $material,
            'Possível acionamento de garantia',
            "Peça \"{$material->name}\" (nº série {$consumption->serial_number}) já foi aplicada em ".
                $priorApplication->created_at->format('d/m/Y')." — dentro do prazo de garantia de {$material->warranty_days} dias. ".
                'Considere acionar o fornecedor antes de comprar outra.'
        );
    }

    /**
     * Nao usa User::role('admin') direto: Spatie resolve por nome
     * globalmente (Role::findByName), ignorando tenant_id -- resolve a role
     * escopada ao tenant primeiro (mesmo bug/fix ja aplicado nos outros
     * Observers desta base).
     */
    private function notifyAdmins(Material $material, string $title, string $body): void
    {
        $role = Role::where('name', 'admin')
            ->where('guard_name', 'web')
            ->where('tenant_id', $material->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)->where('tenant_id', $material->tenant_id)->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}
