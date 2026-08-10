<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatteryCycleReading extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_MAINTENANCE_ORDER = 'maintenance_order';

    protected static ?string $saasFeatureKey = 'tabela_battery_cycle_readings';

    protected static ?string $saasPermissionSlug = 'apontamento_ciclo_bateria';

    protected static ?string $saasModuleLabel = 'Apontamentos de Ciclo de Bateria';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'cycles',
        'recorded_at',
        'recorded_by',
        'source',
        'notes',
    ];

    protected $casts = [
        'cycles' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeLatestForAsset(Builder $query, string $assetId): Builder
    {
        return $query->where('asset_id', $assetId)->orderByDesc('recorded_at');
    }
}
