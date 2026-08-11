<?php

namespace App\Filament\Resources\SupplierResource\Widgets;

use App\Filament\Widgets\Charts\AreaChart;
use App\Models\PurchaseOrder;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Mesmo critério já usado em LeadsWonVsLostAreaWidget/
 * MaintenanceOrdersOpenVsClosedAreaWidget: agrupa por mês de criação
 * (created_at), split pelo status ATUAL.
 */
class PurchaseOrdersOpenVsReceivedAreaWidget extends AreaChart
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
        array $seriesC = [],
        bool $empilhar = false,
    ): void {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn (Carbon $m) => self::MESES_ABREV[$m->month])->all();

        $abertas = $months->map(fn (Carbon $month) => PurchaseOrder::where('status', PurchaseOrder::STATUS_ABERTA)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        $recebidas = $months->map(fn (Carbon $month) => PurchaseOrder::where('status', PurchaseOrder::STATUS_RECEBIDA)
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        parent::mount(
            labels: $labels,
            seriesA: ['name' => 'Abertas', 'color' => '#c98500', 'data' => $abertas],
            seriesB: ['name' => 'Recebidas', 'color' => '#199e70', 'data' => $recebidas],
            chartTitle: 'Ordens de Compra Abertas vs. Recebidas por Mês',
        );
    }
}
