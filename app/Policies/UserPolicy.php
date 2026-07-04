<?php

namespace App\Policies;

/**
 * Antes retornava true sempre para tudo (sem checar tenant, permissao ou
 * plano). Migrado para o padrao real do sistema: delega 100% a
 * AbstractPolicy (feature 'tabela_users' do plano + permissao
 * ler_funcionario/criar_funcionario/etc + mesmo tenant).
 */
class UserPolicy extends AbstractPolicy
{
    // Intencionalmente vazia, mesmo padrao de AssetPolicy.
}
