<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao
 * solicitacao_retirada + mesmo tenant). Mesmo padrao de EquipmentReplacementPolicy.
 */
class EquipmentPickupRequestPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
