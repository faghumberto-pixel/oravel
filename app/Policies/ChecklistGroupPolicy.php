<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- ver AbstractPolicy e o
 * comentario em ClientPolicy para o mecanismo completo.
 */
class ChecklistGroupPolicy extends AbstractPolicy
{
}
