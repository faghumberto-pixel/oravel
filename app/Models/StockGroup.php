<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Grupo de estoque (Administracao e escritorio, Equipamentos, Veiculos, Manutencao, Logistica...), cadastravel por cliente. */
class StockGroup extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    /** Uma chave de contrato so' para todo o "Estoque estruturado" (grupos, enderecos, alocacoes e contagens): a Central liga junto. */
    protected static ?string $saasFeatureKey = 'tabela_stock_structure';

    protected static ?string $saasPermissionSlug = 'grupo_estoque';

    protected static ?string $saasModuleLabel = 'Grupos de Estoque';

    /** Grupos que cada cliente recebe de partida (podem ser renomeados e ampliados). */
    public const PADRAO = [
        ['Administração e escritório', '#64748b'], ['Equipamentos', '#f97316'], ['Veículos', '#38bdf8'],
        ['Manutenção', '#16a34a'], ['Logística', '#a78bfa'], ['Outros', '#94a3b8'],
    ];

    protected $fillable = ['tenant_id', 'name', 'color', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
}
