<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class MaintenancePlan extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_maintenance_plans';

    protected static ?string $saasPermissionSlug = 'plano_manutencao';

    protected static ?string $saasModuleLabel = 'Planos de Manutencao';

    use HasUuids;

    protected $guarded = [];

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
     * @return array{last_service_hours: float, due_at_hours: float, overdue_hours: float, is_overdue: bool}
     */
    public function dueStatusForAsset(Asset $asset): array
    {
        $lastServiceHours = (float) $this->last_service_hours;

        if ($this->isGroupTemplate()) {
            $lastExecution = $this->executions()
                ->where('asset_id', $asset->id)
                ->orderByDesc('horimetro_at_execution')
                ->first();

            $lastServiceHours = $lastExecution ? (float) $lastExecution->horimetro_at_execution : 0.0;
        }

        $dueAtHours = $lastServiceHours + (float) $this->interval_hours;
        $currentHours = (float) $asset->horimetro_atual;
        $overdueHours = max(0.0, $currentHours - $dueAtHours);

        return [
            'last_service_hours' => $lastServiceHours,
            'due_at_hours' => $dueAtHours,
            'overdue_hours' => $overdueHours,
            'is_overdue' => $currentHours >= $dueAtHours,
        ];
    }
}
