<?php

namespace App\Policies;

/**
 * Policy nomeada para Depot.
 * Vazia de proposito: herda toda a logica da AbstractPolicy (feature do plano +
 * permissao + tenant). Ter o nome explicito permite que resolveModelClass()
 * deduza App\Models\Depot mesmo quando o Filament chama viewAny
 * sem passar o model.
 */
class DepotPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
