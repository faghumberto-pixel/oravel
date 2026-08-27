<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MaintenancePlan extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;

    public const SOURCE_TEMPLATE = 'template';

    public const SOURCE_MANUAL = 'manual';

    protected static ?string $saasFeatureKey = 'tabela_maintenance_plans';

    protected static ?string $saasPermissionSlug = 'plano_manutencao';

    protected static ?string $saasModuleLabel = 'Planos de Manutencao';

    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'auto_create_order' => 'boolean',
        'is_critical' => 'boolean',
        'last_service_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Injeção automática e segura do tenant_id para isolamento dos dados
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id
                                    ?? filament()->getTenant()?->id
                                    ?? session('tenant_id');
            }
        });
    }

    // O Filament exige este método para o Tenancy funcionar
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Quando checklist_group_id esta preenchido (asset_id nulo), este plano
     * e um item de TEMPLATE de preventiva do Grupo -- vale pra todo Ativo
     * daquele grupo, ao inves de um Ativo especifico.
     */
    public function checklistGroup(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceExecution::class);
    }

    public function isGroupTemplate(): bool
    {
        return $this->checklist_group_id !== null;
    }

    /**
     * Importa os itens de uma PmpEquipmentFamily (catálogo global, sem
     * tenant_id -- ver app/Models/PmpEquipmentFamily.php) para um
     * ChecklistGroup do tenant, um MaintenancePlan por item. Mesmo padrão
     * de override-por-nome de Asset::copyMaintenancePlanTemplateItem(): se
     * já existe uma linha com o mesmo nome nesse grupo (import anterior ou
     * customização manual do tenant), não duplica nem sobrescreve -- o
     * import é uma cópia pontual, não um link vivo com o catálogo global.
     *
     * @return Collection<int, MaintenancePlan>
     */
    public static function importFromFamilyTemplate(PmpEquipmentFamily $family, ChecklistGroup $targetGroup): Collection
    {
        $existingNames = static::where('tenant_id', $targetGroup->tenant_id)
            ->where('checklist_group_id', $targetGroup->id)
            ->pluck('name')
            ->all();

        return $family->templateItems->map(function (PmpTemplateItem $item) use ($targetGroup, $existingNames) {
            if (in_array($item->name, $existingNames, true)) {
                return static::where('tenant_id', $targetGroup->tenant_id)
                    ->where('checklist_group_id', $targetGroup->id)
                    ->where('name', $item->name)
                    ->first();
            }

            return static::create([
                'tenant_id' => $targetGroup->tenant_id,
                'checklist_group_id' => $targetGroup->id,
                'name' => $item->name,
                'interval_hours' => $item->interval_hours,
                'interval_days' => $item->interval_days,
                'is_critical' => $item->is_critical,
                'auto_create_order' => $item->auto_create_order,
                'notes' => $item->notes,
                'is_active' => true,
                'source' => self::SOURCE_TEMPLATE,
            ]);
        });
    }

    /**
     * Complementa importFromFamilyTemplate(): copia o checklist técnico da
     * família (PmpTemplateChecklistItem, catálogo global) para
     * MaintenanceOrderChecklist is_template=true no ChecklistGroup do
     * tenant. Daqui, MaintenanceOrderChecklistSnapshotObserver já copia
     * pra qualquer OS nova daquele grupo automaticamente -- não precisa
     * mexer no Observer. Mesmo override-por-nome de importFromFamilyTemplate().
     *
     * @return Collection<int, MaintenanceOrderChecklist>
     */
    public static function importChecklistFromFamilyTemplate(PmpEquipmentFamily $family, ChecklistGroup $targetGroup): Collection
    {
        $existingNames = MaintenanceOrderChecklist::where('tenant_id', $targetGroup->tenant_id)
            ->where('checklist_group_id', $targetGroup->id)
            ->where('is_template', true)
            ->pluck('item_name')
            ->all();

        return $family->checklistItems->map(function (PmpTemplateChecklistItem $item) use ($targetGroup, $existingNames) {
            if (in_array($item->item_name, $existingNames, true)) {
                return MaintenanceOrderChecklist::where('tenant_id', $targetGroup->tenant_id)
                    ->where('checklist_group_id', $targetGroup->id)
                    ->where('is_template', true)
                    ->where('item_name', $item->item_name)
                    ->first();
            }

            return MaintenanceOrderChecklist::create([
                'tenant_id' => $targetGroup->tenant_id,
                'checklist_group_id' => $targetGroup->id,
                'is_template' => true,
                'section' => $item->section,
                'item_name' => $item->item_name,
                'instructions' => $item->instructions,
            ]);
        });
    }

    /**
     * Status de vencimento deste item de preventiva para um Ativo especifico.
     * Para planos por-Ativo legados, usa last_service_hours direto. Para
     * templates por-Grupo, busca a ultima execucao REAL deste item para
     * este Ativo (PreventiveMaintenanceExecution) -- ja que o mesmo plano
     * (linha) e compartilhado por varios Ativos do grupo, cada um com seu
     * proprio historico de quando foi executado.
     *
     * interval_days e interval_battery_cycles (opcionais) sao avaliados em
     * paralelo ao interval_hours -- vencido em QUALQUER uma das dimensoes
     * ja preenchidas conta como vencido (ex: "troca a cada 250h OU 6
     * meses, o que vier primeiro"). interval_battery_cycles usa
     * Asset.battery_cycles_atual (alimentado por BatteryCycleReading,
     * mesmo padrao de horimetro_atual/HorimeterReading) -- relevante pra
     * PTA/empilhadeira eletrica.
     *
     * @return array{last_service_hours: float, due_at_hours: float, overdue_hours: float, is_overdue: bool, due_at_date: ?string, overdue_days: int, overdue_battery_cycles: int}
     */
    public function dueStatusForAsset(Asset $asset): array
    {
        $lastServiceHours = (float) $this->last_service_hours;
        $lastServiceDate = $this->last_service_date;
        $lastServiceBatteryCycles = (int) $this->last_service_battery_cycles;

        if ($this->isGroupTemplate()) {
            $lastExecution = $this->executions()
                ->where('asset_id', $asset->id)
                ->orderByDesc('horimetro_at_execution')
                ->first();

            $lastServiceHours = $lastExecution ? (float) $lastExecution->horimetro_at_execution : 0.0;
            $lastServiceDate = $lastExecution?->created_at?->toDateString() ?? $lastServiceDate;
        }

        $overdueByHours = false;
        $dueAtHours = $lastServiceHours;
        $overdueHours = 0.0;

        if ($this->interval_hours) {
            $dueAtHours = $lastServiceHours + (float) $this->interval_hours;
            $currentHours = (float) $asset->horimetro_atual;
            $overdueHours = max(0.0, $currentHours - $dueAtHours);
            $overdueByHours = $currentHours >= $dueAtHours;
        }

        $overdueByDays = false;
        $dueAtDate = null;
        $overdueDays = 0;

        if ($this->interval_days && $lastServiceDate) {
            $dueAtDate = Carbon::parse($lastServiceDate)->addDays($this->interval_days);
            $overdueByDays = now()->greaterThanOrEqualTo($dueAtDate);
            // true = valor absoluto -- Carbon 3 mudou o default de diffInDays()
            // pra $absolute=false (mesma armadilha ja documentada em
            // MaintenanceOrder::booted() pro diffInSeconds()), sem isso dava
            // um numero negativo aqui (data de vencimento no passado).
            $overdueDays = $overdueByDays ? (int) now()->diffInDays($dueAtDate, true) : 0;
        }

        $overdueByBatteryCycles = false;
        $overdueBatteryCycles = 0;

        if ($this->interval_battery_cycles) {
            $dueAtCycles = $lastServiceBatteryCycles + $this->interval_battery_cycles;
            $currentCycles = (int) $asset->battery_cycles_atual;
            $overdueBatteryCycles = max(0, $currentCycles - $dueAtCycles);
            $overdueByBatteryCycles = $currentCycles >= $dueAtCycles;
        }

        return [
            'last_service_hours' => $lastServiceHours,
            'due_at_hours' => $dueAtHours,
            'overdue_hours' => $overdueHours,
            'due_at_date' => $dueAtDate?->toDateString(),
            'overdue_days' => $overdueDays,
            'overdue_battery_cycles' => $overdueBatteryCycles,
            'is_overdue' => $overdueByHours || $overdueByDays || $overdueByBatteryCycles,
        ];
    }

    /**
     * Projeta em qual mês (0 = mês atual, 1 = próximo, ...) este plano vai
     * vencer, olhando pros próximos $months meses -- dueStatusForAsset()
     * só responde "vencido agora ou não", isto responde "quando" pros
     * meses seguintes. Usa a MESMA regra de "vence pelo que chegar
     * primeiro" (horas via Asset::getAverageHourlyUsage(), ou data
     * absoluta via interval_days) -- nenhum cálculo de vencimento novo,
     * só projeção temporal em cima do que já existe.
     *
     * Batería (interval_battery_cycles) não é projetada aqui -- não existe
     * hoje uma média de uso de ciclos por mês equivalente a
     * getAverageHourlyUsage(), então o vencimento por bateria só aparece
     * quando já estiver vencido (ver dueStatusForAsset()).
     *
     * @return array<int, array{month_offset: int, month_label: string, due_at: string, reason: string}>
     */
    public function projectedDueDates(Asset $asset, int $months = 3): array
    {
        $status = $this->dueStatusForAsset($asset);
        $projections = [];

        if ($status['is_overdue']) {
            return [[
                'month_offset' => 0,
                'month_label' => now()->translatedFormat('F/Y'),
                'due_at' => $status['due_at_date'] ?? now()->toDateString(),
                'reason' => $status['due_at_date'] ? 'Vencido por data' : 'Vencido por horímetro',
            ]];
        }

        if ($this->interval_days && $status['due_at_date']) {
            $dueAt = Carbon::parse($status['due_at_date']);
            $monthOffset = now()->startOfMonth()->diffInMonths($dueAt->copy()->startOfMonth());

            if ($monthOffset <= $months) {
                $projections[] = [
                    'month_offset' => $monthOffset,
                    'month_label' => $dueAt->translatedFormat('F/Y'),
                    'due_at' => $dueAt->toDateString(),
                    'reason' => 'Vencimento por data',
                ];
            }
        }

        if ($this->interval_hours) {
            $dailyAverage = $asset->getAverageHourlyUsage()['daily_average'];

            if ($dailyAverage > 0) {
                $remainingHours = max(0, $status['due_at_hours'] - (float) $asset->horimetro_atual);
                $daysUntilDue = (int) ceil($remainingHours / $dailyAverage);
                $dueAt = now()->addDays($daysUntilDue);
                $monthOffset = now()->startOfMonth()->diffInMonths($dueAt->copy()->startOfMonth());

                if ($monthOffset <= $months) {
                    $projections[] = [
                        'month_offset' => $monthOffset,
                        'month_label' => $dueAt->translatedFormat('F/Y'),
                        'due_at' => $dueAt->toDateString(),
                        'reason' => 'Vencimento por horímetro (projetado)',
                    ];
                }
            }
        }

        return $projections;
    }

    /**
     * Resolve quais planos (de um conjunto ja pre-carregado, evitando N+1
     * quando chamado num loop sobre varios Ativos) se aplicam a um Ativo:
     * os proprios (asset_id) + os do Grupo dele (checklist_group_id), MENOS
     * os itens do Grupo que o Ativo ja tem uma versao propria com o MESMO
     * NOME (customizacao/override -- nao soma duplicado, ver
     * Asset::copyMaintenancePlanTemplateItem()). Quando $allPlans nao e'
     * informado, busca direto no banco (uso pontual pra 1 Ativo, sem risco
     * de N+1).
     *
     * @param  ?Collection<int, MaintenancePlan>  $allPlans
     * @return Collection<int, MaintenancePlan>
     */
    public static function applicableFor(Asset $asset, ?Collection $allPlans = null): Collection
    {
        if ($allPlans === null) {
            $allPlans = static::query()
                ->where(function ($query) use ($asset) {
                    $query->where('asset_id', $asset->id);

                    if ($asset->checklist_group_id) {
                        $query->orWhere('checklist_group_id', $asset->checklist_group_id);
                    }
                })
                ->get();
        }

        $assetPlans = $allPlans->where('asset_id', $asset->id);
        $groupPlans = $asset->checklist_group_id
            ? $allPlans->where('checklist_group_id', $asset->checklist_group_id)
            : collect();

        $overriddenNames = $assetPlans->pluck('name')->all();

        return $groupPlans
            ->reject(fn (MaintenancePlan $plan) => in_array($plan->name, $overriddenNames, true))
            ->merge($assetPlans)
            ->values();
    }
}
