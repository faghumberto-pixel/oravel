<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MaintenanceOrderVolumeChart extends ChartWidget
{
    protected static ?string $heading = 'Volume por Status de OS';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $tenantId = Auth::user()->tenant_id;

        // Buscamos os dados reais do Postgres
        $results = MaintenanceOrder::where('tenant_id', $tenantId)
            ->select(
                DB::raw("to_char(created_at, 'MM') as month_num"),
                DB::raw("to_char(created_at, 'Mon') as month_name"),
                DB::raw("count(*) filter (where status IN ('Aberto', 'Em Andamento', 'Pendente')) as open_count"),
                DB::raw("count(*) filter (where status IN ('Concluída', 'completed')) as done_count")
            )
            ->groupBy('month_num', 'month_name')
            ->orderBy('month_num')
            ->get();

        // Se você tiver poucos dados, o gráfico fica vazio. 
        // Aqui pegamos os nomes dos meses para o eixo X
        $labels = $results->pluck('month_name')->toArray();
        if (empty($labels)) { $labels = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai']; }

        return [
            'datasets' => [
                [
                    'label' => 'Em Aberto',
                    'data' => $results->pluck('open_count')->toArray(),
                    'backgroundColor' => 'rgba(160, 160, 160, 0.6)', // Cinza
                    'borderColor' => '#A0A0A0',
                    'fill' => true,      // Preenche a área (Montanha)
                    'tension' => 0.4,   // Faz a curva ser suave
                    'pointRadius' => 3,
                ],
                [
                    'label' => 'Concluídas',
                    'data' => $results->pluck('done_count')->toArray(),
                    'backgroundColor' => 'rgba(0, 184, 172, 0.6)', // Turquesa
                    'borderColor' => '#00B8AC',
                    'fill' => true,      // Preenche a área
                    'tension' => 0.4,   // Curva suave
                    'pointRadius' => 3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        // MUDE PARA LINE. O Chart.js usa 'line' com 'fill' para fazer o efeito de área.
        return 'line'; 
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'stacked' => true, // Empilha uma cor sobre a outra
                    'beginAtZero' => true,
                ],
                'x' => [
                    'stacked' => true,
                ],
            ],
            'plugins' => [
                'legend' => ['display' => true],
            ],
        ];
    }
}