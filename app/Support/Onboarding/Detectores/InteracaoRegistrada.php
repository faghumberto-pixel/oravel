<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\CrmLeadInteraction;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "registre seu primeiro contato": existe ao menos uma interação (ligação, mensagem, visita) registrada em um lead da empresa. */
class InteracaoRegistrada implements Detector
{
    public function concluido(Tenant $tenant): bool
    {
        return CrmLeadInteraction::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists();
    }
}
