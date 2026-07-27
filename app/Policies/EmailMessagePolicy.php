<?php

namespace App\Policies;

/**
 * Policy nomeada para EmailMessage.
 * Vazia de proposito: herda toda a logica da AbstractPolicy (feature do plano +
 * permissao + tenant). Ter o nome explicito permite que resolveModelClass()
 * deduza App\Models\EmailMessage mesmo quando o Filament chama viewAny
 * sem passar o model.
 */
class EmailMessagePolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
