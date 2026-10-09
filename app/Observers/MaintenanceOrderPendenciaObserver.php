<?php

namespace App\Observers;

use App\Services\DestinatariosAvisos;
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
        $this->notifyRole($pendencia);
    }

    /**
     * Ver EquipmentDamageObserver::notifyRole() -- mesmo motivo: nao usar
     * User::role($roleName) direto, pois Spatie resolve por nome
     * globalmente (ignora tenant_id) e falha silenciosamente pra qualquer
     * tenant que nao seja o primeiro a ter um papel com aquele nome.
     */
    private function notifyRole(MaintenanceOrderPendencia $pendencia): void
    {
        $recipients = DestinatariosAvisos::para($pendencia->tenant_id, 'pendencia_os');

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
