<?php

namespace App\Support\Onboarding\Detectores;

use App\Models\Client;
use App\Models\Tenant;
use App\Support\Onboarding\Detector;

/** Passo "importe seus clientes": a empresa tem pelo menos um cliente cadastrado (importado ou criado à mão). */
class ClientesImportados implements Detector
{
    public function concluido(Tenant $tenant): bool
    {
        return Client::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists();
    }
}
