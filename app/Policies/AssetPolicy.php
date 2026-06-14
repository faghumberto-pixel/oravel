<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao ler_ativo + mesmo tenant).
 * A versao anterior tinha canAccess() proprio que ignorava permissao e vazava o modulo.
 */
class AssetPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, como a DynamicPolicy.
}
