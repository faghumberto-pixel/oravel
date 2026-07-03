<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- o Gate remove o argumento de
 * classe nessas checagens (ve callPolicyMethod() no vendor), entao uma
 * Policy compartilhada como DynamicPolicy nao consegue saber de qual
 * modelo se trata nesses casos. Toda a logica real continua herdada do
 * AbstractPolicy (mesmo comportamento que DynamicPolicy ja dava para
 * view/update/delete, que recebem o $record e funcionavam normalmente).
 */
class AssetCategoryPolicy extends AbstractPolicy
{
}
