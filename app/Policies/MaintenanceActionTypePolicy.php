<?php

namespace App\Policies;

use App\Models\User;

/** Tipos de ação nunca são excluídos (o histórico das OS depende deles): só desativar. */
class MaintenanceActionTypePolicy extends AbstractPolicy
{
    public function delete(User $user, $model): bool
    {
        return false;
    }
}
