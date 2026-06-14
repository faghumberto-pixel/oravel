<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Auth;

class FleetStatus extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_fleet_statuses";
    protected static ?string $saasPermissionSlug = "fila_logistica";
    protected static ?string $saasModuleLabel = "Fila de Logistica";

    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'is_available',
        'capacity_label',
        'last_maintenance_id',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Injeção automática e segura do tenant_id para isolamento dos dados
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id 
                                    ?? filament()->getTenant()?->id 
                                    ?? session('tenant_id');
            }
        });
    }

    /**
     * Relacionamento com o Tenant (Multi-tenancy)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacionamento com o Equipamento (Ativo)
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    /**
     * Relacionamento com a Ordem de Serviço de origem
     */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'last_maintenance_id');
    }
}
