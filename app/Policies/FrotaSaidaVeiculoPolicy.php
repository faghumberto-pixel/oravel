<?php

namespace App\Policies;

use App\Models\User;

/** Registro de entrada e saída: lê e cria pela AbstractPolicy (permissão saida_veiculo_frota); histórico não se apaga. */
class FrotaSaidaVeiculoPolicy extends AbstractPolicy
{
    public function delete(User $user, $model): bool
    {
        return false;
    }
}
