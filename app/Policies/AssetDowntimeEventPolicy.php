<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissão
 * parada_ativo + mesmo tenant), mesmo padrão de EquipmentDamagePolicy.
 */
class AssetDowntimeEventPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
