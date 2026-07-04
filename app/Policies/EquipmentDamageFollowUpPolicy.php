<?php

namespace App\Policies;

use App\Models\User;

/**
 * Historico de follow-up de cobranca tem valor juridico: uma vez criado,
 * ninguem edita ou apaga, nem admin do tenant. view/viewAny/create seguem
 * a AbstractPolicy normalmente (feature do plano + permissao + tenant).
 */
class EquipmentDamageFollowUpPolicy extends AbstractPolicy
{
    public function update(User $user, $model): bool
    {
        return false;
    }

    public function delete(User $user, $model): bool
    {
        return false;
    }
}
