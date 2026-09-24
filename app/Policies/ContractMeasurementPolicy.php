<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- o Gate remove o argumento de
 * classe nessas checagens (ve callPolicyMethod() no vendor), entao uma
 * Policy compartilhada como DynamicPolicy nao consegue saber de qual
 * modelo se trata nesses casos. Toda a logica real continua herdada do
 * AbstractPolicy. Modelo real e' App\Domain\Fleet\Models\ContractMeasurement
 * (fora de App\Models -- achado real 2026-09-24, ver
 * AbstractPolicy::resolveModelClass()).
 */
class ContractMeasurementPolicy extends AbstractPolicy {}
