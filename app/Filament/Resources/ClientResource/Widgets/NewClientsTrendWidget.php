<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Filament\Widgets\Charts\LineChartWithMarkers;
use App\Models\Client;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

class NewClientsTrendWidget extends LineChartWithMarkers
{
    protected static bool $isLazy = false;

    private const MESES_ABREV = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    public function mount(
        array $labels = [],
        array $series = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        string $markerStyle = 'circle',
    ): void {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn (Carbon $m) => self::MESES_ABREV[$m->month])->all();

        $dados = $months->map(fn (Carbon $month) => Client::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        parent::mount(
            labels: $labels,
            series: [
                ['name' => 'Novos Clientes', 'color' => '#3987e5', 'data' => $dados],
            ],
            chartTitle: 'Novos Clientes por Mês',
        );
    }
}
