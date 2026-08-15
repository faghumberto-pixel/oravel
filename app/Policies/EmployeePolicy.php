<?php

namespace App\Policies;

/**
 * Delega 100% a' AbstractPolicy (feature do plano + permissao colaborador +
 * mesmo tenant). Nomeada (nao generica) pra resolveModelClass() funcionar
 * em viewAny/create -- mesmo motivo de FleetDriverPolicy/EquipmentDamagePolicy.
 */
class EmployeePolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
