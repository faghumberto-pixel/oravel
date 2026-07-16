<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao + mesmo tenant).
 */
class CriticalityLevelPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, como a DynamicPolicy.
}
