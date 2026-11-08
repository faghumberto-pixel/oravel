<?php

namespace App\Services\Catalogo\Aptidao;

use App\Models\CatalogoItem;
use App\Models\Tenant;

/** O motor so roda (observers, jobs, agenda) para tenants cujo CONTRATO tem a chave ligada: com ela desligada nada muda. */
class AptidaoGate
{
    public static function ligada(string|int|null $tenantId): bool
    {
        if (! $tenantId) {
            return false;
        }

        $tenant = Tenant::withoutGlobalScopes()->with(['plan' => fn ($q) => $q->withoutGlobalScopes()])->find($tenantId);

        return (bool) $tenant?->plan?->hasFeature(CatalogoItem::FEATURE_KEY);
    }
}
