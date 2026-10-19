<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Tipo de ação feita numa manutenção (troca de óleo, revisão, limpeza...): lista que a própria empresa monta. Nunca é excluído, só desativado. */
class MaintenanceActionType extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_maintenance_action_types';

    protected static ?string $saasPermissionSlug = 'tipo_acao_manutencao';

    protected static ?string $saasModuleLabel = 'Tipos de Ação da Manutenção';

    protected $fillable = ['tenant_id', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceOrder::class, 'maintenance_order_action_type');
    }

    public function scopeAtivos($query)
    {
        return $query->where('is_active', true);
    }
}
