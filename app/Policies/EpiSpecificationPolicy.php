<?php

namespace App\Policies;

/**
 * Policy nomeada para EpiSpecification.
 * Vazia de proposito: herda toda a logica da AbstractPolicy (feature do plano +
 * permissao + tenant). Ter o nome explicito permite que resolveModelClass()
 * deduza App\Models\EpiSpecification mesmo quando o Filament chama viewAny
 * sem passar o model.
 */
class EpiSpecificationPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
