<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Execucao de um item de Manutencao Preventiva (MaintenancePlan) num Ativo
 * especifico -- diferente do checklist de inspecao (MaintenanceOrderChecklist,
 * "esta tudo OK") e do checklist de mobilizacao (EquipmentMovementItemTemplate).
 * Aqui o registro e "troquei o oleo com o horimetro em X, proxima em Y".
 */
class PreventiveMaintenanceExecution extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    protected static ?string $saasFeatureKey = 'tabela_preventive_maintenance_executions';

    protected static ?string $saasPermissionSlug = 'preventiva';

    protected static ?string $saasModuleLabel = 'Manutencao Preventiva';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'maintenance_plan_id',
        'maintenance_order_id',
        'technician_id',
        'horimetro_at_execution',
        'next_due_horimetro',
        'observacao',
    ];

    protected $casts = [
        'horimetro_at_execution' => 'decimal:2',
        'next_due_horimetro' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (PreventiveMaintenanceExecution $execution) {
            if ($execution->maintenancePlan) {
                $execution->next_due_horimetro = $execution->horimetro_at_execution + $execution->maintenancePlan->interval_hours;
            }
        });

        static::saved(function (PreventiveMaintenanceExecution $execution) {
            $asset = $execution->asset;

            if ($asset && (float) $execution->horimetro_at_execution >= (float) $asset->horimetro_atual) {
                $asset->updateQuietly([
                    'horimetro_atual' => $execution->horimetro_at_execution,
                    'last_horimetro' => $execution->horimetro_at_execution,
                ]);
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Pecas consumidas nesta preventiva -- vive em maintenance_order_materials
     * (nao tem FK direta pra esta tabela), casado pelo mesmo maintenance_order_id
     * ja que a peca e consumida no contexto da OS a que a preventiva pertence.
     */
    public function materials(): HasMany
    {
        return $this->hasMany(MaintenanceOrderMaterial::class, 'maintenance_order_id', 'maintenance_order_id');
    }
}
