<?php

namespace App\Observers;

use App\Models\EquipmentDamage;
use App\Models\PropostaComercial;
use App\Models\Role;
use App\Models\User;
use Filament\Notifications\Notification;

/**
 * Notifica a role "Comercial" quando uma proposta chega em
 * enviada_para_comercial -- reaproveita a mesma role já usada em
 * EquipmentDamage (EquipmentDamage::ROLE_COMERCIAL), e o mesmo cuidado de
 * resolver por tenant_id antes de resolver por nome (ver comentário em
 * EquipmentDamageObserver::notifyRole() sobre o bug de escopo do Spatie).
 */
class PropostaComercialObserver
{
    public function updated(PropostaComercial $proposta): void
    {
        if (! $proposta->wasChanged('status')) {
            return;
        }

        if ($proposta->status === PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL) {
            $this->notifyRole($proposta);
        }
    }

    private function notifyRole(PropostaComercial $proposta): void
    {
        $role = Role::where('name', EquipmentDamage::ROLE_COMERCIAL)
            ->where('guard_name', 'web')
            ->where('tenant_id', $proposta->tenant_id)
            ->first();

        if (! $role) {
            return;
        }

        $recipients = User::role($role)
            ->where('tenant_id', $proposta->tenant_id)
            ->get();

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Proposta comercial aguardando revisão')
                ->body('Cliente: '.($proposta->client?->name ?? '—').' | Valor: R$ '.number_format((float) $proposta->total_value, 2, ',', '.'))
                ->info()
                ->sendToDatabase($recipient);
        }
    }
}
