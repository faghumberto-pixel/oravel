<?php

namespace App\Services\Estoque;

use App\Models\StockGroup;

class EstoqueGrupos
{
    /** Cria os grupos padrao da empresa se ela ainda nao tem nenhum (idempotente). @return \Illuminate\Support\Collection<int, StockGroup> */
    public function garantirPadrao(string $tenantId)
    {
        $existentes = StockGroup::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('sort_order')->get();
        if ($existentes->isNotEmpty()) {
            return $existentes;
        }

        foreach (StockGroup::PADRAO as $i => [$nome, $cor]) {
            StockGroup::withoutGlobalScopes()->create(['tenant_id' => $tenantId, 'name' => $nome, 'color' => $cor, 'sort_order' => $i + 1, 'is_active' => true]);
        }

        return StockGroup::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('sort_order')->get();
    }
}
