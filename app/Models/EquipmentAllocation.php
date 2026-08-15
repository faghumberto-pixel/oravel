<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alocacao de colaborador a um Asset. blocked/blocked_reason sao preenchidos
 * por uma trigger de banco (nao so' validacao de app) que confere
 * certificacao NR vigente contra nr_requirements_by_category -- ver
 * migration create_equipment_allocations_table. O model nunca deve setar
 * blocked manualmente na criacao normal; a trigger decide.
 */
class EquipmentAllocation extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_equipment_allocations';

    protected static ?string $saasPermissionSlug = 'alocacao_equipamento';

    protected static ?string $saasModuleLabel = 'Alocação de Equipamento';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'asset_id',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'blocked' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
