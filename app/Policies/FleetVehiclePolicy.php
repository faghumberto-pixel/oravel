<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao veiculo_frota + mesmo tenant).
 */
class FleetVehiclePolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de EquipmentDamagePolicy.
}
