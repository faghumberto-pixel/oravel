<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\Asset;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "cadastre seus equipamentos": a empresa tem pelo menos 3 ativos (cadastrados à mão ou importados). */
class AtivosCadastrados implements Detector
{
    public const MINIMO = 3;

    public function concluido(Tenant $tenant): bool
    {
        return Asset::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count() >= self::MINIMO;
    }
}
