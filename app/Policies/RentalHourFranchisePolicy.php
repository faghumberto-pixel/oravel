<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- ver ContractMeasurementPolicy
 * pra explicação completa. Modelo real e'
 * App\Domain\Fleet\Models\RentalHourFranchise (fora de App\Models).
 */
class RentalHourFranchisePolicy extends AbstractPolicy {}
