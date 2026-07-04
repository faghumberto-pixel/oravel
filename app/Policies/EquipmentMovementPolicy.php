<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao movimentacao_equipamento + mesmo tenant).
 */
class EquipmentMovementPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de AssetPolicy.
}
