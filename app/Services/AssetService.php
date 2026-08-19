<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssetService
{
    /**
     * Lista todos os Assets pertencentes ao tenant do usuário logado.
     */
    public function listarAssetPaginado(): LengthAwarePaginator
    {
        // Não precisamos de ->where('tenant_id', ...): Asset usa
        // Concerns\BelongsToTenant, cujo global scope já filtra pelo
        // tenant do usuário autenticado. App\Models\Scopes\TenantScope
        // (nome parecido, implementação diferente/mais antiga) não é
        // usado por este model.
        return Asset::latest()->paginate(15);
    }

    // Futuramente, outros métodos como criarAsset, atualizarAsset, etc., viverão aqui.
}
