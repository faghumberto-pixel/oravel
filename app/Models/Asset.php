<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use App\Models\Contracts\FiltersByTechnician;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use App\Models\Traits\BelongsToTenant;
use App\Models\MeasurementUnit;
use App\Models\AssetCategory;

class Asset extends Model implements FiltersByTechnician
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_assets";
    protected static ?string $saasPermissionSlug = "ativo";
    protected static ?string $saasModuleLabel = "Ativos";

    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    const STATUS_DISPONIVEL = 'disponivel';
    const STATUS_LOCADO     = 'locado';
    const STATUS_MANUTENCAO = 'manutencao';
    const STATUS_OPERANDO   = 'operando';

    protected $fillable = [
        'name', 'patrimonio', 'asset_tag', 'serial_number', 
        'asset_category_id', // Ajustado para seguir o padrão _id
        'measurement_unit_id', 
        'criticality_level', 'status', 'tenant_id', 'description', 'checklist',
        'horimetro_inicial', 'last_horimetro', 'image_path',
        'purchase_price', 'residual_value', 'useful_life_months'
    ];

    protected $casts = [
        'checklist'           => 'array',
        'horimetro_inicial'   => 'decimal:2',
        'last_horimetro'      => 'decimal:2',
        'purchase_price'      => 'decimal:2',
        'residual_value'      => 'decimal:2',
        'useful_life_months'  => 'integer',
    ];

    public function getCurrentValueAttribute(): float
    {
        $purchasePrice = (float) $this->purchase_price;
        $residualValue = (float) $this->residual_value;
        $usefulLife = (int) $this->useful_life_months;

        if ($purchasePrice <= 0 || $usefulLife <= 0) {
            return $purchasePrice;
        }

        $monthsPassed = $this->created_at ? $this->created_at->diffInMonths(now()) : 0;

        if ($monthsPassed >= $usefulLife) {
            return $residualValue;
        }

        $monthlyDepreciation = ($purchasePrice - $residualValue) / $usefulLife;
        $totalDepreciation = $monthlyDepreciation * $monthsPassed;

        return max($residualValue, $purchasePrice - $totalDepreciation);
    }

    public function getQrCodeUrlAttribute(): string
    {
        return URL::to("/admin/assets/{$this->id}");
    }

    public function tenant(): BelongsTo 
    { 
        return $this->belongsTo(Tenant::class, 'tenant_id'); 
    }
    
    public function assetCategory(): BelongsTo 
    { 
        return $this->belongsTo(AssetCategory::class, 'asset_category_id'); 
    }

    public function measurementUnit(): BelongsTo 
    { 
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id'); 
    }

    public function maintenanceOrders(): HasMany 
    { 
        return $this->hasMany(MaintenanceOrder::class, 'asset_id')->latest(); 
    }

    public function criticalityLevel(): BelongsTo 
    { 
        return $this->belongsTo(CriticalityLevel::class, 'criticality_level'); 
    }



    public function scopeWithChecklistIssues(Builder $query): Builder
    {
        return $query->whereJsonContains('checklist', [['status' => false]]);
    }

    public static function getCriticalityLevels(): array
    {
        try {
            return \App\Models\CriticalityLevel::pluck('name', 'id')->toArray();
        } catch (\Exception $e) {
            return ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'];
        }
    }
}