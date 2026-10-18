<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\PropostaComercial;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "acompanhe o aceite": o cliente abriu o link da proposta ou já respondeu. */
class PropostaVista implements Detector
{
    public function concluido(Tenant $tenant): bool
    {
        return PropostaComercial::withoutGlobalScopes()->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->whereNotNull('client_viewed_at')->orWhereIn('status', [PropostaComercial::STATUS_ACEITA_PELO_CLIENTE, PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE]))
            ->exists();
    }
}
