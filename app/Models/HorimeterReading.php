<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorimeterReading extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_MAINTENANCE_ORDER = 'maintenance_order';

    public const SOURCE_CHECKLIST = 'checklist';

    protected static ?string $saasFeatureKey = 'tabela_horimeter_readings';

    protected static ?string $saasPermissionSlug = 'apontamento_horimetro';

    protected static ?string $saasModuleLabel = 'Apontamentos de Horímetro';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'reading',
        'recorded_at',
        'recorded_by',
        'source',
        'reset_confirmed',
        'notes',
        'photo_path',
    ];

    protected $casts = [
        'reading' => 'decimal:2',
        'recorded_at' => 'datetime',
        'reset_confirmed' => 'boolean',
    ];

    /**
     * @return array<string, string>
     */
    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_MANUAL => 'Apontamento Manual',
            self::SOURCE_MAINTENANCE_ORDER => 'Ordem de Serviço',
            self::SOURCE_CHECKLIST => 'Checklist',
        ];
    }

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
