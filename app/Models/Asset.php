<?php

namespace App\Models;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Domain\Fleet\Models\GeneratorSpecification;
use App\Domain\Fleet\Models\PlatformSpecification;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Asset extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
    use InteractsWithMedia;
    use LogsActivity;

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_DISPONIVEL = 'disponivel';

    public const STATUS_LOCADO = 'locado';

    public const STATUS_MANUTENCAO = 'manutencao';

    public const STATUS_OPERANDO = 'operando';

    public const STATUS_AGUARDANDO_TRIAGEM = 'aguardando_triagem';

    /**
     * Diferente de STATUS_AGUARDANDO_TRIAGEM (checklist de retorno em
     * andamento): quarentena e' o estado POS-checklist quando o laudo de
     * recebimento encontrou avaria -- fica retido ate liberacao manual
     * mesmo com o checklist 100% preenchido (ver EquipmentPatioArrivalMobile::finalize()).
     */
    public const STATUS_QUARENTENA = 'quarentena';

    /**
     * Bloqueado pra uma Solicitação de Locação urgente (ver
     * ReservasUrgentes::abrirOsReserva()) -- nem disponível pra outro
     * pedido, nem "locado" de verdade ainda. Estado intermediário: alguém
     * precisa devolver manualmente pra disponivel quando o ativo estiver
     * pronto (esta ação não faz isso sozinha, de propósito -- ver docblock
     * da OS de Reserva).
     */
    public const STATUS_RESERVADO = 'reservado';

    protected static ?string $saasFeatureKey = 'tabela_assets';

    protected static ?string $saasPermissionSlug = 'ativo';

    protected static ?string $saasModuleLabel = 'Ativos / Frota';

    protected $guarded = [];

    protected $casts = [
        'veiculo_pesado' => 'boolean',
        'licenciamento_vencimento' => 'date',
        'ipva_vencimento' => 'date',
        'seguro_vencimento' => 'date',
        'tacografo_vencimento' => 'date',
        'acquisition_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'useful_life_years' => 'integer',
        'checklist' => 'array',
    ];

    /**
     * Auto-numeração de patrimônio é opt-in por tenant (Tenant::auto_generate_patrimonio,
     * configurável em Configurações do Tenant) -- por padrão o campo continua
     * livre pra digitação manual, porque cada locadora cliente já pode ter o
     * próprio critério de numeração de patrimônio. Só preenche se o campo
     * vier vazio, pra nunca sobrescrever um valor digitado à mão.
     */
    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            if (! blank($asset->patrimonio)) {
                return;
            }

            $tenant = Tenant::find($asset->tenant_id);

            if (! $tenant?->auto_generate_patrimonio) {
                return;
            }

            $asset->patrimonio = static::nextPatrimonio($asset->tenant_id);
        });
    }

    /**
     * Sequencial simples por tenant, 6 dígitos. Calculado em PHP (não
     * ORDER BY string) pelo mesmo motivo documentado em
     * MaintenanceOrder::booted() -- comparação lexicográfica de string
     * quebra quando sequências de tamanhos diferentes coexistem.
     */
    public static function nextPatrimonio(string $tenantId): string
    {
        $maxSequence = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('patrimonio')
            ->pluck('patrimonio')
            ->map(fn ($value) => ctype_digit((string) $value) ? (int) $value : 0)
            ->max();

        $nextSequence = $maxSequence ? $maxSequence + 1 : 1;

        return str_pad((string) $nextSequence, 6, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    public function activities()
    {
        return $this->activitiesAsSubject();
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(SolicitacaoLocacao::class, 'asset_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'asset_id');
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class);
    }

    public function forkliftSpecification(): HasOne
    {
        return $this->hasOne(ForkliftSpecification::class);
    }

    public function platformSpecification(): HasOne
    {
        return $this->hasOne(PlatformSpecification::class);
    }

    public function generatorSpecification(): HasOne
    {
        return $this->hasOne(GeneratorSpecification::class);
    }

    public function nr13Specification(): HasOne
    {
        return $this->hasOne(AssetNr13Specification::class);
    }

    public function nr13Documents(): HasMany
    {
        return $this->hasMany(Nr13Document::class);
    }

    public function nr13Inspections(): HasMany
    {
        return $this->hasMany(Nr13Inspection::class);
    }

    /**
     * Contrato vigente deste ativo -- fonte de verdade de "onde ele esta
     * fisicamente instalado agora" quando locado (Contract::resolvedLocation()),
     * usado na O.S., Dossie Operacional e cadastro do Ativo. Mesmo valor
     * 'Ativo' ja usado no filtro de ContractResource::table().
     */
    public function activeContract(): ?Contract
    {
        return $this->contracts()->where('status', 'Ativo')->latest('start_date')->first();
    }

    /**
     * Unidade/filial propria onde o ativo fica baseado quando NAO esta
     * locado (Asset.internal_unit_id existia desde 2026-05 mas nao tinha
     * relacao no model nem uso em nenhum Resource ate agora).
     */
    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    /**
     * Posicao estruturada na planta baixa do patio (ver StorageLocation,
     * context=patio_ativos).
     */
    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class);
    }

    /**
     * Nivel de criticidade atual do ativo -- NAO e' um campo proprio do
     * Asset (criticality_level e' coluna texto morta, nunca usada em
     * nenhum form/tabela). A fonte real e' a Matriz ABC (abcMatrix.nivel,
     * um codigo tipo "A"/"B" que bate com CriticalityLevel.code), o mesmo
     * mecanismo ja usado por PainelCriticidade/MaintenanceKanban -- editar
     * isso e' em Manutenção → Matriz ABC (AbcMatrixResource), nao aqui.
     */
    public function currentCriticalityLevel(): ?CriticalityLevel
    {
        $nivel = $this->abcMatrix?->nivel;

        if (! $nivel) {
            return null;
        }

        return CriticalityLevel::where('tenant_id', $this->tenant_id)
            ->where('code', $nivel)
            ->first();
    }

    /**
     * Cor do badge de status -- extraido daqui pra ser reaproveitado tanto
     * na tabela do AssetResource quanto no componente de planta baixa
     * (PlantaBaixaGrid). Cobre os 7 status reais (antes so' 4 tinham cor
     * propria, o resto caia num "info" generico e ficava indistinguivel).
     */
    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::STATUS_DISPONIVEL => 'success',
            self::STATUS_OPERANDO => 'info',
            self::STATUS_LOCADO => 'warning',
            self::STATUS_MANUTENCAO => 'danger',
            self::STATUS_AGUARDANDO_TRIAGEM => 'gray',
            self::STATUS_RESERVADO => 'primary',
            // 'purple' registrado em AdminPanelProvider::colors() so' pra
            // esse caso -- os 6 nomes padrao do Filament (danger/gray/info/
            // primary/success/warning) nao cobrem os 7 status reais.
            self::STATUS_QUARENTENA => 'purple',
            default => 'info',
        };
    }

    public function equipmentMovements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }

    /**
     * Usado pelo registro de Chegada no Patio (PatioEntry) pra checar, ao
     * digitar um patrimonio como "desmobilizacao", se ha uma mobilizacao
     * registrada pra esse Ativo -- nao existia nenhum jeito reusavel de
     * fazer essa pergunta antes.
     */
    public function latestMobilizacao(): ?EquipmentMovement
    {
        return $this->equipmentMovements()
            ->where('type', EquipmentMovement::TYPE_MOBILIZACAO)
            ->latest('completed_at')
            ->first();
    }

    /**
     * Historico de idas e vindas do patio -- so' as movimentacoes com
     * chegada formalmente confirmada (App\Filament\Pages\PatioChegadas),
     * nao toda desmobilizacao concluida.
     */
    public function patioArrivals(): HasManyThrough
    {
        return $this->hasManyThrough(EquipmentPatioArrival::class, EquipmentMovement::class);
    }

    public function damages(): HasMany
    {
        return $this->hasMany(EquipmentDamage::class);
    }

    public function horimeterReadings(): HasMany
    {
        return $this->hasMany(HorimeterReading::class);
    }

    public function downtimeEvents(): HasMany
    {
        return $this->hasMany(AssetDowntimeEvent::class);
    }

    /**
     * Rótulo pra Select de Ativo com patrimônio na frente (ex: "PAT-0042 —
     * Gerador Perkins 180 kVA") -- nome sozinho não distingue ativos
     * repetidos entre tenants/frota (mesmo modelo, patrimônios diferentes),
     * usado em toda tela de Operação (apontamento de horímetro, paradas).
     */
    public function selectLabel(): string
    {
        return ($this->patrimonio ? "{$this->patrimonio} — " : '').$this->name;
    }

    /**
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        return static::orderBy('name')->get()->mapWithKeys(
            fn (Asset $asset) => [$asset->id => $asset->selectLabel()]
        )->all();
    }

    /**
     * Última leitura de horímetro registrada (App\Models\HorimeterReading),
     * com cache de 5 min por tenant+ativo -- diferente da coluna legada
     * horimetro_atual (mantida em sincronia por HorimeterReadingObserver
     * pra não quebrar MaintenancePlan::dueStatusForAsset() e outros
     * consumidores que já leem aquela coluna direto).
     */
    public function getCurrentHorimeterAttribute(): ?float
    {
        return Cache::remember(
            "horimeter:current:{$this->tenant_id}:{$this->id}",
            300,
            fn () => $this->horimeterReadings()->latestForAsset($this->id)->value('reading')
        );
    }

    /**
     * Chamado por HorimeterReadingObserver toda vez que um apontamento novo
     * é criado -- evita que a leitura em cache sobreviva além dos 5 min só
     * por coincidência de timing.
     */
    public function forgetCurrentHorimeterCache(): void
    {
        Cache::forget("horimeter:current:{$this->tenant_id}:{$this->id}");
    }

    /**
     * Token do link público de registro de horímetro (sem login) --
     * gerado sob demanda no primeiro acesso, não no creating(), porque
     * ativos já existentes precisam do token também. Ver
     * HourMeterPublicController e a rota /hour-meter/publico/{token}.
     */
    public function hourMeterPublicToken(): string
    {
        if (! $this->hour_meter_public_token) {
            $this->forceFill(['hour_meter_public_token' => (string) Str::uuid()])->save();
        }

        return $this->hour_meter_public_token;
    }

    public function abcMatrix(): HasOne
    {
        return $this->hasOne(AbcMatrix::class);
    }

    /**
     * Planos de manutenção específicos deste Ativo (asset_id preenchido) --
     * NÃO inclui os herdados do Grupo de Checklist, ver
     * MaintenancePlan::applicableFor() pra a lista combinada (grupo +
     * próprios, com override por nome).
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /**
     * "Personalizar" um item do template do Grupo pra este Ativo
     * especificamente: copia name/interval_hours/interval_days/is_critical
     * pra uma linha nova com asset_id preenchido (source=template), sem
     * mexer no item original do grupo nem nos outros Ativos que o
     * compartilham. Idempotente por nome -- chamar de novo com o mesmo
     * item não duplica, só devolve a customização que já existe (manual ou
     * copiada antes).
     */
    public function copyMaintenancePlanTemplateItem(MaintenancePlan $templateItem): MaintenancePlan
    {
        $existing = $this->maintenancePlans()->where('name', $templateItem->name)->first();

        if ($existing) {
            return $existing;
        }

        return $this->maintenancePlans()->create([
            'tenant_id' => $this->tenant_id,
            'name' => $templateItem->name,
            'interval_hours' => $templateItem->interval_hours,
            'interval_days' => $templateItem->interval_days,
            'is_critical' => $templateItem->is_critical,
            'notes' => $templateItem->notes,
            'is_active' => true,
            'source' => MaintenancePlan::SOURCE_TEMPLATE,
        ]);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function equipmentReplacementsAsOriginal(): HasMany
    {
        return $this->hasMany(EquipmentReplacement::class, 'original_asset_id');
    }

    public function equipmentReplacementsAsReplacement(): HasMany
    {
        return $this->hasMany(EquipmentReplacement::class, 'replacement_asset_id');
    }

    public function checklistGroup(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class);
    }

    /**
     * FK real pra AssetCategory (asset_category_id, 2026-07-24) -- o campo
     * asset_category (texto livre) continua existindo por compatibilidade,
     * mas essa é a fonte confiável daqui pra frente pra "quais Ativos são
     * dessa categoria" (usado por SolicitacaoLocacaoResource).
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * Itens extras de checklist especificos deste ativo (is_template=true,
     * asset_id preenchido) -- somam ao basico do grupo sem alterar o
     * template do grupo, ex: itens do manual do fabricante daquele
     * equipamento em particular.
     */
    public function extraChecklistItems(): HasMany
    {
        return $this->hasMany(MaintenanceOrderChecklist::class, 'asset_id')->where('is_template', true);
    }

    public function preventiveMaintenanceExecutions(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceExecution::class);
    }

    public static function getCategories(): array
    {
        return AssetCategory::orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Busca flexivel pro Dossie Rapido (QR code/campo): tecnico no patio
     * raramente sabe o patrimonio exato de cor, ou digita com erro de
     * espaco/maiuscula. Busca por trecho em patrimonio, tag, nome ou
     * numero de serie -- nao so igualdade exata. Patrimonio comecando
     * exatamente pelo termo aparece primeiro (major sinal de intencao).
     *
     * @return Collection<int, Asset>
     */
    public static function search(string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return new Collection;
        }

        return static::query()
            ->where(function ($query) use ($term) {
                $query->where('patrimonio', 'ilike', "%{$term}%")
                    ->orWhere('tag', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhere('serial_number', 'ilike', "%{$term}%");
            })
            ->orderByRaw('(patrimonio ilike ?) desc', ["{$term}%"])
            ->orderBy('name')
            ->limit(20)
            ->get();
    }

    public static function getDefaultChecklist($categoryId): array
    {
        return [];
    }

    public function getDepreciationData(): array
    {
        $acquisitionValue = (float) ($this->acquisition_value ?? 0);
        $residualValue = (float) ($this->residual_value ?? 0);
        $usefulLifeYears = (int) ($this->useful_life_years ?? 0);
        $acquisitionDate = $this->acquisition_date;

        if ($acquisitionValue <= 0 || $usefulLifeYears <= 0 || ! $acquisitionDate) {
            return [
                'current_value' => $acquisitionValue,
                'accumulated_depreciation' => 0,
                'depreciation_percentage' => 0,
                'monthly_depreciation' => 0,
            ];
        }

        $depreciableAmount = max($acquisitionValue - $residualValue, 0);
        $monthlyDepreciation = $depreciableAmount / ($usefulLifeYears * 12);

        $monthsElapsed = Carbon::parse($acquisitionDate)->diffInMonths(now());
        $accumulatedDepreciation = min($monthlyDepreciation * $monthsElapsed, $depreciableAmount);

        $currentValue = $acquisitionValue - $accumulatedDepreciation;
        $percentage = $depreciableAmount > 0 ? ($accumulatedDepreciation / $depreciableAmount) * 100 : 0;

        return [
            'current_value' => round($currentValue, 2),
            'accumulated_depreciation' => round($accumulatedDepreciation, 2),
            'depreciation_percentage' => round($percentage, 1),
            'monthly_depreciation' => round($monthlyDepreciation, 2),
        ];
    }

    public function getFinancialSummary(): array
    {
        $depreciation = $this->getDepreciationData();

        $totalMaintenanceCost = (float) $this->maintenanceOrders()->sum('total_order_cost');
        $totalLaborCost = (float) $this->maintenanceOrders()->sum('labor_cost');
        $totalMaterialCost = (float) $this->maintenanceOrders()->sum('material_cost');
        // Soma as 2 origens possiveis de custo logistico: movimentacoes
        // ligadas a uma O.S. (via MaintenanceOrder.logistics_cost) e
        // movimentacoes de Despacho de Locacao (via SolicitacaoLocacao.
        // logistics_cost, ate 2026-07-14 nunca chegava aqui). Solicitacoes
        // "combo" (multiplos ativos via assets() pivot) ficam de fora --
        // mesma limitacao que rentalRequests() ja tem hoje, por so' usar
        // o asset_id legado.
        $totalLogisticsCost = (float) $this->maintenanceOrders()->sum('logistics_cost')
            + (float) $this->rentalRequests()->sum('logistics_cost');
        $totalRentalRevenue = (float) $this->contracts()->sum('price');
        $totalOverageRevenue = (float) RentalOverageCharge::query()
            ->where('asset_id', $this->id)
            ->where('status', RentalOverageCharge::STATUS_INVOICED)
            ->sum('amount');
        $totalDamageRevenue = (float) Quote::query()
            ->whereHasMorph('quotable', [EquipmentDamage::class], function ($query) {
                $query->where('asset_id', $this->id)
                    ->whereIn('cause', [EquipmentDamage::CAUSE_MAU_USO, EquipmentDamage::CAUSE_DANO_CLIENTE]);
            })
            ->whereIn('status', [Quote::STATUS_APROVADO, Quote::STATUS_CONCLUIDO])
            ->sum('total_value');

        $totalRevenue = $totalRentalRevenue + $totalOverageRevenue + $totalDamageRevenue;
        $result = $totalRevenue - $totalMaintenanceCost - $depreciation['accumulated_depreciation'];

        return [
            'acquisition_value' => (float) ($this->acquisition_value ?? 0),
            'current_value' => $depreciation['current_value'],
            'accumulated_depreciation' => $depreciation['accumulated_depreciation'],
            'depreciation_percentage' => $depreciation['depreciation_percentage'],
            'total_labor_cost' => round($totalLaborCost, 2),
            'total_material_cost' => round($totalMaterialCost, 2),
            'total_logistics_cost' => round($totalLogisticsCost, 2),
            'total_maintenance_cost' => round($totalMaintenanceCost, 2),
            'total_rental_revenue' => round($totalRentalRevenue, 2),
            'total_overage_revenue' => round($totalOverageRevenue, 2),
            'total_damage_revenue' => round($totalDamageRevenue, 2),
            'total_revenue' => round($totalRevenue, 2),
            'result' => round($result, 2),
        ];
    }

    /**
     * TCO = receita de locação (Contract.price) menos custo total: O.S.
     * (já coberto por getFinancialSummary) mais despesas avulsas do ativo
     * lançadas direto em AccountPayable (asset_id), que getFinancialSummary
     * não somava -- eram uma fonte de custo real do ativo (revisão
     * terceirizada, multa, etc.) fora do fluxo de O.S.
     */
    public function getTotalCostOfOwnership(): array
    {
        $summary = $this->getFinancialSummary();

        $totalAccountsPayable = (float) $this->accountPayables()->sum('amount');
        $totalCost = $summary['total_maintenance_cost'] + $totalAccountsPayable;
        $result = $summary['total_rental_revenue'] - $totalCost;

        return [
            'total_rental_revenue' => $summary['total_rental_revenue'],
            'total_maintenance_cost' => $summary['total_maintenance_cost'],
            'total_accounts_payable' => round($totalAccountsPayable, 2),
            'total_cost' => round($totalCost, 2),
            'result' => round($result, 2),
        ];
    }

    /**
     * MTBF (Mean Time Between Failures) em horas -- período total em
     * operação (primeira leitura de horímetro até agora, ou até a última
     * leitura se o ativo já foi baixado) dividido pelo número de paradas
     * por quebra/corretiva (AssetDowntimeEvent). Sem pelo menos 1 parada
     * fechada, não há intervalo real medido -- retorna null em vez de
     * infinito/zero enganoso.
     */
    public function getMtbfHours(): ?float
    {
        $failureCount = $this->downtimeEvents()
            ->whereIn('reason', [AssetDowntimeEvent::REASON_QUEBRA, AssetDowntimeEvent::REASON_MANUTENCAO_CORRETIVA])
            ->whereNotNull('ended_at')
            ->count();

        if ($failureCount === 0) {
            return null;
        }

        $first = $this->horimeterReadings()->oldest('recorded_at')->value('reading');
        $last = $this->horimeterReadings()->latest('recorded_at')->value('reading');

        if ($first === null || $last === null || (float) $last <= (float) $first) {
            return null;
        }

        return round(((float) $last - (float) $first) / $failureCount, 1);
    }

    /**
     * Média de horas de uso por dia/mês, a partir do delta entre a primeira
     * e a última leitura de horímetro no período corrido desde a primeira
     * leitura -- usada por MaintenancePlan pra estimar quando a próxima
     * preventiva por horímetro deve vencer (dueStatusForAsset já calcula o
     * "quando", isto calcula o "quão rápido está chegando lá").
     */
    public function getAverageHourlyUsage(): array
    {
        $first = $this->horimeterReadings()->oldest('recorded_at')->first();
        $last = $this->horimeterReadings()->latest('recorded_at')->first();

        if (! $first || ! $last || $first->is($last)) {
            return ['daily_average' => 0.0, 'monthly_average' => 0.0];
        }

        $hoursDelta = (float) $last->reading - (float) $first->reading;
        $daysDelta = max($first->recorded_at->diffInDays($last->recorded_at), 1);

        $dailyAverage = $hoursDelta / $daysDelta;

        return [
            'daily_average' => round($dailyAverage, 2),
            'monthly_average' => round($dailyAverage * 30, 2),
        ];
    }

    /** Quantas fotos o ativo aceita (a primeira é a principal). */
    public const MAX_PHOTOS = 3;

    public const GRUPO_MAQUINA = 'maquina';

    public const GRUPO_VEICULO = 'veiculo';

    /** @return array<string, string> */
    public static function grupoLabels(): array
    {
        return [
            self::GRUPO_MAQUINA => 'Máquina / Equipamento',
            self::GRUPO_VEICULO => 'Veículo',
        ];
    }

    public function isVehicle(): bool
    {
        return $this->grupo === self::GRUPO_VEICULO;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('fotos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Miniatura para a lista de ativos (foto de celular pesa vários MB). nonQueued: não depende
        // de worker de fila.
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 160, 160)
            ->nonQueued();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocument::class);
    }

    /** Placa (padrão antigo ABC1234 ou Mercosul ABC1D23) e única por tenant. */
    public static function plateRule(?string $tenantId, ?string $ignoreId = null): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($tenantId, $ignoreId) {
            if (blank($value)) {
                return;
            }

            $plate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));

            if (! preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $plate)) {
                $fail('Placa inválida. Use o formato ABC1234 ou ABC1D23.');

                return;
            }

            if ($tenantId && static::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('placa', $plate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()) {
                $fail('Já existe um ativo com esta placa.');
            }
        };
    }

    /** Chassi de veículo: 17 caracteres, sem as letras I, O e Q. */
    public static function chassiRule(): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) {
            if (blank($value)) {
                return;
            }

            if (! preg_match('/^[A-HJ-NPR-Z0-9]{17}$/i', trim((string) $value))) {
                $fail('Chassi inválido: são 17 caracteres (letras e números, sem I, O e Q).');
            }
        };
    }

    // --- Gestão de Frota (veículos): checklist de saída e retorno ---

    public function checklistsFrota(): HasMany
    {
        return $this->hasMany(FrotaChecklist::class, 'ativo_id');
    }

    /** Último checklist de SAÍDA concluído. */
    public function ultimaSaidaFrota(): ?FrotaChecklist
    {
        return $this->checklistsFrota()->where('tipo', FrotaChecklist::TIPO_SAIDA)->whereNotNull('concluido_em')->latest('concluido_em')->first();
    }

    /** Checklist de saída que está bloqueando o veículo (null = nada bloqueando). */
    public function bloqueioChecklistFrota(): ?FrotaChecklist
    {
        $saida = $this->ultimaSaidaFrota();

        return $saida && $saida->estaBloqueado() ? $saida : null;
    }

    /** O veículo pode sair? Não, enquanto a última saída estiver bloqueada e não liberada. */
    public function podeSair(): bool
    {
        return $this->bloqueioChecklistFrota() === null;
    }

    /** Último checklist COMPLETO concluído. */
    public function ultimoChecklistCompleto(): ?FrotaChecklist
    {
        return $this->checklistsFrota()
            ->whereNotNull('concluido_em')
            ->whereHas('modelo', fn ($q) => $q->where('tipo', FrotaModeloChecklist::TIPO_COMPLETO))
            ->latest('concluido_em')
            ->first();
    }

    /** Pendência: sem checklist completo, ou o último tem mais de 7 dias. */
    public function checklistCompletoVencido(): bool
    {
        $ultimo = $this->ultimoChecklistCompleto();

        return ! $ultimo || $ultimo->concluido_em->lt(now()->subDays(FrotaChecklist::DIAS_CHECKLIST_COMPLETO));
    }

    /** Pneus montados agora neste veículo (instalações abertas). */
    public function pneusMontados(): HasMany
    {
        return $this->hasMany(FrotaInstalacaoComponente::class, 'ativo_id')->where('componente_type', 'pneu')->whereNull('removido_em');
    }

    /** Menor sulco medido mais recente do veículo (checklist completo ou inspeção de pneu). */
    public function ultimoSulcoMm(): ?float
    {
        $v = FrotaInspecaoPneu::where('ativo_id', $this->id)->whereNotNull('sulco_mm')->orderByDesc('inspecionado_em')->value('sulco_mm');

        return $v !== null ? (float) $v : null;
    }

    /** Baterias montadas agora neste veículo (instalações abertas). */
    public function bateriasMontadas(): HasMany
    {
        return $this->hasMany(FrotaInstalacaoComponente::class, 'ativo_id')->where('componente_type', 'bateria')->whereNull('removido_em');
    }

    /** Última tensão testada do veículo (checklist completo ou teste de bateria), em volts. */
    public function ultimaTensaoV(): ?float
    {
        $v = FrotaTesteBateria::where('ativo_id', $this->id)->orderByDesc('testado_em')->value('tensao');

        return $v !== null ? (float) $v : null;
    }

    /** Todas as instalações de bateria do veículo (abertas e antigas). */
    public function instalacoesBateria(): HasMany
    {
        return $this->hasMany(FrotaInstalacaoComponente::class, 'ativo_id')->where('componente_type', 'bateria');
    }

    // --- Gestão de Frota: óleo ---

    public function trocasOleo(): HasMany
    {
        return $this->hasMany(FrotaTrocaOleo::class, 'ativo_id')->orderByDesc('realizado_em');
    }

    public function planoOleoAtivo(): ?FrotaPlanoOleo
    {
        return FrotaPlanoOleo::where('ativo_id', $this->id)->where('ativo', true)->first();
    }

    /** @return array<string, string> id => "PLACA — nome" dos veículos do cliente (para listas de escolha). */
    public static function opcoesVeiculos(): array
    {
        return static::query()->where('grupo', self::GRUPO_VEICULO)->orderBy('placa')->get()
            ->mapWithKeys(fn (self $a) => [$a->id => trim(($a->placa ? $a->placa.' — ' : '').$a->name)])->all();
    }
}
