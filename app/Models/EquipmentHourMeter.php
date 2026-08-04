<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentHourMeter extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    protected static ?string $saasFeatureKey = 'tabela_equipment_hour_meters';

    protected static ?string $saasPermissionSlug = 'apontamento_horimetro_mobile';

    protected static ?string $saasModuleLabel = 'Apontamentos de Horímetro (Mobile Offline)';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'recorded_by',
        'horimeter_reading_id',
        'client_uuid',
        'reading',
        'photo_path',
        'recorded_at',
        'latitude',
        'longitude',
        'reset_confirmed',
        'sync_status',
        'synced_at',
        'sync_error',
    ];

    protected $casts = [
        'reading' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'recorded_at' => 'datetime',
        'synced_at' => 'datetime',
        'reset_confirmed' => 'boolean',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function horimeterReading(): BelongsTo
    {
        return $this->belongsTo(HorimeterReading::class);
    }
}
