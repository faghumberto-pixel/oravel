<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
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
    use HasUuids, InteractsWithMedia, SoftDeletes;

    // --- CONSTANTES DE SERVIÇO ---
    public const TYPE_CHECKOUT = 'Check-out';

    public const TYPE_CHECKIN = 'Check-in';

    public const TYPE_CORRECTIVE = 'Corretiva';

    public const TYPE_PREVENTIVE = 'Preventiva';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'os_number', 'asset_id', 'technician_id', 'client_id', 'branch_id', 'service_type',
        'maintenance_type', 'reported_problem_id', 'description', 'technical_notes',
        'client_signature', 'technician_signature', 'signature_path', 'status', 'internal_status', 'commercial_status',
        'tenant_id', 'started_at', 'finished_at', 'rescheduled_to', 'total_time_seconds',
        'last_timer_start', 'reschedule_reason', 'criticality_level_id', 'is_rework',
        'parent_os_id', 'labor_cost', 'material_cost', 'logistics_cost', 'total_order_cost',
        'horimetro_entry', 'fuel_level',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'last_timer_start' => 'datetime',
        'is_rework' => 'boolean',
        'total_time_seconds' => 'integer',
        'horimetro_entry' => 'decimal:2',
    ];

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

    public function logStatusChange(string $newStatus, ?string $oldStatus = null, ?string $observation = null): void
    {
        $this->statusHistories()->create([
            'new_status' => $newStatus,
            'old_status' => $oldStatus,
            'observation' => $observation,
            'created_at' => now(),
            'tenant_id' => $this->tenant_id,
        ]);
    }

    protected static function booted()
    {
        static::creating(function (MaintenanceOrder $os) {
            if (empty($os->os_number)) {
                $prefix = 'OS-'.now()->format('Ym').'-';
                $latestOrder = static::withoutGlobalScopes()
                    ->where('os_number', 'like', $prefix.'%')
                    ->orderBy('os_number', 'desc')
                    ->first();

                $nextSequence = $latestOrder ? (int) substr($latestOrder->os_number, -5) + 1 : 10000;
                $os->os_number = $prefix.str_pad($nextSequence, 5, '0', STR_PAD_LEFT);
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
                    $os->total_time_seconds += now()->diffInSeconds($os->last_timer_start);
                    $os->last_timer_start = null;
                }

                if (in_array($os->status, ['Concluída', 'Completado'])) {
                    $os->finished_at = now();
                }
            }
        });
    }
}
