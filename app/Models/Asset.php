<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Asset extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tag', 'name', 'description', 'patrimonio', 'serial_number', 'status', 
        'criticality_level_id', 'current_location_id', 'client_id', 'internal_unit_id', 
        'department_id', 'checklist_group_id', 'tenant_id', 'capacity_value', 
        'capacity_unit', 'acquisition_date', 'acquisition_value', 'residual_value', 
        'useful_life_years', 'manual_items', 'specification', 'manufacturing_year',
        'latitude', 'longitude'
    ];

    protected $casts = [
        'manual_items' => 'array',
        'is_vehicle' => 'boolean',
        'acquisition_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'cost_per_km' => 'decimal:2',
    ];

    /**
     * Boot do Model para garantir a integridade do Multi-tenancy
     */
    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && auth()->check()) {
                // Injeta o tenant_id do usuário autenticado antes de salvar no banco
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /** --- Lógica de ROI e Ciclo de Vida (LCC) --- **/
    public function getTotalMaintenanceCostAttribute(): float
    {
        return (float) ($this->maintenanceOrders()->where('status', 'Concluída')->sum('total_order_cost') ?? 0);
    }

    public function getMaintenanceRoiAttribute(): float
    {
        if (!$this->acquisition_value || $this->acquisition_value <= 0) return 0;
        return ($this->total_maintenance_cost / (float) $this->acquisition_value) * 100;
    }

    public function getLccAnalysisAttribute(): string
    {
        $roi = $this->maintenance_roi;
        if ($roi > 60) return '🚨 Substituição Recomendada';
        if ($roi > 40) return '⚠️ Monitoramento Crítico';
        return '✅ Operação Econômica';
    }

    /** --- Relações --- **/
    public function checklistGroup(): BelongsTo { return $this->belongsTo(ChecklistGroup::class, 'checklist_group_id'); }
    public function criticalityLevel(): BelongsTo { return $this->belongsTo(CriticalityLevel::class, 'criticality_level_id'); }
    public function internalUnit(): BelongsTo { return $this->belongsTo(InternalUnit::class, 'internal_unit_id'); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class, 'client_id'); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class, 'tenant_id'); }
    public function contracts(): HasMany { return $this->hasMany(Contract::class); }
    public function maintenanceOrders(): HasMany { return $this->hasMany(MaintenanceOrder::class); }
    public function movements(): HasMany { return $this->hasMany(AssetMovement::class, 'asset_id')->orderBy('moved_at', 'desc'); }
}