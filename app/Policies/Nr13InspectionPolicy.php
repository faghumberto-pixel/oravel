<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- ver docblock de Nr13DocumentPolicy.
 */
class Nr13InspectionPolicy extends AbstractPolicy {}
