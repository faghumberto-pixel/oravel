<?php

namespace App\Models;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Domain\Fleet\Models\PlatformSpecification;
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

class Asset extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;
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
        'acquisition_date' => 'date',
        'acquisition_value' => 'decimal:2',
        'residual_value' => 'decimal:2',
        'useful_life_years' => 'integer',
        'checklist' => 'array',
    ];

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

        $result = $totalRentalRevenue - $totalMaintenanceCost;

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
}
