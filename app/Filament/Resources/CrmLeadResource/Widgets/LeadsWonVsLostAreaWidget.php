<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Filament\Widgets\Charts\AreaChart;
use App\Models\CrmLead;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Mesmo critério já usado em MaintenanceOrdersOpenVsClosedAreaWidget
 * (Painel de Controle): agrupa por mês de criação (created_at), split
 * pelo stage ATUAL -- não pela data real de conversão/perda (o model não
 * guarda isso separado).
 */
class LeadsWonVsLostAreaWidget extends AreaChart
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    private const MESES_ABREV = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    public function mount(
        array $labels = [],
        array $seriesA = [],
        array $seriesB = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
    ): void {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn (Carbon $m) => self::MESES_ABREV[$m->month])->all();

        $convertidos = $months->map(fn (Carbon $month) => CrmLead::where('stage', CrmLead::STAGE_CONVERTIDO)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        $perdidos = $months->map(fn (Carbon $month) => CrmLead::where('stage', CrmLead::STAGE_PERDIDO)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        parent::mount(
            labels: $labels,
            seriesA: ['name' => 'Convertidos', 'color' => '#199e70', 'data' => $convertidos],
            seriesB: ['name' => 'Perdidos', 'color' => '#e6534d', 'data' => $perdidos],
            chartTitle: 'Convertidos vs. Perdidos por Mês',
        );
    }
}
