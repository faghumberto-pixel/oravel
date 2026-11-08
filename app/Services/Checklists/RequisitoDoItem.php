<?php

namespace App\Services\Checklists;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\CatalogoRequisito;
use App\Models\CkItem;
use Illuminate\Support\Str;

/**
 * Liga um item de checklist ao requisito impeditivo do catalogo (G1) do grupo do ativo, que e o que faz a aptidao enxergar a
 * reprovacao. Se o item ja aponta para um requisito DESSE grupo, usa ele. Senao cria um requisito do tenant, determinista por
 * item (slug ck-...), com `sem_registro = ignorar`: so passa a valer para um ativo depois que ele for reprovado, entao os outros ativos
 * do grupo nao ficam "Apto com restricao" so porque a empresa criou um item critico.
 *
 * Grupo de catalogo = categoria que tem criticidade_padrao (categorias antigas da empresa nao viram grupo por tabela).
 */
class RequisitoDoItem
{
    public function garantir(CkItem $item, Asset $ativo): ?CatalogoRequisito
    {
        $grupo = $this->grupoDoCatalogo($ativo);

        if (! $grupo) {
            return null;
        }

        if ($item->catalogo_requisito_id) {
            $existente = CatalogoRequisito::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->where('grupo_id', $grupo->id)
                ->where('id', $item->catalogo_requisito_id)->where('ativo', true)->whereNull('deleted_at')->first();

            if ($existente) {
                return $existente;
            }
        }

        $slug = 'ck-'.Str::lower(Str::substr(str_replace('-', '', (string) $item->id), 0, 12));

        $requisito = CatalogoRequisito::withoutGlobalScopes()->withTrashed()->where('tenant_id', $ativo->tenant_id)->where('grupo_id', $grupo->id)->where('slug', $slug)->first();

        if ($requisito) {
            return $requisito->trashed() ? null : $requisito;
        }

        $catalogoItem = CatalogoItem::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $ativo->tenant_id, 'slug' => $slug],
            ['tipo' => 'checklist', 'nome' => Str::limit(trim((string) $item->descricao), 120, ''), 'origem' => 'tenant', 'personalizado' => true, 'ativo' => true, 'ordem' => 0],
        );

        $requisito = CatalogoRequisito::withoutGlobalScopes()->create([
            'tenant_id' => $ativo->tenant_id, 'grupo_id' => $grupo->id, 'slug' => $slug, 'item_id' => $catalogoItem->id, 'efeito' => 'IMP', 'gera_os' => true,
            'parametros' => ['sem_registro' => 'ignorar'], 'origem' => 'tenant', 'personalizado' => true, 'ativo' => true, 'ordem' => 999,
        ]);

        // Guarda o vinculo no item so quando ele ainda nao tem um (item usado por grupos diferentes mantem o primeiro).
        if (! $item->catalogo_requisito_id) {
            CkItem::withoutGlobalScopes()->whereKey($item->id)->update(['catalogo_requisito_id' => $requisito->id]);
        }

        return $requisito;
    }

    private function grupoDoCatalogo(Asset $ativo): ?AssetCategory
    {
        if (! $ativo->asset_category_id) {
            return null;
        }

        return AssetCategory::withoutGlobalScopes()->where('tenant_id', $ativo->tenant_id)->where('id', $ativo->asset_category_id)
            ->whereNotNull('criticidade_padrao')->whereNull('deleted_at')->first();
    }
}
