<?php

namespace App\Policies;

use App\Models\User;

/** Problemas comuns nunca são excluídos (as OS antigas apontam para eles): só desativar. */
class ReportedProblemPolicy extends AbstractPolicy
{
    public function delete(User $user, $model): bool
    {
        return false;
    }
}
