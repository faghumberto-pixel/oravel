<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao frete + mesmo tenant).
 */
class FreightRecordPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de EquipmentDamagePolicy.
}
