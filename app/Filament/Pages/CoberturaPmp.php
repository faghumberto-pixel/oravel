<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

/**
 * Dá visibilidade ao vínculo Plano de Manutenção <-> Ativo (já existente,
 * MaintenancePlan::applicableFor()) que hoje só aparecia como texto
 * informativo dentro do form de uma OS já criada (ver
 * MaintenanceOrderResource.php, Placeholder "Preventivas Sugeridas").
 * Ver docs/superpowers/specs/2026-08-29-cobertura-pmp-design.md.
 */
class CoberturaPmp extends Page
{
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'PMP';

    protected static ?string $navigationLabel = 'Cobertura de PMP';

    protected static ?string $title = 'Cobertura de Manutenção Preventiva';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.cobertura-pmp';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * sem_grupo: ativo sem grupo, ou grupo sem nenhum plano ativo aplicável.
     * vencido: algum plano aplicável com is_overdue=true.
     * vencendo: nenhum vencido, mas algum projectedDueDates($asset,0) não-vazio.
     * em_dia: nenhum vencido, nenhuma projeção pro mês atual.
     */
    public static function statusFor(Asset $asset): string
    {
        $plans = MaintenancePlan::applicableFor($asset)->where('is_active', true);

        if ($plans->isEmpty()) {
            return 'sem_grupo';
        }

        $temVencido = $plans->contains(fn (MaintenancePlan $plan) => $plan->dueStatusForAsset($asset)['is_overdue']);

        if ($temVencido) {
            return 'vencido';
        }

        $temVencendoEsteMes = $plans->contains(
            fn (MaintenancePlan $plan) => collect($plan->projectedDueDates($asset, 0))->isNotEmpty()
        );

        return $temVencendoEsteMes ? 'vencendo' : 'em_dia';
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'em_dia' => 'success',
            'vencendo' => 'warning',
            'vencido' => 'danger',
            default => 'gray',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'em_dia' => 'Em Dia',
            'vencendo' => 'Vencendo',
            'vencido' => 'Vencido',
            default => 'Sem Grupo',
        };
    }
}
