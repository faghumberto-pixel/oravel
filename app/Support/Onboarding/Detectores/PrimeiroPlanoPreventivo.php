<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\MaintenancePlan;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "crie seu primeiro plano preventivo": existe ao menos um plano de manutenção preventiva da empresa. */
class PrimeiroPlanoPreventivo implements Detector
{
    public function concluido(Tenant $tenant): bool
    {
        return MaintenancePlan::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists();
    }
}
