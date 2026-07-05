<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EquipmentMovement extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;

    public const TYPE_MOBILIZACAO = 'mobilizacao';

    public const TYPE_DESMOBILIZACAO = 'desmobilizacao';

    public const STATUS_AGUARDANDO_VISTORIA = 'aguardando_vistoria';

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    public const STATUS_CONCLUIDO = 'concluido';

    protected static ?string $saasFeatureKey = 'tabela_equipment_movements';

    protected static ?string $saasPermissionSlug = 'movimentacao_equipamento';

    protected static ?string $saasModuleLabel = 'Mobilização / Desmobilização';

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'asset_id',
        'type',
        'status',
        'vistoria_geral_lat',
        'vistoria_geral_lng',
        'vistoria_geral_captured_at',
        'notes',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'vistoria_geral_lat' => 'decimal:8',
        'vistoria_geral_lng' => 'decimal:8',
        'vistoria_geral_captured_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vistoria_geral')->singleFile();
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(EquipmentMovementItem::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(EquipmentDamage::class);
    }

    public function freightRecords(): HasMany
    {
        return $this->hasMany(FreightRecord::class);
    }
}
