<?php

namespace App\Observers;

use App\Models\EquipmentDamage;
use App\Models\User;
use Filament\Notifications\Notification;

class EquipmentDamageObserver
{
    public function updated(EquipmentDamage $equipmentDamage): void
    {
        if (! $equipmentDamage->wasChanged('status')) {
            return;
        }

        if ($equipmentDamage->status === EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR) {
            $this->notifyRole(
                $equipmentDamage,
                EquipmentDamage::ROLE_SUPERVISOR_MANUTENCAO,
                'Avaria aguardando revisão do supervisor',
            );
        }

        if ($equipmentDamage->status === EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL) {
            $this->notifyRole(
                $equipmentDamage,
                EquipmentDamage::ROLE_COMERCIAL,
                'Avaria confirmada, aguardando tratativa comercial',
            );

            if ($equipmentDamage->requires_replacement) {
                $this->notifyRole(
                    $equipmentDamage,
                    EquipmentDamage::ROLE_GERENTE_MANUTENCAO,
                    'Avaria exige substituição de equipamento',
                );
            }
        }
    }

    private function notifyRole(EquipmentDamage $equipmentDamage, string $roleName, string $title): void
    {
        $recipients = User::role($roleName)
            ->where('tenant_id', $equipmentDamage->tenant_id)
            ->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title($title)
                ->body('Ativo: '.($equipmentDamage->asset?->name ?? '—').' | Severidade: '.$equipmentDamage->severity)
                ->warning()
                ->sendToDatabase($recipient);
        }
    }
}
