<?php

namespace App\Filament\Resources\MaterialResource\Widgets;

use App\Filament\Widgets\Charts\AreaChart;
use App\Models\StockMovement;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

class StockEntriesVsExitsAreaWidget extends AreaChart
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

        $entradas = $months->map(fn (Carbon $month) => StockMovement::where('type', StockMovement::TYPE_ENTRADA_COMPRA)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->sum('quantity'))->all();

        $saidas = $months->map(fn (Carbon $month) => StockMovement::where('type', StockMovement::TYPE_SAIDA_CONSUMO)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->sum('quantity'))->all();

        parent::mount(
            labels: $labels,
            seriesA: ['name' => 'Entradas', 'color' => '#199e70', 'data' => array_map(fn ($v) => (float) $v, $entradas)],
            seriesB: ['name' => 'Saídas', 'color' => '#e6534d', 'data' => array_map(fn ($v) => (float) $v, $saidas)],
            chartTitle: 'Entradas vs. Saídas de Estoque por Mês',
        );
    }
}
