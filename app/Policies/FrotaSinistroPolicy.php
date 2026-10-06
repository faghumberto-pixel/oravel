<?php

namespace App\Policies;

use App\Models\User;

/** Sinistros: lê, cria e edita pela AbstractPolicy (permissão sinistro_frota); o histórico não se apaga (cancela-se). */
class FrotaSinistroPolicy extends AbstractPolicy
{
    public function delete(User $user, $model): bool
    {
        return false;
    }
}
