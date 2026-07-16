<?php

use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialLocationStock;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

/**
 * Sem isso, Material.recalculateCurrentStock() (soma de material_location_stock)
 * zeraria o saldo de qualquer Material que ja tinha estoque ANTES de
 * MaterialStockService existir, na primeira operacao de estoque depois
 * deste deploy -- confirmado: 0 tenants tinham qualquer InternalUnit
 * cadastrado, 143/145 Materials com current_stock > 0. Cria uma filial
 * "Matriz" (type=matriz) pra cada tenant que ainda nao tem nenhuma
 * InternalUnit, e uma linha de material_location_stock la' com o saldo
 * que ja existia, preservando o dado real sem alterar o comportamento
 * visivel (o total continua o mesmo, so' passa a estar "em algum lugar"
 * em vez de solto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()->get(['id'])->each(function (Tenant $tenant) {
            $hasUnit = InternalUnit::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists();

            $defaultUnitId = $hasUnit
                ? InternalUnit::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('created_at')->value('id')
                : null;

            $materials = Material::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where(function ($q) {
                    $q->where('current_stock', '!=', 0)->orWhere('min_stock', '!=', 0);
                })
                ->get();

            if ($materials->isEmpty()) {
                return;
            }

            if (! $defaultUnitId) {
                $defaultUnitId = (string) Str::uuid7();
                InternalUnit::withoutGlobalScopes()->insert([
                    'id' => $defaultUnitId,
                    'tenant_id' => $tenant->id,
                    'name' => 'Matriz',
                    'code' => 'MATRIZ',
                    'type' => 'matriz',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($materials as $material) {
                $alreadyHasStock = MaterialLocationStock::withoutGlobalScopes()
                    ->where('material_id', $material->id)
                    ->exists();

                if ($alreadyHasStock) {
                    continue;
                }

                MaterialLocationStock::withoutGlobalScopes()->insert([
                    'id' => (string) Str::uuid7(),
                    'tenant_id' => $tenant->id,
                    'material_id' => $material->id,
                    'internal_unit_id' => $defaultUnitId,
                    'current_quantity' => (int) $material->current_stock,
                    'minimum_threshold' => (int) $material->min_stock,
                    'maximum_threshold' => $material->max_stock ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Backfill de dados -- sem reverso seguro (nao sabemos quais
        // linhas eram originais vs. criadas aqui), mesmo padrao de outras
        // migrations de backfill neste repositorio.
    }
};
