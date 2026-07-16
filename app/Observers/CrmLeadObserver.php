<?php

namespace App\Observers;

use App\Models\CrmLead;
use App\Models\CrmLeadAssignment;

class CrmLeadObserver
{
    /**
     * So' loga fora de contexto autenticado real (seeders/tinker/console
     * nao tem "quem mudou") -- mesmo motivo documentado em
     * AppServiceProvider::logModelMutation() pro log geral de atividade.
     */
    public function created(CrmLead $lead): void
    {
        if (! $lead->assigned_user_id) {
            return;
        }

        $this->logAssignment($lead, null, $lead->assigned_user_id);
    }

    public function updating(CrmLead $lead): void
    {
        if (! $lead->isDirty('assigned_user_id')) {
            return;
        }

        $this->logAssignment($lead, $lead->getOriginal('assigned_user_id'), $lead->assigned_user_id);
    }

    private function logAssignment(CrmLead $lead, ?string $fromUserId, ?string $toUserId): void
    {
        $userId = auth()->id();

        if (! $userId || ! $toUserId) {
            return;
        }

        CrmLeadAssignment::create([
            'tenant_id' => $lead->tenant_id,
            'crm_lead_id' => $lead->id,
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'changed_by_user_id' => $userId,
        ]);
    }
}
