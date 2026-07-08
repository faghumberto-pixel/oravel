<?php

namespace App\Policies;

/**
 * Delega 100% a' AbstractPolicy (feature do plano + permissao motorista +
 * mesmo tenant). Nomeada (nao generica) pra resolveModelClass() funcionar
 * em viewAny/create -- mesmo motivo de EquipmentDamagePolicy.
 */
class FleetDriverPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
