<?php

namespace App\Policies;

/**
 * Policy nomeada para AIAnalysis.
 * Vazia de proposito: herda toda a logica da AbstractPolicy (feature do plano +
 * permissao + tenant). Ter o nome explicito permite que resolveModelClass()
 * deduza App\Models\AIAnalysis mesmo quando o Filament chama viewAny
 * sem passar o model.
 */
class AIAnalysisPolicy extends AbstractPolicy
{
    // Intencionalmente vazia.
}
