<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FleetStatus extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'is_available',
        'capacity_label',
        'last_maintenance_id',
    ];

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