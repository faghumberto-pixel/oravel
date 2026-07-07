<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class CrmLead extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use LogsActivity;

    public const STAGE_NOVO = 'novo';

    public const STAGE_CONTATO_INICIADO = 'contato_iniciado';

    public const STAGE_QUALIFICADO = 'qualificado';

    public const STAGE_CONVERTIDO = 'convertido';

    public const STAGE_PERDIDO = 'perdido';

    protected static ?string $saasFeatureKey = 'tabela_crm_leads';

    protected static ?string $saasPermissionSlug = 'crm_lead';

    protected static ?string $saasModuleLabel = 'CRM - Leads';

    protected $fillable = [
        'tenant_id',
        'name',
        'company_name',
        'phone',
        'whatsapp',
        'email',
        'document',
        'source',
        'stage',
        'lost_reason',
        'assigned_user_id',
        'estimated_value',
        'client_id',
        'solicitacao_locacao_id',
        'address',
        'city',
        'uf',
        'cep',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_contacted_at' => 'datetime',
        'next_followup_date' => 'date',
    ];

    public static function stageLabels(): array
    {
        return [
            self::STAGE_NOVO => 'Novo',
            self::STAGE_CONTATO_INICIADO => 'Contato Iniciado',
            self::STAGE_QUALIFICADO => 'Qualificado',
            self::STAGE_CONVERTIDO => 'Convertido',
            self::STAGE_PERDIDO => 'Perdido',
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

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function solicitacaoLocacao(): BelongsTo
    {
        return $this->belongsTo(SolicitacaoLocacao::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(CrmLeadInteraction::class)->latest('contact_date');
    }

    /**
     * Recalcula last_contacted_at/next_followup_date a partir da interação
     * mais recente. Mantido como colunas cacheadas (em vez de uma relação
     * hasOne->latestOfMany()) porque o Postgres não tem agregação MAX()
     * para uuid, e todo PK deste app é uuid -- ver CrmLeadInteractionObserver.
     */
    public function refreshFollowUpCache(): void
    {
        $latest = $this->interactions()->first();

        $this->forceFill([
            'last_contacted_at' => $latest?->contact_date,
            'next_followup_date' => $latest?->next_followup_date,
        ])->saveQuietly();
    }

    public function isOpen(): bool
    {
        return ! in_array($this->stage, [self::STAGE_CONVERTIDO, self::STAGE_PERDIDO], true);
    }
}
