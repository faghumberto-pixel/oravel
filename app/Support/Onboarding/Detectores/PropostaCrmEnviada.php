<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\CrmProposta;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "envie sua primeira proposta": alguma proposta do CRM já foi enviada ao cliente. */
class PropostaCrmEnviada implements Detector
{
    public function concluido(Tenant $tenant): bool
    {
        return CrmProposta::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNotNull('enviada_em')->exists();
    }
}
