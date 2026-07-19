<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MaintenanceOrder extends Model implements HasMedia
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_maintenance_orders';

    protected static ?string $saasPermissionSlug = 'ordem_servico';

    protected static ?string $saasModuleLabel = 'Ordens de Servico';

    use BelongsToTenant;
    use HasFactory, HasUuids, InteractsWithMedia, SoftDeletes;

    // --- CONSTANTES DE SERVIÇO ---
    public const TYPE_CHECKOUT = 'Check-out';

    public const TYPE_CHECKIN = 'Check-in';

    public const TYPE_CORRECTIVE = 'Corretiva';

    public const TYPE_PREVENTIVE = 'Preventiva';

    public const TYPE_AVARIA = 'Avaria';

    public const TYPE_TROCA = 'Troca';

    public const TYPE_EMERGENCIA = 'Emergência';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'os_number', 'asset_id', 'technician_id', 'client_id', 'branch_id', 'service_type',
        'maintenance_type', 'reported_problem_id', 'description', 'technical_notes',
        'client_signature', 'technician_signature', 'signature_path', 'status', 'internal_status', 'commercial_status',
        'tenant_id', 'started_at', 'finished_at', 'rescheduled_to', 'total_time_seconds',
        'last_timer_start', 'reschedule_reason', 'criticality_level_id', 'is_rework',
        'parent_os_id', 'labor_cost', 'material_cost', 'logistics_cost', 'total_order_cost',
        'horimetro_entry', 'fuel_level', 'scheduled_at',
        'is_prazo_fatal', 'prazo_fatal_at', 'sla_target_minutes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_timer_start' => 'datetime',
        'scheduled_at' => 'datetime',
        'is_rework' => 'boolean',
        'total_time_seconds' => 'integer',
        'horimetro_entry' => 'decimal:2',
        'is_prazo_fatal' => 'boolean',
        'prazo_fatal_at' => 'datetime',
        'sla_target_minutes' => 'integer',
    ];

    /**
     * Cor do badge de SLA -- mesmo padrao de EquipmentReplacementResource::slaColor(),
     * so' que em minutos (nao horas) e a partir de created_at (ticket aberto)
     * em vez de uma janela fixa por urgencia.
     */
    public function slaColor(): ?string
    {
        if (! $this->sla_target_minutes) {
            return null;
        }

        if ($this->started_at) {
            return 'gray';
        }

        $minutosDecorridos = $this->created_at->diffInMinutes(now());

        return match (true) {
            $minutosDecorridos >= $this->sla_target_minutes => 'danger',
            $minutosDecorridos >= $this->sla_target_minutes * 0.7 => 'warning',
            default => 'success',
        };
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }

    // --- RELACIONAMENTOS ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function parentOrder(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_os_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function criticalityLevel(): BelongsTo
    {
        return $this->belongsTo(CriticalityLevel::class, 'criticality_level_id');
    }

    public function reportedProblem(): BelongsTo
    {
        return $this->belongsTo(ReportedProblem::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(MaintenanceOrderMaterial::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(MaintenanceOrderMaterial::class, 'maintenance_order_id');
    }

    // Relação corrigida para bater com o Kanban e a Migration
    public function statusHistories(): HasMany
    {
        return $this->hasMany(MaintenanceStatusHistory::class, 'maintenance_order_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(MaintenanceOrderChecklist::class);
    }

    public function equipmentMovements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(EquipmentDamage::class);
    }

    public function equipmentReplacements(): HasMany
    {
        return $this->hasMany(EquipmentReplacement::class);
    }

    public function pendencias(): HasMany
    {
        return $this->hasMany(MaintenanceOrderPendencia::class);
    }

    public function internalCommunications(): HasMany
    {
        return $this->hasMany(InternalCommunication::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Attachment::class)->orderBy('captured_at', 'asc');
    }

    public function delegation(): HasOne
    {
        return $this->hasOne(MaintenanceOrderDelegation::class);
    }

    public function chatRoom(): HasOne
    {
        return $this->hasOne(ChatRoom::class, 'maintenance_order_id');
    }

    // --- LÓGICA DE NEGÓCIO ---

    /**
     * $extra aceita old_technician_id/new_technician_id/segment_seconds --
     * so preenchidos pela acao "Transferir" (EditMaintenanceOrder.php), pra
     * permitir calcular quanto tempo cada tecnico realmente trabalhou numa
     * OS que passou por mais de uma mao (ver User::maintenanceOrders() e
     * OrdensAtendidasRelationManager).
     */
    public function logStatusChange(string $newStatus, ?string $oldStatus = null, ?string $observation = null, ?string $userId = null, array $extra = []): void
    {
        $this->statusHistories()->create([
            'new_status' => $newStatus,
            'old_status' => $oldStatus,
            'observation' => $observation,
            'user_id' => $userId ?? auth()->id(),
            'created_at' => now(),
            ...$extra,
        ]);
    }

    protected static function booted()
    {
        static::creating(function (MaintenanceOrder $os) {
            if (empty($os->os_number)) {
                $prefix = 'OS-'.now()->format('Ym').'-';

                // Comparacao por string (ORDER BY os_number DESC) quebra quando
                // sequencias de tamanhos diferentes coexistem na mesma tabela
                // (ex: legado "0005" de 4 digitos vs "00006" de 5 digitos --
                // "0005" > "00006" lexicograficamente mesmo sendo menor). Calcula
                // o maximo de verdade em PHP a partir do sufixo numerico real.
                $maxSequence = static::withoutGlobalScopes()
                    ->where('os_number', 'like', $prefix.'%')
                    ->pluck('os_number')
                    ->map(function ($value) use ($prefix) {
                        $suffix = substr((string) $value, strlen($prefix));

                        return ctype_digit($suffix) ? (int) $suffix : 0;
                    })
                    ->max();

                $nextSequence = $maxSequence ? $maxSequence + 1 : 10000;
                $os->os_number = $prefix.str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
            }

            $isUnderContract = Contract::where('asset_id', $os->asset_id)
                ->where('status', 'Ativo')
                ->exists();

            $os->service_type = $isUnderContract ? 'Externo' : 'Interno';
        });

        static::updating(function (MaintenanceOrder $os) {
            if ($os->isDirty('status')) {
                if ($os->status === 'Em Andamento') {
                    $os->last_timer_start = now();
                    if (! $os->started_at) {
                        $os->started_at = now();
                    }
                }

                if ($os->getOriginal('status') === 'Em Andamento' && $os->last_timer_start) {
                    // Carbon 3 mudou o default de diffInSeconds() pra $absolute=false
                    // (era true no Carbon 2) e passou a retornar float -- sem o `true`
                    // explicito aqui, isso virava um numero negativo com casas decimais
                    // (ex: -602.69) sendo gravado direto na coluna integer.
                    $os->total_time_seconds += (int) now()->diffInSeconds($os->last_timer_start, true);
                    $os->last_timer_start = null;
                }

                if (in_array($os->status, ['Concluída', 'Completado'])) {
                    $os->finished_at = now();
                }
            }
        });

        /**
         * horimetro_entry (preenchido em toda OS) ate 2026-07-17 nunca
         * atualizava Asset.horimetro_atual -- so' PreventiveMaintenanceExecution::saved()
         * fazia isso, deixando o gatilho de preventiva vencida (MaintenancePlan::dueStatusForAsset())
         * olhando pra um horimetro desatualizado em qualquer fluxo que nao
         * passasse pela execucao formal de preventiva. Mesmo criterio (so
         * atualiza se o valor informado e' maior, nunca regride).
         */
        static::saved(function (MaintenanceOrder $os) {
            if (! $os->horimetro_entry || ! $os->asset_id) {
                return;
            }

            $asset = $os->asset;

            if ($asset && (float) $os->horimetro_entry >= (float) $asset->horimetro_atual) {
                $asset->updateQuietly([
                    'horimetro_atual' => $os->horimetro_entry,
                    'last_horimetro' => $os->horimetro_entry,
                ]);
            }
        });
    }
}
