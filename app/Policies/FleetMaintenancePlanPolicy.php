<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao plano_manutencao_veiculo + mesmo tenant).
 */
class FleetMaintenancePlanPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de EquipmentDamagePolicy.
}
