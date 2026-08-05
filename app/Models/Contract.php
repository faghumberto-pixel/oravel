<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Contract extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;
    use HasSaaSMetadata;
    use LogsActivity;

    protected static ?string $saasFeatureKey = 'tabela_contracts';

    protected static ?string $saasPermissionSlug = 'contrato';

    protected static ?string $saasModuleLabel = 'Contratos';

    public const LOCAL_TIPO_SEDE_EMPRESA = 'sede_empresa';

    public const LOCAL_TIPO_ENDERECO_CLIENTE = 'endereco_cliente';

    public const LOCAL_TIPO_OUTRO = 'outro';

    public const CONDICAO_NORMAL = 'normal';

    public const CONDICAO_EXPOSTO_TEMPO = 'exposto_tempo';

    public const CONDICAO_AREA_CONSTRUCAO = 'area_construcao';

    public const CONDICAO_AMBIENTE_AGRESSIVO = 'ambiente_agressivo';

    public const CONDICAO_LITORANEO = 'litoraneo';

    public const CONDICAO_OUTRO = 'outro';

    public const BILLING_MENSAL_FIXO = 'mensal_fixo';

    public const BILLING_POR_HORA = 'por_hora';

    public const BILLING_FRANQUIA_EXCEDENTE = 'franquia_excedente';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'asset_id',
        'contract_number',
        'start_date',
        'end_date',
        'price',
        'billing_type',
        'multa_rescisoria',
        'payment_method',
        'usage_purpose',
        'required_nrs',
        'prohibit_sublease',
        'maintenance_clause',
        'frete_incluso',
        'responsavel_manutencao',
        'initial_horimeter',
        'initial_odometer',
        'local_tipo',
        'internal_unit_id',
        'cep_obra',
        'endereco_obra',
        'cidade_obra',
        'uf_obra',
        'latitude_obra',
        'longitude_obra',
        'condicao_ambiente',
        'legal_forum',
        'insurance_details',
        'is_active',
        'status',
        'observations',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price' => 'decimal:2',
        'initial_horimeter' => 'decimal:2',
        'initial_odometer' => 'decimal:2',
        'prohibit_sublease' => 'boolean',
        'frete_incluso' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public static function localTipoOptions(): array
    {
        return [
            self::LOCAL_TIPO_SEDE_EMPRESA => 'Sede da Empresa (unidade própria)',
            self::LOCAL_TIPO_ENDERECO_CLIENTE => 'Endereço do Cliente (já cadastrado)',
            self::LOCAL_TIPO_OUTRO => 'Outro Local (obra/endereço específico)',
        ];
    }

    public static function condicaoOptions(): array
    {
        return [
            self::CONDICAO_NORMAL => 'Normal',
            self::CONDICAO_EXPOSTO_TEMPO => 'Exposto ao tempo',
            self::CONDICAO_AREA_CONSTRUCAO => 'Área de construção',
            self::CONDICAO_AMBIENTE_AGRESSIVO => 'Ambiente agressivo (poeira, produtos químicos)',
            self::CONDICAO_LITORANEO => 'Litorâneo (maresia)',
            self::CONDICAO_OUTRO => 'Outro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function billingTypeOptions(): array
    {
        return [
            self::BILLING_MENSAL_FIXO => 'Mensal Fixo',
            self::BILLING_POR_HORA => 'Por Hora',
            self::BILLING_FRANQUIA_EXCEDENTE => 'Franquia de Horas + Excedente',
        ];
    }

    /**
     * RentalHourFranchise (app/Domain/Fleet) só é relevante quando o
     * contrato está nessa modalidade -- resto do sistema (formulário,
     * cálculo de excedente) deve checar isto antes de exigir/ler franquia.
     */
    public function usesHourFranchise(): bool
    {
        return $this->billing_type === self::BILLING_FRANQUIA_EXCEDENTE;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function equipmentReplacements(): HasMany
    {
        return $this->hasMany(EquipmentReplacement::class);
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * Fonte unica de verdade de "onde o equipamento deste contrato esta
     * instalado" -- lida em ContractObserver (sync no Asset), na O.S., no
     * Dossie Operacional e no cadastro do Ativo, em vez de cada um repetir
     * a mesma logica condicional por local_tipo.
     *
     * @return array{cep: ?string, address: ?string, city: ?string, uf: ?string, latitude: ?float, longitude: ?float, label: string}|null
     */
    public function resolvedLocation(): ?array
    {
        return match ($this->local_tipo) {
            self::LOCAL_TIPO_SEDE_EMPRESA => $this->internalUnit ? [
                'cep' => $this->internalUnit->cep,
                'address' => $this->internalUnit->address,
                'city' => $this->internalUnit->city,
                'uf' => $this->internalUnit->state,
                'latitude' => $this->internalUnit->latitude,
                'longitude' => $this->internalUnit->longitude,
                'label' => $this->internalUnit->name,
            ] : null,
            self::LOCAL_TIPO_ENDERECO_CLIENTE => $this->client ? [
                'cep' => $this->client->cep,
                'address' => $this->client->address,
                'city' => $this->client->city,
                'uf' => $this->client->uf,
                'latitude' => null,
                'longitude' => null,
                'label' => $this->client->name,
            ] : null,
            self::LOCAL_TIPO_OUTRO => filled($this->cep_obra) ? [
                'cep' => $this->cep_obra,
                'address' => $this->endereco_obra,
                'city' => $this->cidade_obra,
                'uf' => $this->uf_obra,
                'latitude' => $this->latitude_obra,
                'longitude' => $this->longitude_obra,
                'label' => 'Outro local',
            ] : null,
            default => null,
        };
    }
}
