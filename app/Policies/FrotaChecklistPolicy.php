<?php

namespace App\Policies;

use App\Models\FrotaChecklist;
use App\Models\User;

/**
 * Checklists da frota: leitura/criação pela AbstractPolicy (permissão checklist_frota). O histórico é
 * imutável (não edita nem apaga) e a LIBERAÇÃO de um veículo bloqueado exige a permissão especial
 * liberar_checklist_frota (o administrador do cliente também pode).
 */
class FrotaChecklistPolicy extends AbstractPolicy
{
    public function update(User $user, $model): bool
    {
        return false;
    }

    public function delete(User $user, $model): bool
    {
        return false;
    }

    public function liberar(User $user, FrotaChecklist $checklist): bool
    {
        if (! $this->isSameTenant($user, $checklist) && ! $user->isSuperAdmin()) {
            return false;
        }

        return $user->isAdmin() || $user->can('liberar_checklist_frota');
    }
}
