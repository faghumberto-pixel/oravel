<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- o Gate remove o argumento de
 * classe nessas checagens, entao uma Policy compartilhada como DynamicPolicy
 * nao consegue saber de qual modelo se trata nesses casos. Toda a logica real
 * continua herdada do AbstractPolicy.
 */
class WarehouseStockPolicy extends AbstractPolicy
{
}
