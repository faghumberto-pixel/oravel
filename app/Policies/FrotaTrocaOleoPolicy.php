<?php

namespace App\Policies;

use App\Models\User;

/** Histórico de óleo: lê e cria pela AbstractPolicy (permissão troca_oleo_frota), mas não edita nem apaga. */
class FrotaTrocaOleoPolicy extends AbstractPolicy
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
