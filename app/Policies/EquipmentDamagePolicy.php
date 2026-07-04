<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao avaria_equipamento + mesmo tenant).
 */
class EquipmentDamagePolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de EquipmentMovementPolicy.
}
