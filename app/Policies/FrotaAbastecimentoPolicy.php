<?php

namespace App\Policies;

use App\Models\User;

/** Abastecimentos: lê e cria pela AbstractPolicy (permissão abastecimento_frota); histórico não se edita nem se apaga. */
class FrotaAbastecimentoPolicy extends AbstractPolicy
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
