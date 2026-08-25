<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao
 * proposta_comercial_template + mesmo tenant).
 */
class PropostaComercialTemplatePolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
