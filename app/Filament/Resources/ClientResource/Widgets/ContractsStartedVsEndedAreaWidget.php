<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Filament\Widgets\Charts\AreaChart;
use App\Models\Contract;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

class ContractsStartedVsEndedAreaWidget extends AreaChart
{
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

        $iniciados = $months->map(fn (Carbon $month) => Contract::whereNotNull('start_date')
            ->whereBetween('start_date', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        $encerrados = $months->map(fn (Carbon $month) => Contract::whereNotNull('end_date')
            ->whereBetween('end_date', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        parent::mount(
            labels: $labels,
            seriesA: ['name' => 'Iniciados', 'color' => '#199e70', 'data' => $iniciados],
            seriesB: ['name' => 'Encerrados', 'color' => '#c98500', 'data' => $encerrados],
            chartTitle: 'Contratos Iniciados vs. Encerrados por Mês',
        );
    }
}
