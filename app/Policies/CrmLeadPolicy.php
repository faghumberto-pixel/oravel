<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- ver AbstractPolicy.
 */
class CrmLeadPolicy extends AbstractPolicy {}
