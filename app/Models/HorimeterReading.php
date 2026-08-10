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

    public const SOURCE_MOBILE_SYNC = 'mobile_sync';

    /**
     * Registrado por funcionário do cliente locatário via link público
     * (sem login), não pelo técnico/usuário do tenant -- ver
     * HourMeterPublicController. recorded_by fica null (não há User),
     * recorded_by_name guarda o nome digitado na hora.
     */
    public const SOURCE_PUBLIC_CLIENT = 'public_client';

    protected static ?string $saasFeatureKey = 'tabela_horimeter_readings';

    protected static ?string $saasPermissionSlug = 'apontamento_horimetro';

    protected static ?string $saasModuleLabel = 'Apontamentos de Horímetro';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'equipment_movement_item_id',
        'reading',
        'recorded_at',
        'recorded_by',
        'recorded_by_name',
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
            self::SOURCE_MOBILE_SYNC => 'App Mobile (Campo)',
            self::SOURCE_PUBLIC_CLIENT => 'Cliente Locatário (Link Público)',
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

    public function equipmentMovementItem(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovementItem::class);
    }

    public function scopeLatestForAsset(Builder $query, string $assetId): Builder
    {
        return $query->where('asset_id', $assetId)->orderByDesc('recorded_at');
    }

    /**
     * "Externo/Campo" = veio do app mobile do técnico fora do escritório;
     * qualquer outra origem (manual desktop, O.S., checklist) é "Interno".
     * Usado pela tela de monitoramento geral (HorimeterReadingResource).
     */
    public function isFieldSource(): bool
    {
        return $this->source === self::SOURCE_MOBILE_SYNC;
    }

    public function isPublicClientSource(): bool
    {
        return $this->source === self::SOURCE_PUBLIC_CLIENT;
    }

    public function originLabel(): string
    {
        return match (true) {
            $this->isPublicClientSource() => 'Externo (Cliente Locatário)',
            $this->isFieldSource() => 'Externo (Campo)',
            default => 'Interno',
        };
    }

    /**
     * Nome de quem registrou, independente da origem -- User.name quando
     * veio de dentro do sistema, recorded_by_name (texto livre) quando
     * veio do link público sem login.
     */
    public function recordedByLabel(): string
    {
        return $this->recordedBy?->name ?? $this->recorded_by_name ?? '—';
    }
}
