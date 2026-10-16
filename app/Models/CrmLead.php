<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Services\CrmFunilPadrao;
use App\Support\CrmCamposCustom;
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
        'etapa_id',
        'custom',
        'lost_reason',
        'lost_reason_category',
        'fechado_em',
        'valor_fechado',
        'vendedor_fechamento_id',
        'won_notes',
        'assigned_user_id',
        'estimated_value',
        'previsao_fechamento',
        'client_id',
        'crm_conta_id',
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
        // Origem do anúncio (preenchida pelo canal de leads do site)
        'gclid',
        'gbraid',
        'wbraid',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'landing_url',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'estimated_revenue' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'last_contacted_at' => 'datetime',
        'next_followup_date' => 'date',
        'previsao_fechamento' => 'date',
        'fechado_em' => 'datetime',
        'valor_fechado' => 'decimal:2',
        'custom' => 'array',
    ];

    /**
     * Etapa do funil configurável (crm_etapas). `stage` continua gravado como
     * espelho legado: o slug da etapa aberta, ou 'convertido'/'perdido' para
     * etapas finais, para que leitores e dados antigos sigam coerentes.
     */
    protected static function booted(): void
    {
        static::creating(fn (CrmLead $lead) => $lead->resolverEtapa(true));
        static::updating(fn (CrmLead $lead) => $lead->resolverEtapa(false));
        // Ganhou: o processo termina no CRM, com a conta criada (ou reaproveitada pelo CNPJ/CPF).
        static::saved(function (CrmLead $lead) {
            if (! $lead->crm_conta_id && $lead->etapa_id && $lead->isWon()) {
                $lead->garantirConta();
            }
        });
        static::saving(function (CrmLead $lead) {
            if ($lead->isDirty('custom') || ! $lead->exists) {
                $lead->custom = CrmCamposCustom::normalizar('lead', $lead->custom, $lead->tenant_id);
            }
        });
    }

    private function resolverEtapa(bool $novo): void
    {
        if (! $this->tenant_id) {
            return;
        }

        if ($this->etapa_id && ($novo || $this->isDirty('etapa_id'))) {
            $etapa = CrmEtapa::withoutGlobalScopes()->find($this->etapa_id);

            if (! $etapa || (string) $etapa->tenant_id !== (string) $this->tenant_id) {
                throw new \InvalidArgumentException('Etapa não pertence ao tenant do lead.');
            }
        } elseif ($novo || $this->isDirty('stage')) {
            $etapa = $this->etapaPorStage($this->stage ?: self::STAGE_NOVO);
        } else {
            return;
        }

        $this->etapa_id = $etapa->id;
        $this->setRelation('etapa', $etapa);
        $this->registrarFechamento($etapa, $novo);
        $this->stage = match ($etapa->tipo) {
            CrmEtapa::TIPO_GANHO => self::STAGE_CONVERTIDO,
            CrmEtapa::TIPO_PERDIDO => self::STAGE_PERDIDO,
            default => $etapa->slug,
        };
    }

    /**
     * Fechamento datado (base das metas): ao entrar numa etapa final guarda quando, o valor (só ganho) e o
     * responsável da hora; ao voltar para uma etapa aberta limpa tudo. Editar o valor depois não muda o fechado.
     */
    private function registrarFechamento(CrmEtapa $etapa, bool $novo): void
    {
        if ($etapa->isAberta()) {
            $this->fechado_em = null;
            $this->valor_fechado = null;
            $this->vendedor_fechamento_id = null;

            return;
        }

        if ($novo || $this->isDirty('etapa_id') || ! $this->fechado_em) {
            $this->fechado_em = now();
            $this->valor_fechado = $etapa->isGanho() ? $this->estimated_value : null;
            $this->vendedor_fechamento_id = $this->assigned_user_id;
        }
    }

    /** Resolve um valor legado de stage na etapa do funil padrão do tenant ('prospecção' e outros desconhecidos caem na 1ª etapa aberta). */
    private function etapaPorStage(string $stage): CrmEtapa
    {
        $etapas = fn () => CrmEtapa::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->whereIn('crm_funil_id', CrmPipeline::withoutGlobalScopes()->where('tenant_id', $this->tenant_id)->where('padrao', true)->select('id'))
            ->orderBy('ordem')->get();

        $lista = $etapas();
        if ($lista->isEmpty()) {
            CrmFunilPadrao::garantir((string) $this->tenant_id);
            $lista = $etapas();
        }

        return match ($stage) {
            self::STAGE_CONVERTIDO => $lista->firstWhere('tipo', CrmEtapa::TIPO_GANHO),
            self::STAGE_PERDIDO => $lista->firstWhere('tipo', CrmEtapa::TIPO_PERDIDO),
            default => $lista->firstWhere('slug', $stage),
        } ?? $lista->firstWhere('tipo', CrmEtapa::TIPO_ABERTA) ?? $lista->first();
    }

    /** Valores dos campos personalizados do lead que têm campo de cliente com a mesma chave e o mesmo tipo. */
    private function customParaCliente(): array
    {
        $doCliente = CrmCamposCustom::campos('cliente', false, (string) $this->tenant_id)->keyBy('chave');
        $doLead = CrmCamposCustom::campos('lead', false, (string) $this->tenant_id)->keyBy('chave');

        return collect((array) $this->custom)
            ->filter(fn ($valor, $chave) => $doCliente->has($chave) && $doLead->has($chave) && $doCliente[$chave]->tipo === $doLead[$chave]->tipo)
            ->all();
    }

    public function conta(): BelongsTo
    {
        return $this->belongsTo(CrmConta::class, 'crm_conta_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CrmLeadItem::class, 'crm_lead_id');
    }

    /**
     * Conta (cliente do CRM) deste lead: a que já está ligada, a do tenant com o mesmo CNPJ/CPF, ou uma nova
     * criada a partir dos dados do lead (com os contatos). Idempotente. É o fim do processo no CRM.
     */
    public function garantirConta(): CrmConta
    {
        if ($this->crm_conta_id && $this->conta) {
            return $this->conta;
        }

        $conta = CrmConta::porDocumento((string) $this->tenant_id, $this->document);

        if (! $conta) {
            $conta = CrmConta::create([
                'tenant_id' => $this->tenant_id,
                'nome' => $this->company_name ?: $this->name,
                'documento' => $this->document,
                'email' => $this->email,
                'telefone' => $this->phone,
                'whatsapp' => $this->whatsapp,
                'segmento' => self::segmentLabels()[$this->segment] ?? $this->segment,
                'cep' => $this->cep,
                'endereco' => $this->address,
                'cidade' => $this->city,
                'uf' => $this->uf,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'responsavel_user_id' => $this->assigned_user_id,
                'origem' => $this->source,
                'custom' => $this->customParaConta(),
            ]);

            foreach ($this->contacts()->get() as $contato) {
                CrmContaContato::create([
                    'tenant_id' => $this->tenant_id, 'crm_conta_id' => $conta->id, 'name' => $contato->name,
                    'role_title' => $contato->role_title, 'phone' => $contato->phone, 'email' => $contato->email,
                    'is_primary' => $contato->is_primary,
                ]);
            }
        }

        $this->forceFill(['crm_conta_id' => $conta->id])->saveQuietly();
        $this->setRelation('conta', $conta);

        return $conta;
    }

    /** Opcional: envia a conta deste lead para o cadastro de Clientes do ERP (só existe para quem tem o módulo). */
    public function enviarParaErp(): Client
    {
        $client = $this->garantirConta()->enviarParaErp();
        $this->forceFill(['client_id' => $client->id])->saveQuietly();

        return $client;
    }

    /** Valores `custom` do lead que têm campo de conta com a mesma chave e o mesmo tipo. */
    private function customParaConta(): array
    {
        $daConta = CrmCamposCustom::campos('conta', false, (string) $this->tenant_id)->keyBy('chave');
        $doLead = CrmCamposCustom::campos('lead', false, (string) $this->tenant_id)->keyBy('chave');

        return collect((array) $this->custom)
            ->filter(fn ($v, $chave) => $daConta->has($chave) && $doLead->has($chave) && $daConta[$chave]->tipo === $doLead[$chave]->tipo)
            ->all();
    }

    public function etapa(): BelongsTo
    {
        return $this->belongsTo(CrmEtapa::class, 'etapa_id');
    }

    /** Nome da etapa para exibição (cai no rótulo legado se o lead ainda não tiver etapa). */
    public function etapaNome(): string
    {
        return $this->etapa?->nome ?? (self::stageLabels()[$this->stage] ?? (string) $this->stage);
    }

    public function isWon(): bool
    {
        return $this->etapa ? $this->etapa->isGanho() : $this->stage === self::STAGE_CONVERTIDO;
    }

    public function isLost(): bool
    {
        return $this->etapa ? $this->etapa->isPerdido() : $this->stage === self::STAGE_PERDIDO;
    }

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
        return ! $this->isWon() && ! $this->isLost();
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

        $client->update(['custom' => $this->customParaCliente()]);

        foreach ($this->contacts()->get() as $contato) {
            ClientContact::create([
                'tenant_id' => $this->tenant_id,
                'client_id' => $client->id,
                'name' => $contato->name,
                'role_title' => $contato->role_title,
                'phone' => $contato->phone,
                'email' => $contato->email,
                'is_primary' => $contato->is_primary,
            ]);
        }

        $this->update(['client_id' => $client->id]);

        return $client;
    }
}
