<?php

namespace App\Policies;

use App\Models\User;

/** Multas: lê, cria e edita pela AbstractPolicy (permissão multa_frota); o histórico não se apaga (cancela-se). */
class FrotaMultaPolicy extends AbstractPolicy
{
    public function delete(User $user, $model): bool
    {
        return false;
    }
}
