<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao orcamento + mesmo tenant).
 */
class QuotePolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de EquipmentDamagePolicy.
}
