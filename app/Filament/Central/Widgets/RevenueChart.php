<?php

namespace App\Filament\Central\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Faturamento Real Acumulado (MRR)';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = [];
        $labels = [];
        
        // Pegamos o mês atual dinamicamente (Maio = 5)
        $mesAtual = now()->month;
        $nomesMeses = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul'];

        // O loop só vai até o mês de hoje. Junho não aparecerá.
        foreach (range(1, $mesAtual) as $month) {
            $labels[] = $nomesMeses[$month];

            // Soma real do campo mrr_value para clientes criados até este mês
            $somaReal = Tenant::where('created_at', '<=', now()->month($month)->endOfMonth())
                ->whereYear('created_at', now()->year)
                ->sum('mrr_value');
            
            $data[] = $somaReal;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Receita Real (R$)',
                    'data' => $data,
                    'fill' => 'start',
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
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