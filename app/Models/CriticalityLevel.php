<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Fonte unica de niveis de criticidade -- antes existia so' como tabela
 * solta (code/name/color), sem tela de cadastro, com A/B/C hardcoded em
 * 4 lugares diferentes (AbcMatrixResource x2, MaintenanceOrderResource
 * x2). Agora tem Resource proprio (CriticalityLevelResource), e
 * is_urgent substitui a comparacao fixa code === 'alta' que o Kanban
 * usava pra decidir vermelho.
 */
class CriticalityLevel extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_criticality_levels';

    protected static ?string $saasPermissionSlug = 'nivel_criticidade';

    protected static ?string $saasModuleLabel = 'Níveis de Criticidade';

    protected $fillable = ['tenant_id', 'code', 'name', 'color', 'is_urgent', 'sort_order'];

    protected $casts = [
        'is_urgent' => 'boolean',
        'sort_order' => 'integer',
    ];
}
