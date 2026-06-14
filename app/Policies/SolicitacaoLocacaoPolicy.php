<?php

namespace App\Policies;

/**
 * Policy nomeada para SolicitacaoLocacao.
 * Vazia de proposito: herda toda a logica da AbstractPolicy (feature do plano +
 * permissao + tenant). Ter o nome explicito permite que resolveModelClass()
 * deduza App\Models\SolicitacaoLocacao mesmo quando o Filament chama viewAny
 * sem passar o model — o que a DynamicPolicy generica nao conseguia fazer.
 */
class SolicitacaoLocacaoPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
