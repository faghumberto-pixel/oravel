<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\MaintenanceOrder;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "abra sua primeira ordem de serviço": existe ao menos uma OS da empresa. */
class PrimeiraOrdemDeServico implements Detector
{
    public function concluido(Tenant $tenant): bool
    {
        return MaintenanceOrder::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists();
    }
}
