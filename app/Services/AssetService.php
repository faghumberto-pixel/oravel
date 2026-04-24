<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssetService
{
    /**
     * Lista todos os Assets pertencentes ao tenant do usuário logado.
     * A lógica de multi-tenancy é aplicada automaticamente pelo BaseModel.
     *
     * @return LengthAwarePaginator
     */
    public function listarAssetPaginado(): LengthAwarePaginator
    {
        // A mágica acontece aqui.
        // Não precisamos de ->where('tenant_id', ...). Nosso TenantScope já faz isso.
        // A simplicidade é a nossa força.
        return Asset::latest()->paginate(15);
    }

    // Futuramente, outros métodos como criarAsset, atualizarAsset, etc., viverão aqui.
}