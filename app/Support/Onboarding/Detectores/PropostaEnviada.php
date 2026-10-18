<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\PropostaComercial;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "crie sua primeira proposta": alguma proposta já chegou ao cliente (link de aceite gerado) ou foi respondida. */
class PropostaEnviada implements Detector
{
    public const STATUS = [
        PropostaComercial::STATUS_APROVADA_INTERNA,
        PropostaComercial::STATUS_ACEITA_PELO_CLIENTE,
        PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE,
    ];

    public function concluido(Tenant $tenant): bool
    {
        return PropostaComercial::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('status', self::STATUS)->exists();
    }
}
