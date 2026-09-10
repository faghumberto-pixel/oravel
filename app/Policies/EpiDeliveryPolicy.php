<?php

namespace App\Policies;

/**
 * Policy nomeada para EpiDelivery.
 * Vazia de proposito: herda toda a logica da AbstractPolicy (feature do plano +
 * permissao + tenant). Ter o nome explicito permite que resolveModelClass()
 * deduza App\Models\EpiDelivery mesmo quando o Filament chama viewAny
 * sem passar o model.
 */
class EpiDeliveryPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
