<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Asset extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_DISPONIVEL = 'disponivel';

    public const STATUS_LOCADO = 'locado';

    public const STATUS_MANUTENCAO = 'manutencao';

    public const STATUS_OPERANDO = 'operando';

    public const STATUS_AGUARDANDO_TRIAGEM = 'aguardando_triagem';

    protected static ?string $saasFeatureKey = 'tabela_assets';

    protected static ?string $saasPermissionSlug = 'ativo';

    protected static ?string $saasModuleLabel = 'Ativos / Frota';

    protected $guarded = [];

    protected $casts = [
        'acquisition_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'useful_life_years' => 'integer',
        'checklist' => 'array',
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

    public function equipmentMovements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }

    /**
     * Historico de idas e vindas do patio -- so' as movimentacoes com
     * chegada formalmente confirmada (App\Filament\Pages\PatioChegadas),
     * nao toda desmobilizacao concluida.
     */
    public function patioArrivals(): HasManyThrough
    {
        return $this->hasManyThrough(EquipmentPatioArrival::class, EquipmentMovement::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(EquipmentDamage::class);
    }

    public function abcMatrix(): HasOne
    {
        return $this->hasOne(AbcMatrix::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function equipmentReplacementsAsOriginal(): HasMany
    {
        return $this->hasMany(EquipmentReplacement::class, 'original_asset_id');
    }

    public function equipmentReplacementsAsReplacement(): HasMany
    {
        return $this->hasMany(EquipmentReplacement::class, 'replacement_asset_id');
    }

    public function checklistGroup(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class);
    }

    /**
     * Itens extras de checklist especificos deste ativo (is_template=true,
     * asset_id preenchido) -- somam ao basico do grupo sem alterar o
     * template do grupo, ex: itens do manual do fabricante daquele
     * equipamento em particular.
     */
    public function extraChecklistItems(): HasMany
    {
        return $this->hasMany(MaintenanceOrderChecklist::class, 'asset_id')->where('is_template', true);
    }

    public static function getCategories(): array
    {
        return AssetCategory::orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Busca flexivel pro Dossie Rapido (QR code/campo): tecnico no patio
     * raramente sabe o patrimonio exato de cor, ou digita com erro de
     * espaco/maiuscula. Busca por trecho em patrimonio, tag, nome ou
     * numero de serie -- nao so igualdade exata. Patrimonio comecando
     * exatamente pelo termo aparece primeiro (major sinal de intencao).
     *
     * @return Collection<int, Asset>
     */
    public static function search(string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return new Collection;
        }

        return static::query()
            ->where(function ($query) use ($term) {
                $query->where('patrimonio', 'ilike', "%{$term}%")
                    ->orWhere('tag', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhere('serial_number', 'ilike', "%{$term}%");
            })
            ->orderByRaw('(patrimonio ilike ?) desc', ["{$term}%"])
            ->orderBy('name')
            ->limit(20)
            ->get();
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

        if ($acquisitionValue <= 0 || $usefulLifeYears <= 0 || ! $acquisitionDate) {
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
