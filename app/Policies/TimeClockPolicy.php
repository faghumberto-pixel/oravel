<?php

namespace App\Policies;

/**
 * Delega 100% a' AbstractPolicy (feature do plano + permissao ponto +
 * mesmo tenant). Nomeada (nao generica) pra resolveModelClass() funcionar
 * em viewAny/create -- mesmo motivo de EmployeePolicy/FleetDriverPolicy.
 */
class TimeClockPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
