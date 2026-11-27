<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissão documento_modelo + mesmo tenant).
 */
class DocumentoModeloPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
