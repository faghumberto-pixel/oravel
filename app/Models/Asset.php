<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Carbon\Carbon;

class Asset extends Model
{
    use HasFactory;
    use LogsActivity;
    use HasUuids;
    use BelongsToTenant;
    use HasSaaSMetadata;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static ?string $saasFeatureKey = "tabela_assets";
    protected static ?string $saasPermissionSlug = "ativo";
    protected static ?string $saasModuleLabel = "Ativos / Frota";
    protected $guarded = [];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'useful_life_years' => 'integer',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function activities()
    {
        return $this->activitiesAsSubject();
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(SolicitacaoLocacao::class, 'asset_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'asset_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public static function getCategories(): array
    {
        return AssetCategory::orderBy('name')->pluck('name', 'id')->toArray();
    }

    public static function getDefaultChecklist($categoryId): array
    {
        return [];
    }

    public function getDepreciationData(): array
    {
        $acquisitionValue = (float) ($this->acquisition_value ?? 0);
        $residualValue = (float) ($this->residual_value ?? 0);
        $usefulLifeYears = (int) ($this->useful_life_years ?? 0);
        $acquisitionDate = $this->acquisition_date;

        if ($acquisitionValue <= 0 || $usefulLifeYears <= 0 || !$acquisitionDate) {
            return [
                'current_value' => $acquisitionValue,
                'accumulated_depreciation' => 0,
                'depreciation_percentage' => 0,
                'monthly_depreciation' => 0,
            ];
        }

        $depreciableAmount = max($acquisitionValue - $residualValue, 0);
        $monthlyDepreciation = $depreciableAmount / ($usefulLifeYears * 12);

        $monthsElapsed = Carbon::parse($acquisitionDate)->diffInMonths(now());
        $accumulatedDepreciation = min($monthlyDepreciation * $monthsElapsed, $depreciableAmount);

        $currentValue = $acquisitionValue - $accumulatedDepreciation;
        $percentage = $depreciableAmount > 0 ? ($accumulatedDepreciation / $depreciableAmount) * 100 : 0;

        return [
            'current_value' => round($currentValue, 2),
            'accumulated_depreciation' => round($accumulatedDepreciation, 2),
            'depreciation_percentage' => round($percentage, 1),
            'monthly_depreciation' => round($monthlyDepreciation, 2),
        ];
    }

    public function getFinancialSummary(): array
    {
        $depreciation = $this->getDepreciationData();

        $totalMaintenanceCost = (float) $this->maintenanceOrders()->sum('total_order_cost');
        $totalLaborCost = (float) $this->maintenanceOrders()->sum('labor_cost');
        $totalMaterialCost = (float) $this->maintenanceOrders()->sum('material_cost');
        $totalLogisticsCost = (float) $this->maintenanceOrders()->sum('logistics_cost');
        $totalRentalRevenue = (float) $this->contracts()->sum('price');

        $result = $totalRentalRevenue - $totalMaintenanceCost;

        return [
            'acquisition_value' => (float) ($this->acquisition_value ?? 0),
            'current_value' => $depreciation['current_value'],
            'accumulated_depreciation' => $depreciation['accumulated_depreciation'],
            'depreciation_percentage' => $depreciation['depreciation_percentage'],
            'total_labor_cost' => round($totalLaborCost, 2),
            'total_material_cost' => round($totalMaterialCost, 2),
            'total_logistics_cost' => round($totalLogisticsCost, 2),
            'total_maintenance_cost' => round($totalMaintenanceCost, 2),
            'total_rental_revenue' => round($totalRentalRevenue, 2),
            'result' => round($result, 2),
        ];
    }
}
