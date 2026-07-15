<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EquipmentDamage extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;
    use LogsActivity;

    public const SEVERITY_LEVE = 'leve';

    public const SEVERITY_MODERADA = 'moderada';

    public const SEVERITY_GRAVE = 'grave';

    public const DAMAGE_TYPE_HIDRAULICO = 'hidraulico';

    public const DAMAGE_TYPE_ELETRICO = 'eletrico';

    public const DAMAGE_TYPE_PNEU_ESTEIRA = 'pneu_esteira';

    public const DAMAGE_TYPE_MOTOR = 'motor';

    public const DAMAGE_TYPE_ESTRUTURAL = 'estrutural';

    public const DAMAGE_TYPE_OUTRO = 'outro';

    public const STATUS_AGUARDANDO_ASSINATURA_CLIENTE = 'aguardando_assinatura_cliente';

    public const STATUS_AGUARDANDO_SUPERVISOR = 'aguardando_supervisor';

    public const STATUS_AGUARDANDO_COMERCIAL = 'aguardando_comercial';

    public const STATUS_EM_COBRANCA = 'em_cobranca';

    public const STATUS_RESOLVIDO = 'resolvido';

    public const STATUS_CANCELADO = 'cancelado';

    public const ROLE_SUPERVISOR_MANUTENCAO = 'Supervisor de Manutenção';

    public const ROLE_COMERCIAL = 'Comercial';

    public const ROLE_GERENTE_MANUTENCAO = 'Gerente de Manutenção';

    // Vive aqui (nao em MaintenanceOrderPendencia) pra ficar junto das
    // outras duas roles de manutencao ja existentes -- evita espalhar a
    // mesma lista de papeis em mais de um lugar.
    public const ROLE_ANALISTA_MANUTENCAO = 'Analista de Manutenção';

    protected static ?string $saasFeatureKey = 'tabela_equipment_damages';

    protected static ?string $saasPermissionSlug = 'avaria_equipamento';

    protected static ?string $saasModuleLabel = 'Avarias de Equipamento';

    protected $fillable = [
        'tenant_id',
        'equipment_movement_id',
        'equipment_movement_item_id',
        'maintenance_order_id',
        'asset_id',
        'reported_by_user_id',
        'severity',
        'damage_type',
        'description',
        'requires_replacement',
        'replacement_asset_id',
        'status',
        'client_signature',
        'client_acknowledged_at',
        'supervisor_reviewed_by',
        'supervisor_reviewed_at',
        'commercial_reviewed_by',
        'commercial_reviewed_at',
        'estimated_cost',
        'supervisor_notes',
    ];

    protected $casts = [
        'requires_replacement' => 'boolean',
        'client_acknowledged_at' => 'datetime',
        'supervisor_reviewed_at' => 'datetime',
        'commercial_reviewed_at' => 'datetime',
        'estimated_cost' => 'decimal:2',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    /**
     * @return array<string, string>
     */
    public static function damageTypeLabels(): array
    {
        return [
            self::DAMAGE_TYPE_HIDRAULICO => 'Hidráulico',
            self::DAMAGE_TYPE_ELETRICO => 'Elétrico',
            self::DAMAGE_TYPE_PNEU_ESTEIRA => 'Pneu/Esteira',
            self::DAMAGE_TYPE_MOTOR => 'Motor',
            self::DAMAGE_TYPE_ESTRUTURAL => 'Estrutural',
            self::DAMAGE_TYPE_OUTRO => 'Outro',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function activities()
    {
        return $this->activitiesAsSubject();
    }

    public function equipmentMovement(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovement::class);
    }

    public function equipmentMovementItem(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovementItem::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function replacementAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'replacement_asset_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function supervisorReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_reviewed_by');
    }

    public function commercialReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_reviewed_by');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(EquipmentDamageFollowUp::class);
    }

    public function equipmentReplacement(): HasOne
    {
        return $this->hasOne(EquipmentReplacement::class);
    }
}
