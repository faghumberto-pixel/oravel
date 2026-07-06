<?php

namespace App\Policies;

/**
 * Policy dedicada apenas para o Laravel conseguir identificar o modelo em
 * checagens sem $record (viewAny/create) -- ver MaintenancePlanPolicy para
 * a explicacao completa do porque uma Policy compartilhada (DynamicPolicy)
 * nao resolve isso sozinha.
 */
class PreventiveMaintenanceExecutionPolicy extends AbstractPolicy {}
