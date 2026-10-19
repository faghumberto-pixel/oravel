<?php

namespace App\Support;

use App\Models\AssetCategory;
use App\Models\MaintenanceActionType;
use App\Models\ReportedProblem;
use App\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Sugestões iniciais editáveis para quem usa a interface simples, para o sistema não nascer vazio. A empresa edita, desativa e acrescenta
 * o que quiser. Idempotente: só cria o que ainda não existe pelo nome.
 */
class CatalogosIniciais
{
    public const CATEGORIAS = ['Carro', 'Casa', 'Equipamento'];

    public const ACOES = ['Revisão', 'Troca de peça', 'Troca de óleo', 'Limpeza', 'Reparo', 'Pintura'];

    public const PROBLEMAS = ['Não liga', 'Barulho estranho', 'Vazamento', 'Desgaste'];

    public static function garantir(Tenant $tenant): void
    {
        foreach (self::CATEGORIAS as $nome) {
            AssetCategory::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => $nome], ['slug' => Str::slug($nome)]);
        }

        foreach (self::ACOES as $i => $nome) {
            MaintenanceActionType::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => $nome], ['sort_order' => $i, 'is_active' => true]);
        }

        foreach (self::PROBLEMAS as $nome) {
            ReportedProblem::withoutGlobalScopes()->firstOrCreate(['tenant_id' => $tenant->id, 'description' => $nome], ['is_active' => true]);
        }
    }
}
