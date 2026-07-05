<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;

class MaintenanceByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Manutenções por Status';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 1];

    /**
     * Mesmas 6 colunas reais do Kanban (MaintenanceKanban::statusMap),
     * na mesma ordem, para leitura consistente entre as duas telas.
     */
    private const STATUSES = [
        'aguardando_diagnostico' => 'Aguardando Diagnóstico',
        'em_manutencao' => 'Em Manutenção',
        'aguardando_peca' => 'Aguardando Peça',
        'teste_qualidade' => 'Teste de Qualidade',
        'pendencia' => 'Pendência',
        'concluido' => 'Concluído',
    ];

    /**
     * Paleta categorica validada (dataviz skill, dark mode) -- as cores
     * hexadecimais cruas do Kanban (Tailwind amber/orange/slate) nao
     * passam no validador de acessibilidade para esta superficie escura,
     * entao aqui usamos a paleta validada, na mesma ordem das colunas.
     */
    private const COLORS = ['#3987e5', '#199e70', '#c98500', '#008300', '#9085e9', '#e66767'];

    protected function getData(): array
    {
        $counts = [];
        foreach (self::STATUSES as $status => $label) {
            $counts[] = MaintenanceOrder::where('internal_status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'OS',
                    'data' => $counts,
                    'backgroundColor' => self::COLORS,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_values(self::STATUSES),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#334155'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
