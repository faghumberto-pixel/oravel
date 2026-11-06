<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao + mesmo tenant).
 */
class StockCountItemPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, como a DynamicPolicy.
}
