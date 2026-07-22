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
     * Status de vencimento deste item de preventiva para um Ativo especifico.
     * Para planos por-Ativo legados, usa last_service_hours direto. Para
     * templates por-Grupo, busca a ultima execucao REAL deste item para
     * este Ativo (PreventiveMaintenanceExecution) -- ja que o mesmo plano
     * (linha) e compartilhado por varios Ativos do grupo, cada um com seu
     * proprio historico de quando foi executado.
     *
     * interval_days (novo, opcional) e' avaliado em paralelo ao
     * interval_hours -- vencido em QUALQUER uma das duas dimensoes ja
     * preenchidas conta como vencido (ex: "troca a cada 250h OU 6 meses,
     * o que vier primeiro").
     *
     * @return array{last_service_hours: float, due_at_hours: float, overdue_hours: float, is_overdue: bool, due_at_date: ?string, overdue_days: int}
     */
    public function dueStatusForAsset(Asset $asset): array
    {
        $lastServiceHours = (float) $this->last_service_hours;
        $lastServiceDate = $this->last_service_date;

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

        return [
            'last_service_hours' => $lastServiceHours,
            'due_at_hours' => $dueAtHours,
            'overdue_hours' => $overdueHours,
            'due_at_date' => $dueAtDate?->toDateString(),
            'overdue_days' => $overdueDays,
            'is_overdue' => $overdueByHours || $overdueByDays,
        ];
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
