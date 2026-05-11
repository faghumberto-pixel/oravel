<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class FleetStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Mapa de Ocupação e Status da Frota';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Buscamos os status reais do seu banco
        $data = Asset::where('tenant_id', $tenantId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Quantidade de Equipamentos',
                    'data' => array_values($data),
                    'backgroundColor' => ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#6366f1'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // Gráfico de barras para comparar categorias/status
    }
}