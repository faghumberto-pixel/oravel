<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissão
 * apontamento_horimetro_mobile + mesmo tenant), mesmo padrão de HorimeterReadingPolicy.
 */
class EquipmentHourMeterPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
