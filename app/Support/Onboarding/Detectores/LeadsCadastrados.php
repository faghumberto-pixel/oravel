<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\CrmLead;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "cadastre seus leads": a empresa tem pelo menos 3 leads no CRM (importados ou criados à mão). */
class LeadsCadastrados implements Detector
{
    public const MINIMO = 3;

    public function concluido(Tenant $tenant): bool
    {
        return CrmLead::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count() >= self::MINIMO;
    }
}
