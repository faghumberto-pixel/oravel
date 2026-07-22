<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissão
 * apontamento_horimetro + mesmo tenant), mesmo padrão de EquipmentDamagePolicy.
 */
class HorimeterReadingPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
