<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueEvolutionChart extends ChartWidget
{
    protected static ?string $heading = 'Evolução da Receita e Ticket Médio (Contratos)';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $data = [];
        $labels = [];
        $mesesParaAtras = 6;

        for ($i = $mesesParaAtras; $i >= 0; $i--) {
            $mes = now()->subMonths($i);
            $labels[] = $mes->translatedFormat('M');

            $faturamentoMes = Contract::where('tenant_id', $tenantId)
                ->whereMonth('start_date', $mes->month)
                ->whereYear('start_date', $mes->year)
                ->sum('price');
            
            $data[] = $faturamentoMes;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Receita Contratada (R$)',
                    'data' => $data,
                    'borderColor' => '#10b981',
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}