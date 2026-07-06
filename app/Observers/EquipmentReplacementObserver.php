<?php

namespace App\Observers;

use App\Models\EquipmentDamage;
use App\Models\EquipmentReplacement;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;

class EquipmentReplacementObserver
{
    public function created(EquipmentReplacement $equipmentReplacement): void
    {
        $this->notifyRole(
            $equipmentReplacement,
            EquipmentDamage::ROLE_COMERCIAL,
            'Troca de equipamento solicitada',
            $this->severityForUrgency($equipmentReplacement->urgency),
        );
    }

    public function updated(EquipmentReplacement $equipmentReplacement): void
    {
        if (! $equipmentReplacement->wasChanged('status')) {
            return;
        }

        if ($equipmentReplacement->status === EquipmentReplacement::STATUS_DESMOBILIZACAO_ANDAMENTO) {
            $this->notifyRole(
                $equipmentReplacement,
                EquipmentReplacement::ROLE_LOGISTICA,
                'Movimentações de troca de equipamento prontas para execução',
                'info',
            );
        }

        if ($equipmentReplacement->status === EquipmentReplacement::STATUS_CONCLUIDO) {
            if ($requester = $equipmentReplacement->requestedBy) {
                Notification::make()
                    ->title('Troca de equipamento concluída')
                    ->body('Ativo substituto entregue e assinado pelo cliente.')
                    ->success()
                    ->sendToDatabase($requester);
            }
        }
    }

    private function severityForUrgency(string $urgency): string
    {
        return match ($urgency) {
            EquipmentReplacement::URGENCY_CRITICO => 'danger',
            EquipmentReplacement::URGENCY_URGENTE => 'warning',
            default => 'info',
        };
    }

    /**
     * Ver EquipmentDamageObserver::notifyRole() -- mesmo motivo: nao usar
     * User::role($roleName) direto, pois Spatie resolve por nome
     * globalmente (ignora tenant_id) e falha silenciosamente pra qualquer
     * tenant que nao seja o primeiro a ter um papel com aquele nome.
     */
    private function notifyRole(EquipmentReplacement $equipmentReplacement, string $roleName, string $title, string $method): void
    {
        $role = Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->where('tenant_id', $equipmentReplacement->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)
            ->where('tenant_id', $equipmentReplacement->tenant_id)
            ->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title($title)
                ->body('Ativo original: '.($equipmentReplacement->originalAsset?->name ?? '—').' | Urgência: '.$equipmentReplacement->urgency)
                ->{$method}()
                ->sendToDatabase($recipient);
        }
    }
}
