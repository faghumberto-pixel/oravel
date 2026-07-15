<?php

namespace App\Observers;

use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrderPendencia;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;

class MaintenanceOrderPendenciaObserver
{
    public function created(MaintenanceOrderPendencia $pendencia): void
    {
        foreach ([
            EquipmentDamage::ROLE_SUPERVISOR_MANUTENCAO,
            EquipmentDamage::ROLE_GERENTE_MANUTENCAO,
            EquipmentDamage::ROLE_ANALISTA_MANUTENCAO,
        ] as $roleName) {
            $this->notifyRole($pendencia, $roleName);
        }
    }

    /**
     * Ver EquipmentDamageObserver::notifyRole() -- mesmo motivo: nao usar
     * User::role($roleName) direto, pois Spatie resolve por nome
     * globalmente (ignora tenant_id) e falha silenciosamente pra qualquer
     * tenant que nao seja o primeiro a ter um papel com aquele nome.
     */
    private function notifyRole(MaintenanceOrderPendencia $pendencia, string $roleName): void
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('tenant_id', $pendencia->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)
            ->where('tenant_id', $pendencia->tenant_id)
            ->get();

        $osNumber = $pendencia->maintenanceOrder?->os_number ?? '—';
        $assetName = $pendencia->maintenanceOrder?->asset?->name ?? '—';

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Nova pendência: OS '.$osNumber)
                ->body('Ativo: '.$assetName.' — '.$pendencia->description)
                ->warning()
                ->actions([
                    Action::make('view')
                        ->button()
                        ->url(route('filament.admin.resources.maintenance-orders.edit', $pendencia->maintenance_order_id)),
                ])
                ->sendToDatabase($recipient);
        }
    }
}
