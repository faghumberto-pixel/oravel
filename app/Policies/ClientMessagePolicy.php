<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao
 * mensagem_cliente + mesmo tenant). Mesmo padrao de EquipmentPickupRequestPolicy.
 */
class ClientMessagePolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
