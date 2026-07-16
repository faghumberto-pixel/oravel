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

    public const SOURCE_INDICACAO = 'indicacao';

    public const SOURCE_SITE = 'site';

    public const SOURCE_CONTATO_FRIO = 'contato_frio';

    public const SOURCE_EVENTO = 'evento';

    public const SOURCE_OUTRO = 'outro';

    public const COMPANY_SIZE_MICRO = 'microempresa';

    public const COMPANY_SIZE_PEQUENA = 'pequena';

    public const COMPANY_SIZE_MEDIA = 'media';

    public const COMPANY_SIZE_GRANDE = 'grande';

    public const LOST_REASON_PRECO = 'preco';

    public const LOST_REASON_CONCORRENCIA = 'concorrencia';

    public const LOST_REASON_SEM_NECESSIDADE = 'sem_necessidade';

    public const LOST_REASON_SEM_ORCAMENTO = 'sem_orcamento';

    public const LOST_REASON_PRODUTO_NAO_APLICA = 'produto_nao_aplica';

    public const LOST_REASON_DESISTENCIA = 'desistencia';

    public const LOST_REASON_OUTRO = 'outro';

    public const SEGMENT_INDUSTRIA = 'industria';

    public const SEGMENT_CONSTRUCAO = 'construcao';

    public const SEGMENT_FERRARIA = 'ferraria';

    public const SEGMENT_AGROPECUARIA = 'agropecuaria';

    public const SEGMENT_COMERCIO = 'comercio';

    public const SEGMENT_SERVICOS = 'servicos';

    public const SEGMENT_OUTROS = 'outros';

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
        'lost_reason_category',
        'won_notes',
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
        'segment',
        'company_size',
        'estimated_revenue',
        'equipment_interest',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'estimated_revenue' => 'decimal:2',
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

    public static function sourceLabels(): array
    {
        return [
            self::SOURCE_INDICACAO => 'Indicação',
            self::SOURCE_SITE => 'Site',
            self::SOURCE_CONTATO_FRIO => 'Contato Frio',
            self::SOURCE_EVENTO => 'Evento',
            self::SOURCE_OUTRO => 'Outro',
        ];
    }

    public static function segmentLabels(): array
    {
        return [
            self::SEGMENT_INDUSTRIA => 'Indústria',
            self::SEGMENT_CONSTRUCAO => 'Construção',
            self::SEGMENT_FERRARIA => 'Ferraria',
            self::SEGMENT_AGROPECUARIA => 'Agropecuária',
            self::SEGMENT_COMERCIO => 'Comércio',
            self::SEGMENT_SERVICOS => 'Serviços',
            self::SEGMENT_OUTROS => 'Outros',
        ];
    }

    public static function companySizeLabels(): array
    {
        return [
            self::COMPANY_SIZE_MICRO => 'Microempresa',
            self::COMPANY_SIZE_PEQUENA => 'Pequena',
            self::COMPANY_SIZE_MEDIA => 'Média',
            self::COMPANY_SIZE_GRANDE => 'Grande',
        ];
    }

    public static function lostReasonCategoryLabels(): array
    {
        return [
            self::LOST_REASON_PRECO => 'Preço',
            self::LOST_REASON_CONCORRENCIA => 'Concorrência',
            self::LOST_REASON_SEM_NECESSIDADE => 'Sem Necessidade no Momento',
            self::LOST_REASON_SEM_ORCAMENTO => 'Sem Orçamento',
            self::LOST_REASON_PRODUTO_NAO_APLICA => 'Produto Não Se Aplica',
            self::LOST_REASON_DESISTENCIA => 'Desistência do Cliente',
            self::LOST_REASON_OUTRO => 'Outro',
        ];
    }

    /**
     * Categorias que exigem detalhe em lost_reason (o texto livre ja
     * existente): "concorrencia" pra dizer qual concorrente, "outro" pra
     * explicar. As demais sao autoexplicativas pelo rotulo da categoria.
     */
    public static function lostReasonCategoriesRequiringDetail(): array
    {
        return [self::LOST_REASON_CONCORRENCIA, self::LOST_REASON_OUTRO];
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

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmLeadContact::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CrmLeadAssignment::class)->latest('created_at');
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

    /**
     * Cria (ou retorna, se ja convertido antes) o Cliente formal a partir
     * dos dados do Lead -- endereco/geo ja vem geocodificado, so
     * reaproveita. Nao cria Contract (decisao do usuario: um Lead pode
     * virar Cliente sem nunca ter alugado nada ainda). Contatos multiplos
     * (CrmLeadContact) ficam registrados so' no Lead -- Client nao tem
     * uma tabela de contatos, so' um contact_name solto, que recebe o
     * contato principal (is_primary) ou o primeiro cadastrado.
     */
    public function convertToClient(): Client
    {
        if ($this->client_id) {
            return $this->client;
        }

        $primaryContact = $this->contacts()->where('is_primary', true)->first()
            ?? $this->contacts()->first();

        $client = Client::create([
            'tenant_id' => $this->tenant_id,
            'name' => $this->company_name ?: $this->name,
            'contact_name' => $primaryContact?->name ?? $this->name,
            'cpf_cnpj' => $this->document,
            'document' => $this->document,
            'activity_type' => self::segmentLabels()[$this->segment] ?? null,
            'phone' => $primaryContact?->phone ?? $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'address_complement' => null,
            'neighborhood' => null,
            'city' => $this->city,
            'uf' => $this->uf,
            'state' => $this->uf,
            'cep' => $this->cep,
            'zip_code' => $this->cep,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        $this->update(['client_id' => $client->id]);

        return $client;
    }
}
