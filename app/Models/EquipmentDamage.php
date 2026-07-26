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
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    public const CAUSE_DESGASTE_NATURAL = 'desgaste_natural';

    public const CAUSE_MAU_USO = 'mau_uso';

    public const CAUSE_DANO_CLIENTE = 'dano_cliente';

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
        'cause',
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

    /**
     * @return array<string, string>
     */
    public static function causeLabels(): array
    {
        return [
            self::CAUSE_DESGASTE_NATURAL => 'Desgaste Natural',
            self::CAUSE_MAU_USO => 'Mau Uso',
            self::CAUSE_DANO_CLIENTE => 'Dano do Cliente',
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

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AIAnalysis::class);
    }

    /**
     * Nao usa latestOfMany(): ate' passando a coluna de ordenacao
     * ('created_at'), o Eloquent ainda monta um desempate por MAX(id) --
     * id aqui e' uuid, e o Postgres nao tem MAX() pra uuid. Accessor
     * simples evita o aggregate subquery inteiro.
     */
    public function getLatestAiAnalysisAttribute(): ?AIAnalysis
    {
        return $this->aiAnalyses()->latest('created_at')->first();
    }

    /**
     * Orçamento(s) indenizatório(s) gerados a partir desta avaria (POP 5/6
     * da auditoria: mau_uso/dano_cliente alimenta o fluxo de cobrança via
     * App\Models\Quote em vez de só o campo estimated_cost de texto livre).
     */
    public function quotes(): MorphMany
    {
        return $this->morphMany(Quote::class, 'quotable');
    }

    /**
     * Causas que responsabilizam o cliente pelo dano -- as únicas que
     * fazem sentido virar cobrança/orçamento indenizatório. Desgaste
     * natural nunca é cobrado do cliente.
     */
    public function isBillableToClient(): bool
    {
        return in_array($this->cause, [self::CAUSE_MAU_USO, self::CAUSE_DANO_CLIENTE], true);
    }
}
