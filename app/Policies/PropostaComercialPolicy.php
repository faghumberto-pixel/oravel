<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao
 * proposta_comercial + mesmo tenant), mesmo padrão de QuotePolicy.
 */
class PropostaComercialPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
