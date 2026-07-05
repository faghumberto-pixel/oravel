<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao transportadora + mesmo tenant).
 */
class FreightCarrierPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de EquipmentDamagePolicy.
}
