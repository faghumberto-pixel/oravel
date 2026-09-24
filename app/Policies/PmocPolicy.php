<?php

namespace App\Policies;

/**
 * Delega 100% à AbstractPolicy (feature do plano + permissao ler_pmoc +
 * mesmo tenant). Criada junto com Pmoc::class ganhar HasSaaSMetadata
 * (2026-09-23) -- sem uma Policy própria nomeada, Gate::guessPolicyNamesUsing()
 * cairia na DynamicPolicy compartilhada, que não consegue identificar qual
 * model está autorizando (ver CLAUDE.md).
 */
class PmocPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, como AssetPolicy/as demais.
}
