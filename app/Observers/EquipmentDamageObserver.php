<?php

namespace App\Observers;

use App\Models\EquipmentDamage;
use App\Models\Role;
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

    /**
     * Nao usa User::role($roleName) direto: Spatie resolve papel por nome
     * globalmente (Role::findByName), ignorando tenant_id -- com varios
     * tenants tendo cada um seu proprio registro com o mesmo nome (ex:
     * "Comercial"), sempre resolvia pro primeiro criado no banco inteiro,
     * retornando lista vazia (silenciosamente) pra qualquer outro tenant.
     * Resolve a role certa primeiro e passa a instancia, que o scope aceita
     * sem re-resolver por nome.
     */
    private function notifyRole(EquipmentDamage $equipmentDamage, string $roleName, string $title): void
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('tenant_id', $equipmentDamage->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)
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
