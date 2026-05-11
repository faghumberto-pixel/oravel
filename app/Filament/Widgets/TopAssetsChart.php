<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopAssetsChart extends ChartWidget
{
    protected static ?string $heading = 'Ranking de Rentabilidade (Ativos)';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $tenantId = auth()->user()->tenant_id;

        // Soma o total de 'price' agrupado por asset_id
        $ranking = Contract::where('tenant_id', $tenantId)
            ->select('asset_id', DB::raw('SUM(price) as total_revenue'))
            ->groupBy('asset_id')
            ->orderBy('total_revenue', 'desc')
            ->with('asset')
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Receita Total Acumulada (R$)',
                    'data' => $ranking->pluck('total_revenue')->toArray(),
                    'backgroundColor' => '#6366f1',
                ],
            ],
            'labels' => $ranking->map(fn($item) => $item->asset?->name ?? 'N/A')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}